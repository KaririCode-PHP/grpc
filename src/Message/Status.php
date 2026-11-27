<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Message;

use KaririCode\Grpc\Enum\StatusCode;

/**
 * Immutable gRPC status (code + message + details).
 *
 * ARFA 1.3 P1 (Immutable State Transformation): final readonly class.
 *
 * Spec V4.0: static factories for common statuses.
 *
 * Every gRPC response terminates with a status sent in HTTP/2 trailers
 * (grpc-status and grpc-message). The optional details field carries
 * structured error information per the google.rpc.Status proto.
 *
 * @see https://grpc.io/docs/guides/status-codes/
 * @see https://github.com/googleapis/googleapis/blob/master/google/rpc/status.proto
 */
final readonly class Status
{
    /**
     * @param list<array<string, mixed>> $details Structured error details (google.rpc.Status.details)
     */
    public function __construct(
        public StatusCode $code,
        public string $message = '',
        public array $details = [],
    ) {
    }

    public function isOk(): bool
    {
        return $this->code->isOk();
    }

    // ── Common Factories ─────────────────────────────────────────────────

    public static function ok(): self
    {
        return new self(StatusCode::Ok);
    }

    public static function cancelled(string $message = 'Cancelled'): self
    {
        return new self(StatusCode::Cancelled, $message);
    }

    public static function invalidArgument(string $message): self
    {
        return new self(StatusCode::InvalidArgument, $message);
    }

    public static function notFound(string $message): self
    {
        return new self(StatusCode::NotFound, $message);
    }

    public static function permissionDenied(string $message = 'Permission denied'): self
    {
        return new self(StatusCode::PermissionDenied, $message);
    }

    public static function unauthenticated(string $message = 'Unauthenticated'): self
    {
        return new self(StatusCode::Unauthenticated, $message);
    }

    public static function internal(string $message = 'Internal error'): self
    {
        return new self(StatusCode::Internal, $message);
    }

    public static function unimplemented(string $service, string $method): self
    {
        return new self(StatusCode::Unimplemented, "Method not found: {$service}/{$method}");
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
