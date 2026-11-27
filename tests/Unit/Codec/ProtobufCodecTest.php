<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Tests\Unit\Codec;

use KaririCode\Grpc\Codec\ProtobufCodec;
use PHPUnit\Framework\TestCase;

final class ProtobufCodecTest extends TestCase
{
    private ProtobufCodec $codec;

    protected function setUp(): void
    {
        $this->codec = new ProtobufCodec();
    }

    public function testName(): void
    {
        self::assertSame('proto', $this->codec->name());
    }

    public function testEncodeDecodeVarint(): void
    {
        $message = ['field1' => 150];
        $encoded = $this->codec->encode($message);
        $decoded = $this->codec->decode($encoded);

        self::assertSame(150, $decoded[1]);
    }

    public function testEncodeDecodeString(): void
    {
        $message = ['name' => 'testing'];
        $encoded = $this->codec->encode($message);
        $decoded = $this->codec->decode($encoded);

        self::assertSame('testing', $decoded[1]);
    }

    public function testEncodeDecodeBool(): void
    {
        $message = ['active' => true];
        $encoded = $this->codec->encode($message);
        $decoded = $this->codec->decode($encoded);

        self::assertSame(1, $decoded[1]);
    }

    public function testEncodeDecodeMultipleFields(): void
    {
        $message = ['id' => 42, 'name' => 'test'];
        $encoded = $this->codec->encode($message);
        $decoded = $this->codec->decode($encoded);

        self::assertSame(42, $decoded[1]);
        self::assertSame('test', $decoded[2]);
    }

    public function testDecodeEmptyReturnsEmpty(): void
    {
        self::assertSame([], $this->codec->decode(''));
    }
}
