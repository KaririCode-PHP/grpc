<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Tests\Unit\Codec;

use KaririCode\Grpc\Codec\JsonCodec;
use PHPUnit\Framework\TestCase;

final class JsonCodecTest extends TestCase
{
    private JsonCodec $codec;

    protected function setUp(): void
    {
        $this->codec = new JsonCodec();
    }

    public function testName(): void
    {
        self::assertSame('json', $this->codec->name());
    }

    public function testEncodeDecodeRoundTrip(): void
    {
        $message = ['name' => 'Alice', 'age' => 30, 'active' => true];
        $encoded = $this->codec->encode($message);
        $decoded = $this->codec->decode($encoded);

        self::assertSame($message, $decoded);
    }

    public function testDecodeEmptyString(): void
    {
        self::assertSame([], $this->codec->decode(''));
    }

    public function testEncodePreservesUnicode(): void
    {
        $message = ['greeting' => 'Olá, São Paulo!'];
        $encoded = $this->codec->encode($message);

        self::assertStringContainsString('Olá', $encoded);
        self::assertStringNotContainsString('\\u', $encoded);
    }

    public function testEncodePreservesZeroFraction(): void
    {
        $message = ['price' => 10.0];
        $encoded = $this->codec->encode($message);

        self::assertStringContainsString('10.0', $encoded);
    }

    public function testDecodeInvalidJsonThrows(): void
    {
        $this->expectException(\JsonException::class);
        $this->codec->decode('{invalid');
    }

    public function testDecodeScalarReturnsEmpty(): void
    {
        self::assertSame([], $this->codec->decode('"scalar"'));
    }

    public function testEncodeNestedStructure(): void
    {
        $message = ['user' => ['name' => 'Bob', 'roles' => ['admin', 'editor']]];
        $decoded = $this->codec->decode($this->codec->encode($message));

        self::assertSame('Bob', $decoded['user']['name']);
        self::assertSame(['admin', 'editor'], $decoded['user']['roles']);
    }
}
