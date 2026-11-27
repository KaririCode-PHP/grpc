<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Exception;

use KaririCode\Exception\ValidationException;

/**
 * GrpcNormalizer was given something other than a GrpcRequest: a wiring
 * error, since the router asks canNormalize() first.
 *
 * ARFA 1.3: P4 (the router stays protocol-agnostic). Spec V4.3.3: final, a
 * kariricode/contract Validation failure, built by a named constructor.
 *
 * Preconditions: none.
 *
 * Thread-safety: an exception belongs to the call that threw it.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     (new GrpcNormalizer())->normalize(new \stdClass());   // UnsupportedRequestException 1950
 *
 * References: kariricode/router RequestNormalizer; KaririCode Spec V4.3.3
 * §8.4; ADR-001, ADR-003.
 *
 * @see https://github.com/KaririCode-PHP/router
 */
final class UnsupportedRequestException extends ValidationException
{
    public static function notAGrpcRequest(): self
    {
        return new self(GrpcCode::NotAGrpcRequest, 'GrpcNormalizer normalizes GrpcRequest objects only.');
    }
}
