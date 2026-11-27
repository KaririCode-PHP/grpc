<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Tests\Unit\Message;

use KaririCode\Grpc\Message\Metadata;
use PHPUnit\Framework\TestCase;

final class MetadataTest extends TestCase
{
    public function testEmptyMetadata(): void
    {
        $md = new Metadata();
        self::assertTrue($md->isEmpty());
        self::assertSame([], $md->all());
    }

    public function testGetAndHas(): void
    {
        $md = new Metadata(['Authorization' => 'Bearer token']);
        self::assertTrue($md->has('authorization'));
        self::assertSame('Bearer token', $md->get('Authorization'));
        self::assertNull($md->get('nonexistent'));
        self::assertSame('fallback', $md->get('nonexistent', 'fallback'));
    }

    public function testCaseInsensitiveKeys(): void
    {
        $md = new Metadata(['Content-Type' => 'application/grpc']);
        self::assertSame('application/grpc', $md->get('content-type'));
        self::assertSame('application/grpc', $md->get('CONTENT-TYPE'));
    }

    public function testMultipleValues(): void
    {
        $md = new Metadata(['x-custom' => ['a', 'b', 'c']]);
        self::assertSame(['a', 'b', 'c'], $md->getAll('x-custom'));
        self::assertSame('a', $md->get('x-custom'));
    }

    public function testWithValueImmutable(): void
    {
        $md = new Metadata(['key' => 'old']);
        $new = $md->withValue('key', 'new');

        self::assertSame('old', $md->get('key'));
        self::assertSame('new', $new->get('key'));
    }

    public function testWithAddedValue(): void
    {
        $md = new Metadata(['key' => 'a']);
        $new = $md->withAddedValue('key', 'b');

        self::assertSame(['a', 'b'], $new->getAll('key'));
    }

    public function testWithoutKey(): void
    {
        $md = new Metadata(['keep' => 'yes', 'remove' => 'no']);
        $new = $md->withoutKey('remove');

        self::assertTrue($new->has('keep'));
        self::assertFalse($new->has('remove'));
    }

    public function testIsBinaryKey(): void
    {
        self::assertTrue(Metadata::isBinaryKey('token-bin'));
        self::assertTrue(Metadata::isBinaryKey('Token-BIN'));
        self::assertFalse(Metadata::isBinaryKey('authorization'));
    }
}
