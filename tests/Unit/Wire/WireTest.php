<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Tests\Unit\Wire;

use KaririCode\Grpc\Enum\StatusCode;
use KaririCode\Grpc\Exception\FrameException;
use KaririCode\Grpc\Exception\GrpcCode;
use KaririCode\Grpc\Exception\InvalidConfigurationException;
use KaririCode\Grpc\Exception\InvalidMessageException;
use KaririCode\Grpc\Wire\MessageFrame;
use KaririCode\Grpc\Wire\MessageFramer;
use KaririCode\Grpc\Wire\StatusMessage;
use KaririCode\Grpc\Wire\Timeout;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MessageFramer::class)]
#[CoversClass(MessageFrame::class)]
#[CoversClass(Timeout::class)]
#[CoversClass(StatusMessage::class)]
final class WireTest extends TestCase
{
    // ── Length-prefixed messages ─────────────────────────────────────────

    #[Test]
    public function aMessageIsFramedWithFlagAndBigEndianLength(): void
    {
        $framer = new MessageFramer();

        self::assertSame("\x00\x00\x00\x00\x05hello", $framer->frame('hello'));
        self::assertSame("\x00\x00\x00\x00\x00", $framer->frame(''));
        self::assertSame("\x00\x00\x00\x01\x2C", substr($framer->frame(str_repeat('x', 300)), 0, 5));
        self::assertSame(4_194_304, $framer->maxMessageSize);
    }

    #[Test]
    public function aFrameIsReadBackWithWhatItUsed(): void
    {
        $framer = new MessageFramer();
        $stream = $framer->frame('first') . "\x01\x00\x00\x00\x02gz" . "\x00\x00";

        $first = $framer->parse($stream);
        self::assertNotNull($first);
        [$frame, $used] = $first;
        self::assertSame('first', $frame->message);
        self::assertFalse($frame->compressed);
        self::assertSame(5, $frame->length());
        self::assertSame(10, $used);

        $second = $framer->parse(substr($stream, $used));
        self::assertNotNull($second);
        self::assertSame('gz', $second[0]->message);
        self::assertTrue($second[0]->compressed);
        self::assertSame(7, $second[1]);

        self::assertNull($framer->parse(substr($stream, $used + 7)), 'two bytes are not a prefix yet');
    }

    #[Test]
    public function anIncompleteMessageWaits(): void
    {
        $framer = new MessageFramer();
        $bytes = $framer->frame(str_repeat('m', 70000));

        foreach ([0, 1, 4, 5, 6, 1000, strlen($bytes) - 1] as $cut) {
            self::assertNull($framer->parse(substr($bytes, 0, $cut)), 'cut at ' . $cut);
        }

        self::assertSame(70000, self::frame($framer->parse($bytes))->length());
        self::assertFalse((new MessageFrame('m'))->compressed, 'a frame is uncompressed unless the flag says so');
        self::assertSame('', self::frame($framer->parse("\x00\x00\x00\x00\x00"))->message, 'an empty message is complete at five bytes');
    }

    #[Test]
    public function theLimitIsCheckedFromThePrefix(): void
    {
        $framer = new MessageFramer(maxMessageSize: 10);

        self::assertSame(10, self::frame($framer->parse($framer->frame(str_repeat('x', 10))))->length());

        foreach (["\x00\x00\x00\x00\x0B", "\x00\xFF\xFF\xFF\xFF", "\x01\x80\x00\x00\x00"] as $prefix) {
            try {
                $framer->parse($prefix);
                self::fail('a message above the limit was accepted');
            } catch (FrameException $e) {
                self::assertSame(GrpcCode::MessageTooLarge, $e->errorCode);
                self::assertSame(StatusCode::ResourceExhausted, $e->status);
            }
        }

        self::assertSame(1, self::frame((new MessageFramer(1))->parse("\x00\x00\x00\x00\x01a"))->length());

        $sixteenMebibytes = new MessageFramer(16_777_216);
        self::assertNull($sixteenMebibytes->parse("\x00\x01\x00\x00\x00"), 'the top length byte counts 2^24: exactly the limit, waiting for the message');

        try {
            $sixteenMebibytes->parse("\x00\x01\x00\x00\x01");
            self::fail('one byte above the limit was accepted');
        } catch (FrameException $e) {
            self::assertSame(GrpcCode::MessageTooLarge, $e->errorCode);
        }
    }

    #[Test]
    public function theCompressedFlagIsZeroOrOne(): void
    {
        foreach (["\x02\x00\x00\x00\x00", "\xFF\x00\x00\x00\x00"] as $prefix) {
            try {
                (new MessageFramer())->parse($prefix);
                self::fail('flag accepted');
            } catch (FrameException $e) {
                self::assertSame(GrpcCode::InvalidCompressedFlag, $e->errorCode);
                self::assertSame(StatusCode::Internal, $e->status);
            }
        }
    }

    #[Test]
    public function theLimitIsAtLeastOneByte(): void
    {
        try {
            new MessageFramer(0);
            self::fail('a zero limit was accepted');
        } catch (InvalidConfigurationException $e) {
            self::assertSame(GrpcCode::NonPositiveLimit, $e->errorCode);
        }
    }

    // ── grpc-timeout ─────────────────────────────────────────────────────

    /**
     * @return iterable<string, array{string, float}>
     */
    public static function timeouts(): iterable
    {
        yield 'hours' => ['2H', 7200.0];
        yield 'minutes' => ['3M', 180.0];
        yield 'seconds' => ['5S', 5.0];
        yield 'milliseconds' => ['250m', 0.25];
        yield 'microseconds' => ['1500u', 0.0015];
        yield 'nanoseconds round up to a microsecond' => ['1n', 0.000001];
        yield 'nanoseconds' => ['2000n', 0.000002];
        yield 'zero' => ['0S', 0.0];
        yield 'eight digits' => ['99999999H', 359_999_996_400.0];
    }

    #[Test]
    #[DataProvider('timeouts')]
    public function aTimeoutIsReadInItsUnit(string $value, float $seconds): void
    {
        self::assertEqualsWithDelta($seconds, Timeout::seconds($value), 1e-9);
    }

    #[Test]
    public function aTimeoutSetsTheDeadlineFromNow(): void
    {
        $now = new \DateTimeImmutable('2026-11-27 12:00:00.250000', new \DateTimeZone('UTC'));

        self::assertSame('2026-11-27 12:00:05.250000', Timeout::deadline('5S', $now)->format('Y-m-d H:i:s.u'));
        self::assertSame('2026-11-27 12:00:01.750000', Timeout::deadline('1500m', $now)->format('Y-m-d H:i:s.u'));
        self::assertSame('2026-11-27 12:00:00.250001', Timeout::deadline('1n', $now)->format('Y-m-d H:i:s.u'));
        self::assertSame('2026-11-27 14:00:00.250000', Timeout::deadline('2H', $now)->format('Y-m-d H:i:s.u'));
        self::assertSame('2026-11-27 12:00:01.249999', Timeout::deadline('999999u', $now)->format('Y-m-d H:i:s.u'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidTimeouts(): iterable
    {
        yield 'empty' => [''];
        yield 'no unit' => ['5'];
        yield 'no digits' => ['S'];
        yield 'nine digits' => ['123456789S'];
        yield 'unknown unit' => ['5s'];
        yield 'days' => ['1D'];
        yield 'negative' => ['-5S'];
        yield 'fraction' => ['1.5S'];
        yield 'space' => ['5 S'];
        yield 'trailing newline' => ["5S\n"];
    }

    #[Test]
    #[DataProvider('invalidTimeouts')]
    public function aTimeoutOutsideTheGrammarIsRefused(string $value): void
    {
        try {
            Timeout::seconds($value);
            self::fail('the timeout was accepted');
        } catch (InvalidMessageException $e) {
            self::assertSame(GrpcCode::InvalidTimeout, $e->errorCode);
        }
    }

    // ── grpc-message ─────────────────────────────────────────────────────

    #[Test]
    public function aStatusMessageIsPercentEncodedOutsidePrintableAscii(): void
    {
        self::assertSame('Pre%C3%A7o inv%C3%A1lido%0A', StatusMessage::encode("Preço inválido\n"));
        self::assertSame('50%25 done', StatusMessage::encode('50% done'));
        self::assertSame('%0D%0Agrpc-status: 0', StatusMessage::encode("\r\ngrpc-status: 0"));
        self::assertSame('%00%7F%FF', StatusMessage::encode("\x00\x7F\xFF"));
        self::assertSame(' !"#$&\'()~', StatusMessage::encode(' !"#$&\'()~'));
        self::assertSame('', StatusMessage::encode(''));
    }

    #[Test]
    public function aStatusMessageIsDecodedWithoutFailing(): void
    {
        self::assertSame("Preço inválido\n", StatusMessage::decode('Pre%C3%A7o inv%c3%a1lido%0A'));
        self::assertSame('50% done', StatusMessage::decode('50%25 done'));
        self::assertSame('bad %G1 and %4', StatusMessage::decode('bad %G1 and %4'), 'malformed escapes stay as they are');

        foreach (["any \x00 bytes \xFF", 'Pre%C3%A7o', '%%25%'] as $message) {
            self::assertSame($message, StatusMessage::decode(StatusMessage::encode($message)));
        }
    }

    /**
     * @param array{MessageFrame, int}|null $result
     */
    private static function frame(?array $result): MessageFrame
    {
        self::assertNotNull($result);

        return $result[0];
    }
}
