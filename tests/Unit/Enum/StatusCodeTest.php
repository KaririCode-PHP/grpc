<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Tests\Unit\Enum;

use KaririCode\Grpc\Enum\StatusCode;
use PHPUnit\Framework\TestCase;

final class StatusCodeTest extends TestCase
{
    public function testAllCasesHaveUniqueValues(): void
    {
        $values = array_map(fn (StatusCode $c) => $c->numericCode(), StatusCode::cases());
        self::assertCount(count($values), array_unique($values));
    }

    public function testValueRange(): void
    {
        foreach (StatusCode::cases() as $case) {
            self::assertGreaterThanOrEqual(0, $case->numericCode());
            self::assertLessThanOrEqual(16, $case->numericCode());
        }
    }

    public function testOkIsZero(): void
    {
        self::assertSame(0, StatusCode::Ok->numericCode());
        self::assertTrue(StatusCode::Ok->isOk());
    }

    public function testNonOkIsNotOk(): void
    {
        foreach (StatusCode::cases() as $case) {
            if ($case === StatusCode::Ok) continue;
            self::assertFalse($case->isOk());
        }
    }

    public function testEveryCodeHasDescription(): void
    {
        foreach (StatusCode::cases() as $case) {
            self::assertNotEmpty($case->description());
        }
    }

    public function testEveryCodeHasHttpMapping(): void
    {
        foreach (StatusCode::cases() as $case) {
            $http = $case->httpStatusCode();
            self::assertGreaterThanOrEqual(200, $http);
            self::assertLessThanOrEqual(599, $http);
        }
    }

    public function testTryFromValueReturnsCase(): void
    {
        self::assertSame(StatusCode::Ok, StatusCode::tryFromCode(0));
        self::assertSame(StatusCode::NotFound, StatusCode::tryFromCode(5));
        self::assertSame(StatusCode::Unauthenticated, StatusCode::tryFromCode(16));
    }

    public function testTryFromValueReturnsNullForInvalid(): void
    {
        self::assertNull(StatusCode::tryFromCode(99));
        self::assertNull(StatusCode::tryFromCode(-1));
    }

    public function testSpecificHttpMappings(): void
    {
        self::assertSame(200, StatusCode::Ok->httpStatusCode());
        self::assertSame(400, StatusCode::InvalidArgument->httpStatusCode());
        self::assertSame(404, StatusCode::NotFound->httpStatusCode());
        self::assertSame(401, StatusCode::Unauthenticated->httpStatusCode());
        self::assertSame(403, StatusCode::PermissionDenied->httpStatusCode());
        self::assertSame(429, StatusCode::ResourceExhausted->httpStatusCode());
        self::assertSame(500, StatusCode::Internal->httpStatusCode());
        self::assertSame(501, StatusCode::Unimplemented->httpStatusCode());
        self::assertSame(503, StatusCode::Unavailable->httpStatusCode());
        self::assertSame(504, StatusCode::DeadlineExceeded->httpStatusCode());
    }
}
