<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Enum;

/**
 * gRPC status codes per the gRPC specification.
 *
 * ARFA 1.3 P1 (Immutable State Transformation): enum cases are
 * inherently immutable value types.
 *
 * Spec V4.0 §2.1: pure enum — numeric values via match().
 *
 * These 17 codes (0–16) are the canonical gRPC status codes. Every gRPC
 * response carries exactly one status code, analogous to HTTP status
 * codes but with RPC-specific semantics (e.g. DeadlineExceeded has no
 * direct HTTP equivalent).
 *
 * Static memoization in tryFromCode() avoids repeated array
 * construction during response parsing.
 *
 * @see https://grpc.io/docs/guides/status-codes/
 * @see https://github.com/grpc/grpc/blob/master/doc/statuscodes.md
 */
enum StatusCode
{
    case Ok;
    case Cancelled;
    case Unknown;
    case InvalidArgument;
    case DeadlineExceeded;
    case NotFound;
    case AlreadyExists;
    case PermissionDenied;
    case ResourceExhausted;
    case FailedPrecondition;
    case Aborted;
    case OutOfRange;
    case Unimplemented;
    case Internal;
    case Unavailable;
    case DataLoss;
    case Unauthenticated;

    public function numericCode(): int
    {
        return match ($this) {
            self::Ok                 => 0,
            self::Cancelled          => 1,
            self::Unknown            => 2,
            self::InvalidArgument    => 3,
            self::DeadlineExceeded   => 4,
            self::NotFound           => 5,
            self::AlreadyExists      => 6,
            self::PermissionDenied   => 7,
            self::ResourceExhausted  => 8,
            self::FailedPrecondition => 9,
            self::Aborted            => 10,
            self::OutOfRange         => 11,
            self::Unimplemented      => 12,
            self::Internal           => 13,
            self::Unavailable        => 14,
            self::DataLoss           => 15,
            self::Unauthenticated    => 16,
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Ok                 => 'OK',
            self::Cancelled          => 'The operation was cancelled',
            self::Unknown            => 'Unknown error',
            self::InvalidArgument    => 'Client specified an invalid argument',
            self::DeadlineExceeded   => 'Deadline expired before operation could complete',
            self::NotFound           => 'Requested entity was not found',
            self::AlreadyExists      => 'Entity that we attempted to create already exists',
            self::PermissionDenied   => 'Caller does not have permission',
            self::ResourceExhausted  => 'Resource has been exhausted',
            self::FailedPrecondition => 'System is not in a state required for the operation',
            self::Aborted            => 'The operation was aborted due to a concurrency issue',
            self::OutOfRange         => 'Operation was attempted past the valid range',
            self::Unimplemented      => 'Operation is not implemented or supported',
            self::Internal           => 'Internal error',
            self::Unavailable        => 'The service is currently unavailable',
            self::DataLoss           => 'Unrecoverable data loss or corruption',
            self::Unauthenticated    => 'Request does not have valid authentication credentials',
        };
    }

    /**
     * Maps gRPC status to the nearest HTTP status code.
     *
     * Mapping per gRPC-HTTP status code mapping specification.
     *
     * @see https://github.com/grpc/grpc/blob/master/doc/http-grpc-status-mapping.md
     */
    public function httpStatusCode(): int
    {
        return match ($this) {
            self::Ok                 => 200,
            self::Cancelled          => 499,
            self::Unknown            => 500,
            self::InvalidArgument    => 400,
            self::DeadlineExceeded   => 504,
            self::NotFound           => 404,
            self::AlreadyExists      => 409,
            self::PermissionDenied   => 403,
            self::ResourceExhausted  => 429,
            self::FailedPrecondition => 400,
            self::Aborted            => 409,
            self::OutOfRange         => 400,
            self::Unimplemented      => 501,
            self::Internal           => 500,
            self::Unavailable        => 503,
            self::DataLoss           => 500,
            self::Unauthenticated    => 401,
        };
    }

    /**
     * Whether this status represents a successful outcome.
     */
    public function isOk(): bool
    {
        return $this === self::Ok;
    }

    public static function tryFromCode(int $code): ?self
    {
        static $map = null;

        $map ??= array_reduce(
            self::cases(),
            static function (array $carry, self $case): array {
                $carry[$case->numericCode()] = $case;
                return $carry;
            },
            [],
        );

        return $map[$code] ?? null;
    }
}
