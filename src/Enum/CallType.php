<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Enum;

/**
 * The four kinds of gRPC call, by how many messages each side sends. The
 * value is the pseudo-method a router matches (GrpcNormalizer), so one
 * route table can hold unary and streaming methods apart.
 *
 * ARFA 1.3: P4 (call kinds as router methods). Spec V4.3.3: a backed enum
 * for a value that leaves the package (E-003).
 *
 * Preconditions: none.
 *
 * Thread-safety: enum cases are immutable.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     $routes->route([CallType::Unary->routerMethod()], '/shop.Cart/AddItem', AddItem::class);
 *     CallType::ServerStreaming->isSingleRequest();   // true: one request, many responses
 *
 * References: gRPC concepts (RPC life cycle); kariricode/router
 * NormalizedRequest; ADR-001.
 *
 * @see https://grpc.io/docs/what-is-grpc/core-concepts/#rpc-life-cycle
 */
enum CallType: string
{
    case Unary = 'UNARY';
    case ServerStreaming = 'SERVER_STREAM';
    case ClientStreaming = 'CLIENT_STREAM';
    case BidiStreaming = 'BIDI_STREAM';

    public function routerMethod(): string
    {
        return $this->value;
    }

    public function isSingleRequest(): bool
    {
        return $this === self::Unary || $this === self::ServerStreaming;
    }

    public function isSingleResponse(): bool
    {
        return $this === self::Unary || $this === self::ClientStreaming;
    }
}
