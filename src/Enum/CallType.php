<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Enum;

/**
 * gRPC call types (RPC cardinality).
 *
 * ARFA 1.3 P1 (Immutable State Transformation): enum cases are
 * inherently immutable value types.
 *
 * ARFA 1.3 P4 (Protocol Agnosticism): each call type maps to an
 * uppercase string used as the Router method key — enabling unified
 * route registration for gRPC alongside HTTP/WebSocket/SSE.
 *
 * Spec V4.0 §2.1: pure enum.
 *
 * @see https://grpc.io/docs/what-is-grpc/core-concepts/#rpc-life-cycle
 */
enum CallType
{
    case Unary;
    case ServerStreaming;
    case ClientStreaming;
    case BidiStreaming;

    /**
     * The Router method string for route registration.
     *
     * Convention: uppercase string matching the gRPC call pattern.
     * Example: $router->route('UNARY', '/myapp.UserService/GetUser', ...)
     */
    public function routerMethod(): string
    {
        return match ($this) {
            self::Unary           => 'UNARY',
            self::ServerStreaming  => 'SERVER_STREAM',
            self::ClientStreaming  => 'CLIENT_STREAM',
            self::BidiStreaming    => 'BIDI_STREAM',
        };
    }

    /**
     * Whether this call type receives a single request message.
     */
    public function isSingleRequest(): bool
    {
        return match ($this) {
            self::Unary, self::ServerStreaming => true,
            self::ClientStreaming, self::BidiStreaming => false,
        };
    }

    /**
     * Whether this call type sends a single response message.
     */
    public function isSingleResponse(): bool
    {
        return match ($this) {
            self::Unary, self::ClientStreaming => true,
            self::ServerStreaming, self::BidiStreaming => false,
        };
    }

    public static function fromRouterMethod(string $method): ?self
    {
        return match (strtoupper($method)) {
            'UNARY'         => self::Unary,
            'SERVER_STREAM' => self::ServerStreaming,
            'CLIENT_STREAM' => self::ClientStreaming,
            'BIDI_STREAM'   => self::BidiStreaming,
            default         => null,
        };
    }
}
