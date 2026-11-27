<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Tests\Unit\Message;

use KaririCode\Grpc\Enum\StatusCode;
use KaririCode\Grpc\Message\Status;
use PHPUnit\Framework\TestCase;

final class StatusTest extends TestCase
{
    public function testOkFactory(): void
    {
        $status = Status::ok();
        self::assertTrue($status->isOk());
        self::assertSame(StatusCode::Ok, $status->code);
    }

    public function testFactoriesReturnCorrectCodes(): void
    {
        self::assertSame(StatusCode::Cancelled, Status::cancelled()->code);
        self::assertSame(StatusCode::InvalidArgument, Status::invalidArgument('bad')->code);
        self::assertSame(StatusCode::NotFound, Status::notFound('user 1')->code);
        self::assertSame(StatusCode::PermissionDenied, Status::permissionDenied()->code);
        self::assertSame(StatusCode::Unauthenticated, Status::unauthenticated()->code);
        self::assertSame(StatusCode::Internal, Status::internal()->code);
        self::assertSame(StatusCode::Unavailable, Status::unavailable()->code);
        self::assertSame(StatusCode::DeadlineExceeded, Status::deadlineExceeded()->code);
    }

    public function testUnimplementedFactory(): void
    {
        $status = Status::unimplemented('UserService', 'GetUser');
        self::assertSame(StatusCode::Unimplemented, $status->code);
        self::assertStringContainsString('UserService/GetUser', $status->message);
    }

    public function testCustomMessage(): void
    {
        $status = new Status(StatusCode::Internal, 'disk full');
        self::assertSame('disk full', $status->message);
    }

    public function testDetails(): void
    {
        $details = [['field' => 'email', 'reason' => 'invalid format']];
        $status = new Status(StatusCode::InvalidArgument, 'bad request', $details);
        self::assertSame($details, $status->details);
    }
}
