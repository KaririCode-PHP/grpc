<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Tests\Unit\Integration;

use KaririCode\Grpc\Enum\CallType;
use KaririCode\Grpc\Integration\GrpcNormalizer;
use KaririCode\Grpc\Message\GrpcRequest;
use PHPUnit\Framework\TestCase;

final class GrpcNormalizerTest extends TestCase
{
    private GrpcNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new GrpcNormalizer();
    }

    public function testCanNormalizeGrpcRequest(): void
    {
        self::assertTrue($this->normalizer->canNormalize(new GrpcRequest('svc', 'M')));
    }

    public function testCannotNormalizeOtherObjects(): void
    {
        self::assertFalse($this->normalizer->canNormalize(new \stdClass()));
    }

    public function testNormalizeUnary(): void
    {
        $request = new GrpcRequest('myapp.v1.UserService', 'GetUser');
        $normalized = $this->normalizer->normalize($request);

        self::assertSame('UNARY', $normalized->method);
        self::assertSame('/myapp.v1.UserService/GetUser', $normalized->uri);
        self::assertSame($request, $normalized->original);
    }

    public function testNormalizeServerStream(): void
    {
        $request = new GrpcRequest('svc', 'List', callType: CallType::ServerStreaming);
        $normalized = $this->normalizer->normalize($request);

        self::assertSame('SERVER_STREAM', $normalized->method);
    }

    public function testNormalizeClientStream(): void
    {
        $request = new GrpcRequest('svc', 'Upload', callType: CallType::ClientStreaming);
        $normalized = $this->normalizer->normalize($request);

        self::assertSame('CLIENT_STREAM', $normalized->method);
    }

    public function testNormalizeBidiStream(): void
    {
        $request = new GrpcRequest('svc', 'Chat', callType: CallType::BidiStreaming);
        $normalized = $this->normalizer->normalize($request);

        self::assertSame('BIDI_STREAM', $normalized->method);
    }
}
