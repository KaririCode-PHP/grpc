<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Tests\Unit\Server;

use KaririCode\Grpc\Enum\CallType;
use KaririCode\Grpc\Server\MethodDescriptor;
use PHPUnit\Framework\TestCase;

final class MethodDescriptorTest extends TestCase
{
    public function testFullPath(): void
    {
        $desc = new MethodDescriptor('myapp.v1.UserService', 'GetUser');
        self::assertSame('/myapp.v1.UserService/GetUser', $desc->fullPath);
    }

    public function testDefaultCallType(): void
    {
        $desc = new MethodDescriptor('svc', 'Method');
        self::assertSame(CallType::Unary, $desc->callType);
    }

    public function testRouterMethod(): void
    {
        $unary = new MethodDescriptor('svc', 'M', CallType::Unary);
        $stream = new MethodDescriptor('svc', 'M', CallType::ServerStreaming);

        self::assertSame('UNARY', $unary->routerMethod());
        self::assertSame('SERVER_STREAM', $stream->routerMethod());
    }

    public function testReadonlyProperties(): void
    {
        $desc = new MethodDescriptor('pkg.Service', 'List', CallType::BidiStreaming);

        self::assertSame('pkg.Service', $desc->serviceName);
        self::assertSame('List', $desc->methodName);
        self::assertSame(CallType::BidiStreaming, $desc->callType);
    }
}
