<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Tests\Unit\Codec;

use KaririCode\Grpc\Codec\JsonCodec;
use KaririCode\Grpc\Exception\GrpcCode;
use KaririCode\Grpc\Exception\InvalidMessageException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonCodec::class)]
final class JsonCodecTest extends TestCase
{
    #[Test]
    public function anObjectRoundTrips(): void
    {
        $codec = new JsonCodec();
        $message = ['sku' => 'A/1', 'name' => 'Café', 'quantity' => 2, 'price' => 10.0, 'tags' => ['x'], 'meta' => ['a' => null]];

        $encoded = $codec->encode($message);

        self::assertSame('json', $codec->name());
        self::assertSame('{"sku":"A/1","name":"Café","quantity":2,"price":10.0,"tags":["x"],"meta":{"a":null}}', $encoded);
        self::assertSame($message, $codec->decode($encoded));
    }

    #[Test]
    public function nestingStopsAtTheJsonDefaultDepth(): void
    {
        $nested = static fn(int $levels): string => str_repeat('{"a":', $levels) . '1' . str_repeat('}', $levels);

        self::assertNotSame([], (new JsonCodec())->decode($nested(511)));

        try {
            (new JsonCodec())->decode($nested(512));
            self::fail('512 nested objects were decoded');
        } catch (InvalidMessageException $e) {
            self::assertSame(GrpcCode::MalformedJson, $e->errorCode);
        }
    }

    #[Test]
    public function anEmptyMessageIsAnEmptyArray(): void
    {
        $codec = new JsonCodec();

        self::assertSame([], $codec->decode(''));
        self::assertSame([], $codec->decode('{}'));
        self::assertSame([], $codec->decode(" \r\n\t{ }\n"));
        self::assertSame('[]', $codec->encode([]), 'PHP cannot tell an empty array from an empty object');
    }

    /**
     * @return iterable<string, array{string, GrpcCode}>
     */
    public static function invalidMessages(): iterable
    {
        yield 'truncated' => ['{"a":', GrpcCode::MalformedJson];
        yield 'not JSON' => ['hello', GrpcCode::MalformedJson];
        yield 'invalid UTF-8' => ["{\"a\":\"\xC3\"}", GrpcCode::MalformedJson];
        yield 'too deep' => [str_repeat('{"a":', 600) . '1' . str_repeat('}', 600), GrpcCode::MalformedJson];
        yield 'array' => ['[1,2]', GrpcCode::JsonNotAnObject];
        yield 'empty array' => ['[]', GrpcCode::JsonNotAnObject];
        yield 'string' => ['"text"', GrpcCode::JsonNotAnObject];
        yield 'number' => ['42', GrpcCode::JsonNotAnObject];
        yield 'null' => ['null', GrpcCode::JsonNotAnObject];
    }

    #[Test]
    #[DataProvider('invalidMessages')]
    public function onlyAJsonObjectIsAMessage(string $data, GrpcCode $code): void
    {
        try {
            (new JsonCodec())->decode($data);
            self::fail('the message was decoded');
        } catch (InvalidMessageException $e) {
            self::assertSame($code, $e->errorCode);
        }
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>}>
     */
    public static function unencodable(): iterable
    {
        yield 'invalid UTF-8' => [['a' => "\xC3"]];
        yield 'infinity' => [['a' => INF]];
        yield 'not a number' => [['a' => NAN]];
    }

    /**
     * @param array<array-key, mixed> $message
     */
    #[Test]
    #[DataProvider('unencodable')]
    public function dataJsonCannotHoldIsRefused(array $message): void
    {
        try {
            (new JsonCodec())->encode($message);
            self::fail('the message was encoded');
        } catch (InvalidMessageException $e) {
            self::assertSame(GrpcCode::UnencodableJson, $e->errorCode);
        }
    }
}
