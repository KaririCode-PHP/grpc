<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Tests\Unit\Integration;

use KaririCode\Grpc\Enum\CallType;
use KaririCode\Grpc\Exception\GrpcCode;
use KaririCode\Grpc\Exception\UnsupportedRequestException;
use KaririCode\Grpc\Integration\GrpcNormalizer;
use KaririCode\Grpc\Message\GrpcRequest;
use KaririCode\Router\Core\RouteRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GrpcNormalizer::class)]
final class GrpcNormalizerTest extends TestCase
{
    #[Test]
    public function aCallIsRoutedByCallTypeAndPath(): void
    {
        $routes = (new RouteRegistry())
            ->route([CallType::Unary->routerMethod()], '/shop.Cart/AddItem', 'add')
            ->route([CallType::ServerStreaming->routerMethod()], '/shop.Cart/Watch', 'watch');
        $normalizer = new GrpcNormalizer();
        $unary = new GrpcRequest('shop.Cart', 'AddItem');
        $stream = new GrpcRequest('shop.Cart', 'Watch', callType: CallType::ServerStreaming);

        $request = $normalizer->normalize($unary);

        self::assertTrue($normalizer->canNormalize($unary));
        self::assertFalse($normalizer->canNormalize(new \stdClass()));
        self::assertSame('UNARY', $request->method);
        self::assertSame('/shop.Cart/AddItem', $request->uri);
        self::assertSame($unary, $request->original);
        self::assertSame('add', $routes->match($request->method, $request->uri)->handler());
        self::assertSame('watch', $routes->match($normalizer->normalize($stream)->method, $normalizer->normalize($stream)->uri)->handler());
    }

    #[Test]
    public function normalizingSomethingElseIsAWiringError(): void
    {
        try {
            (new GrpcNormalizer())->normalize(new \stdClass());
            self::fail('a non-request was normalised');
        } catch (UnsupportedRequestException $e) {
            self::assertSame(GrpcCode::NotAGrpcRequest, $e->errorCode);
        }
    }
}
