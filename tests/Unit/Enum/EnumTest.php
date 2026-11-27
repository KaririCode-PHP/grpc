<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Tests\Unit\Enum;

use KaririCode\Grpc\Enum\CallType;
use KaririCode\Grpc\Enum\CompressionAlgorithm;
use KaririCode\Grpc\Enum\StatusCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(StatusCode::class)]
#[CoversClass(CallType::class)]
#[CoversClass(CompressionAlgorithm::class)]
final class EnumTest extends TestCase
{
    #[Test]
    public function statusCodesCarryTheirWireNumbers(): void
    {
        self::assertSame(range(0, 16), array_map(static fn(StatusCode $code): int => $code->value, StatusCode::cases()));
        self::assertSame(StatusCode::Unauthenticated, StatusCode::tryFrom(16));
        self::assertNull(StatusCode::tryFrom(17));
        self::assertTrue(StatusCode::Ok->isOk());
        self::assertFalse(StatusCode::Cancelled->isOk());
    }

    #[Test]
    public function statusCodesMapToTheGoogleRpcHttpStatuses(): void
    {
        $expected = [200, 499, 500, 400, 504, 404, 409, 403, 429, 400, 409, 400, 501, 500, 503, 500, 401];

        self::assertSame($expected, array_map(static fn(StatusCode $code): int => $code->httpStatusCode(), StatusCode::cases()));
    }

    #[Test]
    public function everyStatusCodeIsDescribed(): void
    {
        $descriptions = array_map(static fn(StatusCode $code): string => $code->description(), StatusCode::cases());

        self::assertCount(17, array_unique($descriptions));
        self::assertSame('Internal error', StatusCode::Internal->description());
        self::assertSame('The deadline expired before the operation could complete', StatusCode::DeadlineExceeded->description());
    }

    #[Test]
    public function callTypesAreRouterMethodsAndCountMessages(): void
    {
        self::assertSame(['UNARY', 'SERVER_STREAM', 'CLIENT_STREAM', 'BIDI_STREAM'], array_map(static fn(CallType $type): string => $type->routerMethod(), CallType::cases()));
        self::assertSame([true, true, false, false], array_map(static fn(CallType $type): bool => $type->isSingleRequest(), CallType::cases()));
        self::assertSame([true, false, true, false], array_map(static fn(CallType $type): bool => $type->isSingleResponse(), CallType::cases()));
    }

    #[Test]
    public function compressionAlgorithmsReadTheGrpcEncodingHeader(): void
    {
        self::assertSame(CompressionAlgorithm::Identity, CompressionAlgorithm::fromHeader(''));
        self::assertSame(CompressionAlgorithm::Identity, CompressionAlgorithm::fromHeader(' identity '));
        self::assertSame(CompressionAlgorithm::Gzip, CompressionAlgorithm::fromHeader("GZip\t"));
        self::assertSame(CompressionAlgorithm::Deflate, CompressionAlgorithm::fromHeader('deflate'));
        self::assertNull(CompressionAlgorithm::fromHeader('snappy'));
        self::assertNull(CompressionAlgorithm::fromHeader("\ngzip"), 'only spaces and tabs surround a header value');
        self::assertSame([false, true, true], array_map(static fn(CompressionAlgorithm $algorithm): bool => $algorithm->requiresZlib(), CompressionAlgorithm::cases()));
    }
}
