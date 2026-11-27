<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Tests\Unit\Message;

use KaririCode\Grpc\Enum\CallType;
use KaririCode\Grpc\Message\GrpcRequest;
use KaririCode\Grpc\Message\Metadata;
use PHPUnit\Framework\TestCase;

final class GrpcRequestTest extends TestCase
{
    public function testPath(): void
    {
        $req = new GrpcRequest('myapp.v1.UserService', 'GetUser');
        self::assertSame('/myapp.v1.UserService/GetUser', $req->path());
    }

    public function testDefaults(): void
    {
        $req = new GrpcRequest('svc', 'Method');
        self::assertSame([], $req->payload);
        self::assertTrue($req->metadata->isEmpty());
        self::assertSame(CallType::Unary, $req->callType);
        self::assertNull($req->deadline);
        self::assertFalse($req->hasDeadline());
    }

    public function testWithPayloadImmutable(): void
    {
        $req = new GrpcRequest('svc', 'M', ['a' => 1]);
        $new = $req->withPayload(['b' => 2]);

        self::assertSame(['a' => 1], $req->payload);
        self::assertSame(['b' => 2], $new->payload);
    }

    public function testWithMetadataImmutable(): void
    {
        $req = new GrpcRequest('svc', 'M');
        $md = new Metadata(['auth' => 'token']);
        $new = $req->withMetadata($md);

        self::assertTrue($req->metadata->isEmpty());
        self::assertSame('token', $new->metadata->get('auth'));
    }

    public function testDeadline(): void
    {
        $future = microtime(true) + 60;
        $req = new GrpcRequest('svc', 'M', deadline: $future);

        self::assertTrue($req->hasDeadline());
        self::assertFalse($req->isDeadlineExceeded());
    }

    public function testDeadlineExceeded(): void
    {
        $past = microtime(true) - 1;
        $req = new GrpcRequest('svc', 'M', deadline: $past);

        self::assertTrue($req->isDeadlineExceeded());
    }

    public function testWithDeadlineImmutable(): void
    {
        $req = new GrpcRequest('svc', 'M');
        $new = $req->withDeadline(microtime(true) + 30);

        self::assertFalse($req->hasDeadline());
        self::assertTrue($new->hasDeadline());
    }
}
