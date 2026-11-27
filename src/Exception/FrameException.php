<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Exception;

use KaririCode\Exception\ProtocolException;
use KaririCode\Grpc\Enum\StatusCode;

/**
 * A length-prefixed message breaks the framing of gRPC over HTTP/2: its
 * declared length is above the receive limit, or its compressed-flag byte
 * is neither 0 nor 1. Each carries the status the server answers with:
 * RESOURCE_EXHAUSTED for the limit, INTERNAL for the flag.
 *
 * ARFA 1.3: P2 (the first stage of the read path). Spec V4.3.3: final, a
 * kariricode/contract Protocol failure, built by named constructors.
 *
 * Preconditions: messages name the rule, never the bytes (errata E-047).
 *
 * Thread-safety: an exception belongs to the call that threw it.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     try {
 *         $result = $framer->parse($body);
 *     } catch (FrameException $e) {
 *         $response = GrpcResponse::error(new Status($e->status, 'Message rejected'));
 *     }
 *
 * References: gRPC over HTTP/2 (Length-Prefixed-Message); KaririCode Spec
 * V4.3.3 §8.4; ADR-003, ADR-006.
 *
 * @see https://github.com/grpc/grpc/blob/master/doc/PROTOCOL-HTTP2.md#requests
 */
final class FrameException extends ProtocolException
{
    private function __construct(GrpcCode $code, public readonly StatusCode $status, string $message)
    {
        parent::__construct($code, $message);
    }

    public static function messageTooLarge(): self
    {
        return new self(GrpcCode::MessageTooLarge, StatusCode::ResourceExhausted, 'The message is longer than the receive limit.');
    }

    public static function invalidCompressedFlag(): self
    {
        return new self(GrpcCode::InvalidCompressedFlag, StatusCode::Internal, 'The compressed flag of a message is 0 or 1.');
    }
}
