<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Tests\Unit\Message;

use KaririCode\Grpc\Enum\StatusCode;
use KaririCode\Grpc\Message\GrpcResponse;
use KaririCode\Grpc\Message\Metadata;
use KaririCode\Grpc\Message\Status;
use PHPUnit\Framework\TestCase;

final class GrpcResponseTest extends TestCase
{
    public function testDefaultIsOk(): void
    {
        $resp = new GrpcResponse();
        self::assertTrue($resp->isOk());
    }

    public function testOkFactory(): void
    {
        $resp = GrpcResponse::ok(['id' => 42]);
        self::assertTrue($resp->isOk());
        self::assertSame(['id' => 42], $resp->payload);
    }

    public function testErrorFactory(): void
    {
        $resp = GrpcResponse::error(Status::notFound('user 1'));
        self::assertFalse($resp->isOk());
        self::assertSame(StatusCode::NotFound, $resp->status->code);
    }

    public function testUnimplementedFactory(): void
    {
        $resp = GrpcResponse::unimplemented('Svc', 'Method');
        self::assertSame(StatusCode::Unimplemented, $resp->status->code);
    }

    public function testWithPayloadImmutable(): void
    {
        $resp = GrpcResponse::ok(['a' => 1]);
        $new = $resp->withPayload(['b' => 2]);

        self::assertSame(['a' => 1], $resp->payload);
        self::assertSame(['b' => 2], $new->payload);
    }

    public function testWithStatusImmutable(): void
    {
        $resp = GrpcResponse::ok();
        $new = $resp->withStatus(Status::internal());

        self::assertTrue($resp->isOk());
        self::assertFalse($new->isOk());
    }

    public function testWithHeaders(): void
    {
        $resp = new GrpcResponse();
        $new = $resp->withHeaders(new Metadata(['x-request-id' => '123']));

        self::assertTrue($resp->headers->isEmpty());
        self::assertSame('123', $new->headers->get('x-request-id'));
    }

    public function testWithTrailers(): void
    {
        $resp = new GrpcResponse();
        $new = $resp->withTrailers(new Metadata(['grpc-status' => '0']));

        self::assertSame('0', $new->trailers->get('grpc-status'));
    }
}
