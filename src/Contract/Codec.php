<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Contract;

/**
 * Codec for gRPC message serialization/deserialization.
 *
 * ARFA 1.3 P2 (Reactive Flow Composition): codecs are pluggable
 * stages in the request/response pipeline.
 *
 * Spec V4.0 §1.3: interface without "Interface" suffix — implementations
 * MUST be stateless — a single codec instance is shared across all
 * requests for a given content-type.
 *
 * @see https://grpc.io/docs/guides/concepts/#codecs
 */
interface Codec
{
    /**
     * Content-type sub-type identifier (e.g. "proto", "json").
     * Used in the content-type header: application/grpc+{name}.
     */
    public function name(): string;

    /**
     * Serialize a message to its wire format.
     *
     * @param array<string, mixed> $message Associative array of field values
     * @return string Binary payload
     */
    public function encode(array $message): string;

    /**
     * Deserialize a wire-format payload into an associative array.
     *
     * @param string $data Binary payload
     * @return array<string, mixed>
     */
    public function decode(string $data): array;
}
