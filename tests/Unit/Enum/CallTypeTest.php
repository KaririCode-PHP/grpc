<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Tests\Unit\Enum;

use KaririCode\Grpc\Enum\CallType;
use PHPUnit\Framework\TestCase;

final class CallTypeTest extends TestCase
{
    public function testRouterMethods(): void
    {
        self::assertSame('UNARY', CallType::Unary->routerMethod());
        self::assertSame('SERVER_STREAM', CallType::ServerStreaming->routerMethod());
        self::assertSame('CLIENT_STREAM', CallType::ClientStreaming->routerMethod());
        self::assertSame('BIDI_STREAM', CallType::BidiStreaming->routerMethod());
    }

    public function testSingleRequestCardinality(): void
    {
        self::assertTrue(CallType::Unary->isSingleRequest());
        self::assertTrue(CallType::ServerStreaming->isSingleRequest());
        self::assertFalse(CallType::ClientStreaming->isSingleRequest());
        self::assertFalse(CallType::BidiStreaming->isSingleRequest());
    }

    public function testSingleResponseCardinality(): void
    {
        self::assertTrue(CallType::Unary->isSingleResponse());
        self::assertTrue(CallType::ClientStreaming->isSingleResponse());
        self::assertFalse(CallType::ServerStreaming->isSingleResponse());
        self::assertFalse(CallType::BidiStreaming->isSingleResponse());
    }

    public function testFromRouterMethod(): void
    {
        self::assertSame(CallType::Unary, CallType::fromRouterMethod('UNARY'));
        self::assertSame(CallType::Unary, CallType::fromRouterMethod('unary'));
        self::assertSame(CallType::ServerStreaming, CallType::fromRouterMethod('SERVER_STREAM'));
        self::assertNull(CallType::fromRouterMethod('INVALID'));
    }
}
