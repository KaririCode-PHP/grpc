<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Codec;

use KaririCode\Grpc\Contract\Codec;
use KaririCode\Grpc\Exception\InvalidMessageException;

/**
 * The codec of application/grpc+json: a message is one JSON object. An
 * empty message decodes to an empty array, as an empty protobuf message
 * means every field at its default. Encoding keeps Unicode and slashes as
 * they are and writes 1.0 as 1.0.
 *
 * ARFA 1.3: P4 (one serialization behind Contract\Codec). Spec V4.3.3:
 * final readonly, stateless.
 *
 * Preconditions: decode() takes a JSON object (1918 when the bytes are not
 * JSON, 1919 when the value is not an object); encode() takes data JSON
 * can hold (1920 for invalid UTF-8, INF or NAN, or nesting deeper than
 * 512).
 *
 * Thread-safety: stateless; safe to share.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     $codec = new JsonCodec();
 *     $codec->decode('{"sku":"A-1","quantity":2}');   // ['sku' => 'A-1', 'quantity' => 2]
 *     $codec->encode(['total' => 42.0]);               // {"total":42.0}
 *
 * References: RFC 8259; gRPC over HTTP/2 (Content-Type); ADR-002.
 *
 * @see https://www.rfc-editor.org/rfc/rfc8259
 */
final readonly class JsonCodec implements Codec
{
    private const int FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION;

    #[\Override]
    public function name(): string
    {
        return 'json';
    }

    #[\Override]
    public function encode(array $message): string
    {
        try {
            return json_encode($message, self::FLAGS | JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw InvalidMessageException::unencodableJson();
        }
    }

    #[\Override]
    public function decode(string $data): array
    {
        if ($data === '') {
            return [];
        }

        try {
            return self::object($data, json_decode($data, true, 512, JSON_THROW_ON_ERROR));
        } catch (\JsonException) {
            throw InvalidMessageException::malformedJson();
        }
    }

    /**
     * A JSON array decodes to a PHP array too; only an object is a message.
     *
     * @return array<array-key, mixed>
     */
    private static function object(string $data, mixed $decoded): array
    {
        return is_array($decoded) && str_starts_with(ltrim($data, " \t\n\r"), '{') ? $decoded : throw InvalidMessageException::jsonNotAnObject();
    }
}
