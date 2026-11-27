<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Message;

use KaririCode\Grpc\Enum\StatusCode;

/**
 * Immutable gRPC response message.
 *
 * ARFA 1.3 P1 (Immutable State Transformation): all with* methods
 * return a new instance.
 *
 * Spec V4.0: final readonly class with static factories for
 * common outcomes.
 *
 * Carries the response payload, headers (initial metadata), trailers
 * (trailing metadata), and the terminal gRPC status.
 *
 * @see https://github.com/grpc/grpc/blob/master/doc/PROTOCOL-HTTP2.md
 */
final readonly class GrpcResponse
{
    /**
     * @param array<string, mixed> $payload  Decoded response message fields
     * @param Status               $status   Terminal gRPC status
     * @param Metadata             $headers  Initial metadata (sent before payload)
     * @param Metadata             $trailers Trailing metadata (sent with status)
     */
    public function __construct(
        public array $payload = [],
        public Status $status = new Status(StatusCode::Ok),
        public Metadata $headers = new Metadata(),
        public Metadata $trailers = new Metadata(),
    ) {
    }

    public function isOk(): bool
    {
        return $this->status->isOk();
    }

    public function withPayload(array $payload): self
    {
        return new self($payload, $this->status, $this->headers, $this->trailers);
    }

    public function withStatus(Status $status): self
    {
        return new self($this->payload, $status, $this->headers, $this->trailers);
    }

    public function withHeaders(Metadata $headers): self
    {
        return new self($this->payload, $this->status, $headers, $this->trailers);
    }

    public function withTrailers(Metadata $trailers): self
    {
        return new self($this->payload, $this->status, $this->headers, $trailers);
    }

    // ── Factories ────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $payload
     */
    public static function ok(array $payload = [], Metadata $headers = new Metadata()): self
    {
        return new self($payload, Status::ok(), $headers);
    }

    public static function error(Status $status, Metadata $trailers = new Metadata()): self
    {
        return new self([], $status, trailers: $trailers);
    }

    public static function unimplemented(string $service, string $method): self
    {
        return self::error(Status::unimplemented($service, $method));
    }
}
