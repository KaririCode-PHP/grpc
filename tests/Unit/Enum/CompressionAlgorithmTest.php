<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Tests\Unit\Enum;

use KaririCode\Grpc\Enum\CompressionAlgorithm;
use PHPUnit\Framework\TestCase;

final class CompressionAlgorithmTest extends TestCase
{
    public function testWireValues(): void
    {
        self::assertSame('identity', CompressionAlgorithm::Identity->wireValue());
        self::assertSame('gzip', CompressionAlgorithm::Gzip->wireValue());
        self::assertSame('deflate', CompressionAlgorithm::Deflate->wireValue());
    }

    public function testRequiresZlib(): void
    {
        self::assertFalse(CompressionAlgorithm::Identity->requiresZlib());
        self::assertTrue(CompressionAlgorithm::Gzip->requiresZlib());
        self::assertTrue(CompressionAlgorithm::Deflate->requiresZlib());
    }

    public function testFromWireValue(): void
    {
        self::assertSame(CompressionAlgorithm::Identity, CompressionAlgorithm::fromWireValue('identity'));
        self::assertSame(CompressionAlgorithm::Identity, CompressionAlgorithm::fromWireValue(''));
        self::assertSame(CompressionAlgorithm::Gzip, CompressionAlgorithm::fromWireValue('gzip'));
        self::assertNull(CompressionAlgorithm::fromWireValue('snappy'));
    }
}
