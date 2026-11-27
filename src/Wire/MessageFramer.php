<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Wire;

use KaririCode\Grpc\Exception\FrameException;
use KaririCode\Grpc\Exception\InvalidConfigurationException;

/**
 * Reads and writes the Length-Prefixed-Message of gRPC over HTTP/2: a
 * compressed-flag byte, a 4-byte big-endian length and the message. The
 * length is checked against the receive limit from the 5-byte prefix,
 * before the message arrives, so a client cannot make the server buffer
 * more than the limit; the default is 4 MiB, gRPC's usual receive limit.
 *
 * ARFA 1.3: P2 (bytes → message, the first stage of the read path). Spec
 * V4.3.3: final readonly, no state between calls.
 *
 * Preconditions: $maxMessageSize ≥ 1 (1940).
 *
 * Thread-safety: stateless; safe to share.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     $framer = new MessageFramer();
 *     $result = $framer->parse($body);           // null until the whole message is there
 *     if ($result !== null) {
 *         [$frame, $used] = $result;
 *         $body = substr($body, $used);
 *     }
 *     $data = $framer->frame($response->message);
 *
 * References: gRPC over HTTP/2 (Length-Prefixed-Message); gRPC
 * MaxRecvMsgSize defaults; ADR-006.
 *
 * @see https://github.com/grpc/grpc/blob/master/doc/PROTOCOL-HTTP2.md#requests
 */
final readonly class MessageFramer
{
    public function __construct(public int $maxMessageSize = 4_194_304)
    {
        if ($maxMessageSize < 1) {
            throw InvalidConfigurationException::nonPositiveLimit('maxMessageSize');
        }
    }

    /** $message as one uncompressed Length-Prefixed-Message. */
    public function frame(string $message): string
    {
        return "\x00" . pack('N', strlen($message)) . $message;
    }

    /**
     * The first message in $buffer and the bytes it used, or null while it
     * is incomplete.
     *
     * @return array{MessageFrame, int}|null
     */
    public function parse(string $buffer): ?array
    {
        if (strlen($buffer) < 5) {
            return null;
        }

        $flag = ord($buffer[0]);

        if ($flag > 1) {
            throw FrameException::invalidCompressedFlag();
        }

        $length = (ord($buffer[1]) << 24) | (ord($buffer[2]) << 16) | (ord($buffer[3]) << 8) | ord($buffer[4]);

        if ($length > $this->maxMessageSize) {
            throw FrameException::messageTooLarge();
        }

        return strlen($buffer) < 5 + $length ? null : [new MessageFrame(substr($buffer, 5, $length), $flag === 1), 5 + $length];
    }
}
