<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Enum;

/**
 * The message encodings a grpc-encoding header can name, backed by their
 * wire names. A call without the header uses identity. The framer reports
 * whether a message is compressed (Wire\MessageFrame); decompressing it
 * with the named algorithm is the transport's step.
 *
 * ARFA 1.3: P4 (wire vocabulary). Spec V4.3.3: a backed enum for a wire
 * value (E-003).
 *
 * Preconditions: none. fromHeader() returns null for an encoding this
 * enum does not list; the server then answers UNIMPLEMENTED and lists
 * what it accepts in grpc-accept-encoding.
 *
 * Thread-safety: enum cases are immutable.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     $encoding = CompressionAlgorithm::fromHeader($headers['grpc-encoding'] ?? '');
 *     $encoding?->requiresZlib();   // true for gzip and deflate
 *
 * References: gRPC compression (doc/compression.md); gRPC over HTTP/2
 * (Message-Encoding); ADR-006.
 *
 * @see https://github.com/grpc/grpc/blob/master/doc/compression.md
 */
enum CompressionAlgorithm: string
{
    case Identity = 'identity';
    case Gzip = 'gzip';
    case Deflate = 'deflate';

    public function requiresZlib(): bool
    {
        return $this !== self::Identity;
    }

    /** The encoding a grpc-encoding value names; an empty value is identity. */
    public static function fromHeader(string $value): ?self
    {
        $name = strtolower(trim($value, " \t"));

        return $name === '' ? self::Identity : self::tryFrom($name);
    }
}
