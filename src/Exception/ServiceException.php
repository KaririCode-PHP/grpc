<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Exception;

use KaririCode\Grpc\Enum\StatusCode;

/**
 * Service-level gRPC exception with structured error details.
 *
 * ARFA 1.3 P2: exceptions propagate up the interceptor pipeline.
 *
 * Spec V4.0: final class, static factories enforce consistent messages.
 *
 * Carries optional structured details (google.rpc.Status.details) that
 * can be serialized into the grpc-status-details-bin trailer.
 *
 * @see https://grpc.io/docs/guides/error/
 * @see https://github.com/googleapis/googleapis/blob/master/google/rpc/error_details.proto
 */
final class ServiceException extends GrpcException
{
    /**
     * @param list<array<string, mixed>> $details Structured error details
     */
    public function __construct(
        StatusCode $statusCode,
        string $message = '',
        public readonly array $details = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($statusCode, $message, $previous);
    }

    public static function invalidArgument(string $field, string $reason): self
    {
        return new self(
            StatusCode::InvalidArgument,
            "Invalid argument '{$field}': {$reason}",
            [['field' => $field, 'reason' => $reason]],
        );
    }

    public static function notFound(string $resource, string $id): self
    {
        return new self(
            StatusCode::NotFound,
            "{$resource} '{$id}' not found",
            [['resource' => $resource, 'id' => $id]],
        );
    }

    public static function alreadyExists(string $resource, string $id): self
    {
        return new self(
            StatusCode::AlreadyExists,
            "{$resource} '{$id}' already exists",
            [['resource' => $resource, 'id' => $id]],
        );
    }

    public static function permissionDenied(string $resource, string $action): self
    {
        return new self(
            StatusCode::PermissionDenied,
            "Permission denied: cannot {$action} {$resource}",
            [['resource' => $resource, 'action' => $action]],
        );
    }

    public static function deadlineExceeded(float $deadlineSeconds): self
    {
        return new self(
            StatusCode::DeadlineExceeded,
            sprintf('Deadline exceeded after %.3fs', $deadlineSeconds),
        );
    }

    public static function resourceExhausted(string $resource, string $limit): self
    {
        return new self(
            StatusCode::ResourceExhausted,
            "Resource exhausted: {$resource} limit {$limit}",
            [['resource' => $resource, 'limit' => $limit]],
        );
    }

    public static function internal(string $message, ?\Throwable $cause = null): self
    {
        return new self(StatusCode::Internal, $message, previous: $cause);
    }

    public static function unimplemented(string $service, string $method): self
    {
        return new self(
            StatusCode::Unimplemented,
            "Method not found: {$service}/{$method}",
        );
    }
}
