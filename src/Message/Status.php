<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Message;

use KaririCode\Grpc\Enum\StatusCode;

/**
 * The outcome of a call as the client receives it: the status code, a
 * message for the developer reading the error, and structured details for
 * the application. The transport sends the code as grpc-status and the
 * message percent-encoded as grpc-message (Wire\StatusMessage); encoding
 * the details as grpc-status-details-bin needs protobuf and is left to the
 * transport.
 *
 * ARFA 1.3: P1 (immutable outcome). Spec V4.3.3: final readonly.
 *
 * Preconditions: the message is written for clients: the factories use
 * fixed text, and an application that passes its own decides what the
 * client may read.
 *
 * Thread-safety: immutable; safe to share.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     return GrpcResponse::error(Status::notFound('No such cart'));
 *     new Status(StatusCode::FailedPrecondition, 'Cart is checked out', [['cart' => 'c-7']]);
 *
 * References: gRPC status codes; gRPC over HTTP/2 (Status, Status-Message);
 * google/rpc/status.proto; ADR-001.
 *
 * @see https://grpc.io/docs/guides/error/
 */
final readonly class Status
{
    /**
     * @param list<array<string, scalar|null>> $details
     */
    public function __construct(
        public StatusCode $code,
        public string $message = '',
        public array $details = [],
    ) {}

    public function isOk(): bool
    {
        return $this->code->isOk();
    }

    public static function ok(): self
    {
        return new self(StatusCode::Ok);
    }

    public static function cancelled(string $message = 'Cancelled'): self
    {
        return new self(StatusCode::Cancelled, $message);
    }

    public static function invalidArgument(string $message = 'Invalid argument'): self
    {
        return new self(StatusCode::InvalidArgument, $message);
    }

    public static function notFound(string $message = 'Not found'): self
    {
        return new self(StatusCode::NotFound, $message);
    }

    public static function alreadyExists(string $message = 'Already exists'): self
    {
        return new self(StatusCode::AlreadyExists, $message);
    }

    public static function permissionDenied(string $message = 'Permission denied'): self
    {
        return new self(StatusCode::PermissionDenied, $message);
    }

    public static function resourceExhausted(string $message = 'Resource exhausted'): self
    {
        return new self(StatusCode::ResourceExhausted, $message);
    }

    public static function unauthenticated(string $message = 'Unauthenticated'): self
    {
        return new self(StatusCode::Unauthenticated, $message);
    }

    public static function unimplemented(string $message = 'Not implemented'): self
    {
        return new self(StatusCode::Unimplemented, $message);
    }

    public static function internal(string $message = 'Internal error'): self
    {
        return new self(StatusCode::Internal, $message);
    }

    public static function unavailable(string $message = 'Service unavailable'): self
    {
        return new self(StatusCode::Unavailable, $message);
    }

    public static function deadlineExceeded(string $message = 'Deadline exceeded'): self
    {
        return new self(StatusCode::DeadlineExceeded, $message);
    }
}
