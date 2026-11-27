<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Tests\Unit\Exception;

use KaririCode\Grpc\Enum\StatusCode;
use KaririCode\Grpc\Exception\GrpcException;
use KaririCode\Grpc\Exception\ServiceException;
use PHPUnit\Framework\TestCase;

final class ExceptionTest extends TestCase
{
    // ── GrpcException ─────────────────────────────────────────────────

    public function testGrpcExceptionCarriesStatusCode(): void
    {
        $ex = new GrpcException(StatusCode::Internal, 'something broke');

        self::assertSame(StatusCode::Internal, $ex->statusCode);
        self::assertSame('something broke', $ex->getMessage());
        self::assertSame(13, $ex->getCode());
    }

    public function testGrpcExceptionUsesDescriptionWhenNoMessage(): void
    {
        $ex = new GrpcException(StatusCode::NotFound);

        self::assertSame(StatusCode::NotFound->description(), $ex->getMessage());
    }

    public function testGrpcExceptionChainsPrevious(): void
    {
        $cause = new \RuntimeException('root cause');
        $ex = new GrpcException(StatusCode::Unavailable, 'down', $cause);

        self::assertSame($cause, $ex->getPrevious());
    }

    // ── ServiceException ──────────────────────────────────────────────

    public function testInvalidArgumentFactory(): void
    {
        $ex = ServiceException::invalidArgument('email', 'must be valid');

        self::assertSame(StatusCode::InvalidArgument, $ex->statusCode);
        self::assertStringContainsString('email', $ex->getMessage());
        self::assertStringContainsString('must be valid', $ex->getMessage());
        self::assertSame([['field' => 'email', 'reason' => 'must be valid']], $ex->details);
    }

    public function testNotFoundFactory(): void
    {
        $ex = ServiceException::notFound('User', '42');

        self::assertSame(StatusCode::NotFound, $ex->statusCode);
        self::assertStringContainsString('User', $ex->getMessage());
        self::assertStringContainsString('42', $ex->getMessage());
        self::assertSame([['resource' => 'User', 'id' => '42']], $ex->details);
    }

    public function testAlreadyExistsFactory(): void
    {
        $ex = ServiceException::alreadyExists('Tenant', 'acme');

        self::assertSame(StatusCode::AlreadyExists, $ex->statusCode);
        self::assertStringContainsString('Tenant', $ex->getMessage());
    }

    public function testPermissionDeniedFactory(): void
    {
        $ex = ServiceException::permissionDenied('settings', 'update');

        self::assertSame(StatusCode::PermissionDenied, $ex->statusCode);
        self::assertSame([['resource' => 'settings', 'action' => 'update']], $ex->details);
    }

    public function testDeadlineExceededFactory(): void
    {
        $ex = ServiceException::deadlineExceeded(5.5);

        self::assertSame(StatusCode::DeadlineExceeded, $ex->statusCode);
        self::assertStringContainsString('5.500', $ex->getMessage());
    }

    public function testResourceExhaustedFactory(): void
    {
        $ex = ServiceException::resourceExhausted('memory', '512MB');

        self::assertSame(StatusCode::ResourceExhausted, $ex->statusCode);
        self::assertSame([['resource' => 'memory', 'limit' => '512MB']], $ex->details);
    }

    public function testInternalFactory(): void
    {
        $cause = new \RuntimeException('db down');
        $ex = ServiceException::internal('Database error', $cause);

        self::assertSame(StatusCode::Internal, $ex->statusCode);
        self::assertSame($cause, $ex->getPrevious());
        self::assertSame([], $ex->details);
    }

    public function testUnimplementedFactory(): void
    {
        $ex = ServiceException::unimplemented('UserService', 'Delete');

        self::assertSame(StatusCode::Unimplemented, $ex->statusCode);
        self::assertStringContainsString('UserService/Delete', $ex->getMessage());
    }

    public function testServiceExceptionIsGrpcException(): void
    {
        $ex = ServiceException::notFound('X', '1');

        self::assertInstanceOf(GrpcException::class, $ex);
    }
}
