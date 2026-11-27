<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Wire;

/**
 * The grpc-message trailer's encoding: the status message as UTF-8 bytes,
 * with every byte outside space–tilde, and "%" itself, written as %XX.
 * The encoded form fits in an HTTP/2 header whatever the message holds, so
 * a status message cannot split the trailers. decode() reads a received
 * value; a malformed escape stays as it is, because the specification
 * forbids failing or dropping the message.
 *
 * ARFA 1.3: P2 (domain value ↔ wire value). Spec V4.3.3: final, stateless
 * functions.
 *
 * Preconditions: none.
 *
 * Thread-safety: stateless; safe to share.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     StatusMessage::encode("Preço inválido\n");   // "Pre%C3%A7o inv%C3%A1lido%0A"
 *     StatusMessage::decode('50%25 done');         // "50% done"
 *
 * References: gRPC over HTTP/2 (Status-Message, Percent-Encoded); ADR-006.
 *
 * @see https://github.com/grpc/grpc/blob/master/doc/PROTOCOL-HTTP2.md#responses
 */
final class StatusMessage
{
    public static function encode(string $message): string
    {
        return (string) preg_replace_callback('/[^\x20-\x24\x26-\x7E]/', static fn(array $byte): string => sprintf('%%%02X', ord($byte[0])), $message);
    }

    public static function decode(string $value): string
    {
        return (string) preg_replace_callback('/%([0-9A-Fa-f]{2})/', static fn(array $escape): string => chr((int) hexdec($escape[1])), $value);
    }
}
