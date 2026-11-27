<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Enum;

/**
 * The status a gRPC call ends with, as the grpc-status trailer carries it:
 * the seventeen codes of the gRPC specification, backed by their wire
 * numbers. description() is the meaning the specification gives each code;
 * httpStatusCode() is the HTTP status of the google.rpc.Code mapping, for
 * gateways that translate gRPC to HTTP.
 *
 * ARFA 1.3: P5 (an outcome callers and logs branch on). Spec V4.3.3: a
 * backed enum for a wire value (E-003).
 *
 * Preconditions: none. tryFrom() reads a received grpc-status; a number
 * outside 0–16 has no case, and a client treats it as UNKNOWN.
 *
 * Thread-safety: enum cases are immutable.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     $code = StatusCode::tryFrom((int) $trailer) ?? StatusCode::Unknown;
 *     $code->httpStatusCode();   // 404 for NotFound
 *
 * References: gRPC status codes (doc/statuscodes.md); google/rpc/code.proto;
 * ADR-001.
 *
 * @see https://grpc.io/docs/guides/status-codes/
 */
enum StatusCode: int
{
    case Ok = 0;
    case Cancelled = 1;
    case Unknown = 2;
    case InvalidArgument = 3;
    case DeadlineExceeded = 4;
    case NotFound = 5;
    case AlreadyExists = 6;
    case PermissionDenied = 7;
    case ResourceExhausted = 8;
    case FailedPrecondition = 9;
    case Aborted = 10;
    case OutOfRange = 11;
    case Unimplemented = 12;
    case Internal = 13;
    case Unavailable = 14;
    case DataLoss = 15;
    case Unauthenticated = 16;

    public function description(): string
    {
        return match ($this) {
            self::Ok => 'Not an error; returned on success',
            self::Cancelled => 'The operation was cancelled, typically by the caller',
            self::Unknown => 'Unknown error',
            self::InvalidArgument => 'The client specified an invalid argument',
            self::DeadlineExceeded => 'The deadline expired before the operation could complete',
            self::NotFound => 'Some requested entity was not found',
            self::AlreadyExists => 'The entity a client attempted to create already exists',
            self::PermissionDenied => 'The caller does not have permission to execute the operation',
            self::ResourceExhausted => 'Some resource has been exhausted',
            self::FailedPrecondition => 'The system is not in a state required for the operation',
            self::Aborted => 'The operation was aborted, typically by a concurrency issue',
            self::OutOfRange => 'The operation was attempted past the valid range',
            self::Unimplemented => 'The operation is not implemented, supported or enabled',
            self::Internal => 'Internal error',
            self::Unavailable => 'The service is currently unavailable',
            self::DataLoss => 'Unrecoverable data loss or corruption',
            self::Unauthenticated => 'The request does not have valid authentication credentials',
        };
    }

    public function httpStatusCode(): int
    {
        return match ($this) {
            self::Ok => 200,
            self::Cancelled => 499,
            self::InvalidArgument, self::FailedPrecondition, self::OutOfRange => 400,
            self::Unauthenticated => 401,
            self::PermissionDenied => 403,
            self::NotFound => 404,
            self::AlreadyExists, self::Aborted => 409,
            self::ResourceExhausted => 429,
            self::Unknown, self::Internal, self::DataLoss => 500,
            self::Unimplemented => 501,
            self::Unavailable => 503,
            self::DeadlineExceeded => 504,
        };
    }

    public function isOk(): bool
    {
        return $this === self::Ok;
    }
}
