<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Exception;

use KaririCode\Exception\RuntimeException;
use KaririCode\Grpc\Message\Status;

/**
 * Ends a call with a gRPC status from anywhere in a service or an
 * interceptor: UnaryHandler catches it and answers with its status, as if
 * the handler had returned GrpcResponse::error($status). The status
 * message and details are the application's, written for the client.
 *
 * ARFA 1.3: P5 (an outcome with its status). Spec V4.3.3: final, a
 * kariricode/contract Runtime failure, built by a named constructor.
 *
 * Preconditions: the status is not OK; an OK status reaches the client as
 * an error response without a message.
 *
 * Thread-safety: an exception belongs to the call that threw it.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     $user = $users->find($id) ?? throw StatusException::failed(Status::notFound('No such user'));
 *
 * References: gRPC status codes; KaririCode Spec V4.3.3 §8.4; ADR-001,
 * ADR-003.
 *
 * @see https://grpc.io/docs/guides/error/
 */
final class StatusException extends RuntimeException
{
    private function __construct(GrpcCode $code, public readonly Status $status)
    {
        parent::__construct($code, sprintf('The call failed with status %s.', self::quote($status->code->name)));
    }

    public static function failed(Status $status): self
    {
        return new self(GrpcCode::CallFailed, $status);
    }
}
