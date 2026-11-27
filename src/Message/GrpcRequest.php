<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Message;

use KaririCode\Grpc\Enum\CallType;
use KaririCode\Grpc\Exception\InvalidMessageException;

/**
 * One call as the server receives it: the service and method of its
 * :path, the serialized request message, the custom metadata, the kind of
 * call, the deadline, the peer's address, and attributes. The message is
 * the bytes the client sent, after the transport removed the
 * length prefix; a handler decodes them with its generated protobuf
 * classes or Codec\JsonCodec. Attributes carry server-side values (what
 * the transport verified, what an interceptor accepted) and never come
 * from the client.
 *
 * ARFA 1.3: P1 (immutable; with*() returns a new instance). Spec V4.3.3:
 * final readonly; all-promoted constructor.
 *
 * Preconditions: $service is dot-separated identifiers (1913), $method an
 * identifier (1914), $peer an IP address without port or empty (1916).
 *
 * Thread-safety: immutable; safe to share.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     $request = GrpcRequest::fromPath('/shop.Cart/AddItem', $body, new Metadata($custom))
 *         ->withDeadline(Timeout::deadline($headers['grpc-timeout'], $clock->now()));
 *     $item = (new JsonCodec())->decode($request->message);
 *
 * References: gRPC over HTTP/2 (Path, Timeout, Custom-Metadata); ADR-002.
 *
 * @see https://github.com/grpc/grpc/blob/master/doc/PROTOCOL-HTTP2.md#requests
 */
final readonly class GrpcRequest
{
    /** Dot-separated protobuf identifiers: a package-qualified service name. */
    public const string SERVICE_PATTERN = '/\A[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)*\z/';

    /** One protobuf identifier. */
    public const string METHOD_PATTERN = '/\A[A-Za-z_][A-Za-z0-9_]*\z/';

    /**
     * @param array<string, mixed> $attributes server-side values, by name
     */
    public function __construct(
        public string $service,
        public string $method,
        public string $message = '',
        public Metadata $metadata = new Metadata(),
        public CallType $callType = CallType::Unary,
        public ?\DateTimeImmutable $deadline = null,
        public string $peer = '',
        public array $attributes = [],
    ) {
        if (preg_match(self::SERVICE_PATTERN, $service) !== 1) {
            throw InvalidMessageException::invalidServiceName();
        }

        if (preg_match(self::METHOD_PATTERN, $method) !== 1) {
            throw InvalidMessageException::invalidMethodName();
        }

        if ($peer !== '' && filter_var($peer, FILTER_VALIDATE_IP) === false) {
            throw InvalidMessageException::invalidPeer();
        }
    }

    /** The request for an HTTP/2 :path "/service/method" (1915 for any other form). */
    public static function fromPath(string $path, string $message = '', Metadata $metadata = new Metadata()): self
    {
        if (preg_match('#\A/([^/]+)/([^/]+)\z#', $path, $match) !== 1) {
            throw InvalidMessageException::invalidPath();
        }

        return new self($match[1], $match[2], $message, $metadata);
    }

    /** "/service/method", the :path of the call. */
    public function path(): string
    {
        return '/' . $this->service . '/' . $this->method;
    }

    public function hasDeadline(): bool
    {
        return $this->deadline !== null;
    }

    public function isDeadlineExceeded(\DateTimeImmutable $now): bool
    {
        return $this->deadline !== null && $now >= $this->deadline;
    }

    public function attribute(string $name, mixed $default = null): mixed
    {
        return array_key_exists($name, $this->attributes) ? $this->attributes[$name] : $default;
    }

    public function withMessage(string $message): self
    {
        return new self($this->service, $this->method, $message, $this->metadata, $this->callType, $this->deadline, $this->peer, $this->attributes);
    }

    public function withMetadata(Metadata $metadata): self
    {
        return new self($this->service, $this->method, $this->message, $metadata, $this->callType, $this->deadline, $this->peer, $this->attributes);
    }

    public function withCallType(CallType $callType): self
    {
        return new self($this->service, $this->method, $this->message, $this->metadata, $callType, $this->deadline, $this->peer, $this->attributes);
    }

    public function withDeadline(?\DateTimeImmutable $deadline): self
    {
        return new self($this->service, $this->method, $this->message, $this->metadata, $this->callType, $deadline, $this->peer, $this->attributes);
    }

    /** The request from $peer, the client's IP address as the transport saw it. */
    public function withPeer(string $peer): self
    {
        return new self($this->service, $this->method, $this->message, $this->metadata, $this->callType, $this->deadline, $peer, $this->attributes);
    }

    public function withAttribute(string $name, mixed $value): self
    {
        return new self($this->service, $this->method, $this->message, $this->metadata, $this->callType, $this->deadline, $this->peer, [...$this->attributes, $name => $value]);
    }
}
