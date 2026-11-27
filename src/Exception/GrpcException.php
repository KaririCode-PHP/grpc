<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Exception;

use KaririCode\Grpc\Enum\StatusCode;

/**
 * Base exception for gRPC errors.
 *
 * ARFA 1.3 P1: carries an immutable {@see StatusCode} enum value so
 * the server can send the appropriate grpc-status trailer.
 *
 * Spec V4.0: non-final by design — extended by {@see ServiceException}
 * for service-level errors with structured details.
 *
 * @see https://grpc.io/docs/guides/status-codes/
 */
class GrpcException extends \RuntimeException
{
    public function __construct(
        public readonly StatusCode $statusCode,
        string $message = '',
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            $message ?: $statusCode->description(),
            $statusCode->numericCode(),
            $previous,
        );
    }
}
