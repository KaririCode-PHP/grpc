<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Wire;

/**
 * One message of a gRPC stream as the framer read it: the bytes and
 * whether the sender compressed them with the call's grpc-encoding.
 *
 * ARFA 1.3: P1 (immutable value). Spec V4.3.3: final readonly.
 *
 * Preconditions: none.
 *
 * Thread-safety: immutable; safe to share.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     [$frame, $used] = $framer->parse($body) ?? throw new \LengthException('incomplete');
 *     $frame->compressed ? $transport->decompress($frame->message) : $frame->message;
 *
 * References: gRPC over HTTP/2 (Length-Prefixed-Message); ADR-006.
 *
 * @see https://github.com/grpc/grpc/blob/master/doc/PROTOCOL-HTTP2.md#requests
 */
final readonly class MessageFrame
{
    public function __construct(
        public string $message,
        public bool $compressed = false,
    ) {}

    public function length(): int
    {
        return strlen($this->message);
    }
}
