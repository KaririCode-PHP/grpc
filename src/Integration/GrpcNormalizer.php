<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Integration;

use KaririCode\Grpc\Exception\UnsupportedRequestException;
use KaririCode\Grpc\Message\GrpcRequest;
use KaririCode\Router\Contract\RequestNormalizer;
use KaririCode\Router\Core\NormalizedRequest;

/**
 * Lets kariricode/router route gRPC calls: a request becomes the router
 * request with the call type as its method (UNARY, SERVER_STREAM, ...) and
 * its :path as the URI, so one route table can map "/shop.Cart/AddItem" to
 * a handler beside HTTP, WebSocket and SSE routes.
 *
 * ARFA 1.3: P4 (the router stays protocol-agnostic). Spec V4.3.3: final;
 * the `object` parameters are the router's RequestNormalizer signature.
 *
 * Preconditions: normalize() takes what canNormalize() accepted
 * (UnsupportedRequestException 1950 otherwise).
 *
 * Thread-safety: stateless; safe to share.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     $routes->route([CallType::Unary->routerMethod()], '/shop.Cart/AddItem', AddItem::class);
 *     $normalized = (new GrpcNormalizer())->normalize($request);
 *     $match = $routes->match($normalized->method, $normalized->uri);
 *
 * References: kariricode/router RequestNormalizer and NormalizedRequest
 * (its ADR-002); gRPC over HTTP/2 (Path); ADR-001.
 *
 * @see https://github.com/KaririCode-PHP/router
 */
final class GrpcNormalizer implements RequestNormalizer
{
    #[\Override]
    public function canNormalize(object $request): bool
    {
        return $request instanceof GrpcRequest;
    }

    #[\Override]
    public function normalize(object $request): NormalizedRequest
    {
        if (!$request instanceof GrpcRequest) {
            throw UnsupportedRequestException::notAGrpcRequest();
        }

        return new NormalizedRequest($request->callType->routerMethod(), $request->path(), $request);
    }
}
