<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Codec;

use KaririCode\Grpc\Contract\Codec;

/**
 * JSON codec for gRPC-JSON and gRPC-Web text mode.
 *
 * ARFA 1.3 P2 (Reactive Flow Composition): pluggable codec stage.
 *
 * Spec V4.0: final class, zero state, pure transformation.
 *
 * Content-type: application/grpc+json
 *
 * Useful for debugging, gRPC-Web browser clients, and environments
 * where Protocol Buffers tooling is unavailable.
 *
 * @see https://grpc.io/blog/grpc-with-json/
 */
final class JsonCodec implements Codec
{
    private const JSON_ENCODE_FLAGS = JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_THROW_ON_ERROR
        | JSON_PRESERVE_ZERO_FRACTION;

    public function name(): string
    {
        return 'json';
    }

    public function encode(array $message): string
    {
        return json_encode($message, self::JSON_ENCODE_FLAGS);
    }

    public function decode(string $data): array
    {
        if ($data === '') {
            return [];
        }

        $decoded = json_decode($data, true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }
}
