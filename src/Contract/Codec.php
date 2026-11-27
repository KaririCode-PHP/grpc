<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Contract;

/**
 * Turns a message between its wire bytes and an array: the content
 * subtype of application/grpc+{name}. Codec\JsonCodec implements
 * application/grpc+json; protobuf messages are decoded by the classes
 * protoc generates, from GrpcRequest::$message.
 *
 * ARFA 1.3: P4 (the serialization stays outside the dispatch). Spec
 * V4.3.3: an interface with no state.
 *
 * Preconditions: decode() refuses bytes that are not a message of its
 * format, and encode() an array it cannot write; both with
 * KaririCode\Grpc\Exception\InvalidMessageException.
 *
 * Thread-safety: implementations hold no per-call state.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     $codec = new JsonCodec();
 *     $request = $codec->decode($grpcRequest->message);
 *     return GrpcResponse::ok($codec->encode(['id' => $id]));
 *
 * References: gRPC over HTTP/2 (Content-Type); ADR-002.
 *
 * @see https://github.com/grpc/grpc/blob/master/doc/PROTOCOL-HTTP2.md#requests
 */
interface Codec
{
    /** The content subtype: "json" for application/grpc+json. */
    public function name(): string;

    /**
     * @param array<array-key, mixed> $message
     */
    public function encode(array $message): string;

    /**
     * @return array<array-key, mixed>
     */
    public function decode(string $data): array;
}
