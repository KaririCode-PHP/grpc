<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Tests\Unit\Message;

use KaririCode\Grpc\Exception\GrpcCode;
use KaririCode\Grpc\Exception\InvalidMessageException;
use KaririCode\Grpc\Message\Metadata;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Metadata::class)]
#[CoversClass(InvalidMessageException::class)]
final class MetadataTest extends TestCase
{
    #[Test]
    public function keysAreLowerCasedAndValuesKeptInOrder(): void
    {
        $metadata = new Metadata(['X-Request-Id' => 'r-42', 'tag' => ['a', 'b'], 7 => 'seven']);

        self::assertSame('r-42', $metadata->get('x-request-id'));
        self::assertSame('r-42', $metadata->get('X-REQUEST-ID'));
        self::assertSame(['a', 'b'], $metadata->getAll('Tag'));
        self::assertSame('a', $metadata->get('tag'));
        self::assertSame('seven', $metadata->get('7'));
        self::assertTrue($metadata->has('TAG'));
        self::assertSame(['x-request-id' => ['r-42'], 'tag' => ['a', 'b'], '7' => ['seven']], $metadata->all());
        self::assertFalse($metadata->isEmpty());
    }

    #[Test]
    public function missingKeysHaveDefaultsAndNoValues(): void
    {
        $metadata = new Metadata(['empty-list' => []]);

        self::assertNull($metadata->get('absent'));
        self::assertSame('fallback', $metadata->get('absent', 'fallback'));
        self::assertSame([], $metadata->getAll('absent'));
        self::assertFalse($metadata->has('empty-list'), 'a key without values is not kept');
        self::assertTrue($metadata->isEmpty());
        self::assertSame('', (new Metadata(['blank' => '']))->get('blank', 'fallback'), 'an empty value is a value');
    }

    #[Test]
    public function withersReturnNewMetadata(): void
    {
        $metadata = new Metadata(['tag' => ['a', 'b']]);

        $replaced = $metadata->withValue('Tag', 'c');
        $added = $metadata->withAddedValue('TAG', 'c');
        $new = $metadata->withAddedValue('fresh', 'x');
        $removed = $metadata->withoutKey('TAG');

        self::assertSame(['c'], $replaced->getAll('tag'));
        self::assertSame(['a', 'b', 'c'], $added->getAll('tag'));
        self::assertSame(['x'], $new->getAll('fresh'));
        self::assertFalse($removed->has('tag'));
        self::assertSame(['a', 'b'], $metadata->getAll('tag'), 'the original is unchanged');
    }

    #[Test]
    public function binaryKeysHoldAnyBytes(): void
    {
        $bytes = "\x00\xFF\r\n";
        $metadata = new Metadata(['trace-BIN' => $bytes]);

        self::assertSame($bytes, $metadata->get('trace-bin'));
        self::assertTrue(Metadata::isBinaryKey('Trace-Bin'));
        self::assertFalse(Metadata::isBinaryKey('binary'));
        self::assertFalse(Metadata::isBinaryKey('trace-bin-x'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidKeys(): iterable
    {
        yield 'empty' => [''];
        yield 'space' => ['x request'];
        yield 'colon (pseudo-header)' => [':path'];
        yield 'CR LF' => ["x\r\nInjected"];
        yield 'slash' => ['a/b'];
        yield 'non-ASCII' => ['chave-ç'];
    }

    #[Test]
    #[DataProvider('invalidKeys')]
    public function keysOutsideTheGrammarAreRefused(string $key): void
    {
        self::assertRefused(GrpcCode::InvalidMetadataKey, static fn() => new Metadata([$key => 'v']));
    }

    #[Test]
    public function grpcKeysBelongToTheProtocol(): void
    {
        foreach (['grpc-status', 'GRPC-Message', 'grpc-timeout'] as $key) {
            self::assertRefused(GrpcCode::ReservedMetadataKey, static fn() => new Metadata([$key => '0']));
        }

        self::assertRefused(GrpcCode::ReservedMetadataKey, static fn() => (new Metadata())->withValue('grpc-status', '0'));
        self::assertSame('ok', (new Metadata(['x-grpc-like' => 'ok']))->get('x-grpc-like'), 'only the prefix is reserved');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidAsciiValues(): iterable
    {
        yield 'CR LF' => ["a\r\nInjected: 1"];
        yield 'NUL' => ["a\x00b"];
        yield 'tab' => ["a\tb"];
        yield 'DEL' => ["a\x7Fb"];
        yield 'UTF-8' => ['preço'];
    }

    #[Test]
    #[DataProvider('invalidAsciiValues')]
    public function asciiValuesHoldPrintableAsciiOnly(string $value): void
    {
        self::assertRefused(GrpcCode::InvalidMetadataValue, static fn() => new Metadata(['note' => $value]));
        self::assertRefused(GrpcCode::InvalidMetadataValue, static fn() => (new Metadata())->withAddedValue('note', $value));
        self::assertSame($value, (new Metadata(['note-bin' => $value]))->get('note-bin'));
    }

    #[Test]
    public function printableAsciiIsAccepted(): void
    {
        $value = implode('', array_map('chr', range(0x20, 0x7E)));

        self::assertSame($value, (new Metadata(['all' => $value]))->get('all'));
    }

    private static function assertRefused(GrpcCode $code, \Closure $build): void
    {
        try {
            $build();
            self::fail('the metadata was accepted');
        } catch (InvalidMessageException $e) {
            self::assertSame($code, $e->errorCode);
        }
    }
}
