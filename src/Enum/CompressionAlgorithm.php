<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Enum;

/**
 * gRPC message compression algorithms.
 *
 * ARFA 1.3 P1 (Immutable State Transformation): enum cases are
 * inherently immutable value types.
 *
 * Spec V4.0 §2.1: pure enum.
 *
 * gRPC supports per-message compression via the Compressed-Flag
 * byte in the Length-Prefixed-Message framing. The algorithm is
 * negotiated via the grpc-encoding header.
 *
 * @see https://github.com/grpc/grpc/blob/master/doc/compression.md
 */
enum CompressionAlgorithm
{
    case Identity;
    case Gzip;
    case Deflate;

    /**
     * The wire-format name used in grpc-encoding / grpc-accept-encoding.
     */
    public function wireValue(): string
    {
        return match ($this) {
            self::Identity => 'identity',
            self::Gzip     => 'gzip',
            self::Deflate  => 'deflate',
        };
    }

    /**
     * Whether this algorithm requires ext-zlib.
     */
    public function requiresZlib(): bool
    {
        return match ($this) {
            self::Identity => false,
            self::Gzip, self::Deflate => true,
        };
    }

    public static function fromWireValue(string $value): ?self
    {
        return match (strtolower(trim($value))) {
            'identity', '' => self::Identity,
            'gzip'         => self::Gzip,
            'deflate'      => self::Deflate,
            default        => null,
        };
    }
}
