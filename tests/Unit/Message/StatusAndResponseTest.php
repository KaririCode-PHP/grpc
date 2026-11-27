<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Tests\Unit\Message;

use KaririCode\Grpc\Enum\StatusCode;
use KaririCode\Grpc\Message\GrpcResponse;
use KaririCode\Grpc\Message\Metadata;
use KaririCode\Grpc\Message\Status;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Status::class)]
#[CoversClass(GrpcResponse::class)]
final class StatusAndResponseTest extends TestCase
{
    /**
     * @return iterable<string, array{\Closure(): Status, StatusCode, string}>
     */
    public static function factories(): iterable
    {
        yield 'ok' => [static fn(): Status => Status::ok(), StatusCode::Ok, ''];
        yield 'cancelled' => [static fn(): Status => Status::cancelled(), StatusCode::Cancelled, 'Cancelled'];
        yield 'invalid argument' => [static fn(): Status => Status::invalidArgument(), StatusCode::InvalidArgument, 'Invalid argument'];
        yield 'not found' => [static fn(): Status => Status::notFound(), StatusCode::NotFound, 'Not found'];
        yield 'already exists' => [static fn(): Status => Status::alreadyExists(), StatusCode::AlreadyExists, 'Already exists'];
        yield 'permission denied' => [static fn(): Status => Status::permissionDenied(), StatusCode::PermissionDenied, 'Permission denied'];
        yield 'resource exhausted' => [static fn(): Status => Status::resourceExhausted(), StatusCode::ResourceExhausted, 'Resource exhausted'];
        yield 'unauthenticated' => [static fn(): Status => Status::unauthenticated(), StatusCode::Unauthenticated, 'Unauthenticated'];
        yield 'unimplemented' => [static fn(): Status => Status::unimplemented(), StatusCode::Unimplemented, 'Not implemented'];
        yield 'internal' => [static fn(): Status => Status::internal(), StatusCode::Internal, 'Internal error'];
        yield 'unavailable' => [static fn(): Status => Status::unavailable(), StatusCode::Unavailable, 'Service unavailable'];
        yield 'deadline exceeded' => [static fn(): Status => Status::deadlineExceeded(), StatusCode::DeadlineExceeded, 'Deadline exceeded'];
    }

    /**
     * @param \Closure(): Status $factory
     */
    #[Test]
    #[DataProvider('factories')]
    public function factoriesSetTheCodeAndAFixedMessage(\Closure $factory, StatusCode $code, string $message): void
    {
        $status = $factory();

        self::assertSame($code, $status->code);
        self::assertSame($message, $status->message);
        self::assertSame([], $status->details);
        self::assertSame($code === StatusCode::Ok, $status->isOk());
    }

    #[Test]
    public function theApplicationMayWriteItsOwnMessageAndDetails(): void
    {
        $status = new Status(StatusCode::FailedPrecondition, 'Cart is checked out', [['cart' => 'c-7']]);

        self::assertSame('No such cart', Status::notFound('No such cart')->message);
        self::assertSame([['cart' => 'c-7']], $status->details);
        self::assertSame('Cart is checked out', $status->message);
    }

    #[Test]
    public function responsesCarryMessageStatusAndMetadata(): void
    {
        $headers = new Metadata(['x-region' => 'br']);
        $trailers = new Metadata(['x-cost' => '3']);

        $ok = GrpcResponse::ok('bytes', $headers);
        $error = GrpcResponse::error(Status::notFound(), $trailers);
        $default = new GrpcResponse();

        self::assertTrue($ok->isOk());
        self::assertSame('bytes', $ok->message);
        self::assertSame($headers, $ok->headers);
        self::assertFalse($error->isOk());
        self::assertSame('', $error->message);
        self::assertSame($trailers, $error->trailers);
        self::assertTrue($error->headers->isEmpty());
        self::assertTrue($default->isOk());
        self::assertSame('', $default->message);
    }

    #[Test]
    public function withersReturnNewResponses(): void
    {
        $response = GrpcResponse::ok('a');
        $headers = new Metadata(['h' => '1']);
        $trailers = new Metadata(['t' => '2']);

        $changed = $response->withMessage('b')->withStatus(new Status(StatusCode::Aborted))->withHeaders($headers)->withTrailers($trailers);

        self::assertSame('b', $changed->message);
        self::assertSame(StatusCode::Aborted, $changed->status->code);
        self::assertSame($headers, $changed->headers);
        self::assertSame($trailers, $changed->trailers);
        self::assertSame('a', $response->message);
        self::assertTrue($response->isOk());
    }
}
