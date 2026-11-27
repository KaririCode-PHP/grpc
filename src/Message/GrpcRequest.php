<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Message;

use KaririCode\Grpc\Enum\CallType;

/**
 * Immutable gRPC request message.
 *
 * ARFA 1.3 P1 (Immutable State Transformation): all with* methods
 * return a new instance.
 *
 * Spec V4.0: final readonly class.
 *
 * Fully decoded gRPC request after Length-Prefixed-Message framing
 * has been stripped and the codec has deserialized the payload.
 *
 * The service and method fields correspond to the gRPC path:
 *   POST /package.ServiceName/MethodName
 *
 * @see https://github.com/grpc/grpc/blob/master/doc/PROTOCOL-HTTP2.md
 */
final readonly class GrpcRequest
{
    /**
     * @param string                $service  Fully qualified service name
     * @param string                $method   RPC method name
     * @param array<string, mixed>  $payload  Decoded request message fields
     * @param Metadata              $metadata Request metadata (headers)
     * @param CallType              $callType RPC cardinality
     * @param float|null            $deadline Absolute deadline (microtime) or null for no deadline
     */
    public function __construct(
        public string $service,
        public string $method,
        public array $payload = [],
        public Metadata $metadata = new Metadata(),
        public CallType $callType = CallType::Unary,
        public ?float $deadline = null,
    ) {
    }

    /**
     * Full gRPC path: /{service}/{method}
     */
    public function path(): string
    {
        return "/{$this->service}/{$this->method}";
    }

    public function hasDeadline(): bool
    {
        return $this->deadline !== null;
    }

    public function isDeadlineExceeded(): bool
    {
        return $this->deadline !== null && microtime(true) > $this->deadline;
    }

    public function withPayload(array $payload): self
    {
        return new self(
            $this->service,
            $this->method,
            $payload,
            $this->metadata,
            $this->callType,
            $this->deadline,
        );
    }

    public function withMetadata(Metadata $metadata): self
    {
        return new self(
            $this->service,
            $this->method,
            $this->payload,
            $metadata,
            $this->callType,
            $this->deadline,
        );
    }

    public function withDeadline(?float $deadline): self
    {
        return new self(
            $this->service,
            $this->method,
            $this->payload,
            $this->metadata,
            $this->callType,
            $deadline,
        );
    }
}
