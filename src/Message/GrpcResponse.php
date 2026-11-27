<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Message;

use KaririCode\Grpc\Enum\StatusCode;

/**
 * The answer to a call: the serialized response message, the status, and
 * the metadata sent as response headers and as trailers. A failed call
 * normally carries no message; the transport then sends a trailers-only
 * response with grpc-status and grpc-message.
 *
 * ARFA 1.3: P1 (immutable; with*() returns a new instance). Spec V4.3.3:
 * final readonly.
 *
 * Preconditions: none.
 *
 * Thread-safety: immutable; safe to share.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     return GrpcResponse::ok((new JsonCodec())->encode(['total' => 42]));
 *     return GrpcResponse::error(Status::invalidArgument('quantity must be positive'));
 *
 * References: gRPC over HTTP/2 (Responses, Trailers-Only); ADR-002.
 *
 * @see https://github.com/grpc/grpc/blob/master/doc/PROTOCOL-HTTP2.md#responses
 */
final readonly class GrpcResponse
{
    public function __construct(
        public string $message = '',
        public Status $status = new Status(StatusCode::Ok),
        public Metadata $headers = new Metadata(),
        public Metadata $trailers = new Metadata(),
    ) {}

    public static function ok(string $message = '', Metadata $headers = new Metadata()): self
    {
        return new self($message, Status::ok(), $headers);
    }

    public static function error(Status $status, Metadata $trailers = new Metadata()): self
    {
        return new self('', $status, trailers: $trailers);
    }

    public function isOk(): bool
    {
        return $this->status->isOk();
    }

    public function withMessage(string $message): self
    {
        return new self($message, $this->status, $this->headers, $this->trailers);
    }

    public function withStatus(Status $status): self
    {
        return new self($this->message, $status, $this->headers, $this->trailers);
    }

    public function withHeaders(Metadata $headers): self
    {
        return new self($this->message, $this->status, $headers, $this->trailers);
    }

    public function withTrailers(Metadata $trailers): self
    {
        return new self($this->message, $this->status, $this->headers, $trailers);
    }
}
