<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Integration;

use KaririCode\Router\Contract\RequestNormalizer;
use KaririCode\Router\Core\NormalizedRequest;
use KaririCode\Grpc\Message\GrpcRequest;

/**
 * Bridges gRPC requests into Router V4.1 string-based matching.
 *
 * ARFA 1.3 P4 (Protocol Agnosticism): implements the
 * {@see RequestNormalizer} contract from kariricode/router. This class
 * lives in kariricode/grpc (not in Router) so the Router package has
 * zero knowledge of gRPC types.
 *
 * Spec V4.0: final class, zero state, pure transformation.
 *
 * Mapping convention (gRPC over HTTP/2 §2):
 *   method   → CallType router method ("UNARY", "SERVER_STREAM", etc.)
 *   uri      → full gRPC path (/{service}/{method})
 *   original → the {@see GrpcRequest} object for handler-level access
 *
 * Route registration example:
 *   $router->route('UNARY', '/myapp.v1.UserService/GetUser', $handler);
 *   $router->route('SERVER_STREAM', '/myapp.v1.UserService/ListUsers', $handler);
 *
 * Dependency direction: Grpc → Router (never reversed).
 *
 * @see \KaririCode\Router\Contract\RequestNormalizer
 * @see \KaririCode\Router\Core\NormalizedRequest
 * @see https://github.com/grpc/grpc/blob/master/doc/PROTOCOL-HTTP2.md
 */
final class GrpcNormalizer implements RequestNormalizer
{
    public function canNormalize(object $request): bool
    {
        return $request instanceof GrpcRequest;
    }

    public function normalize(object $request): NormalizedRequest
    {
        assert($request instanceof GrpcRequest);

        return new NormalizedRequest(
            method: $request->callType->routerMethod(),
            uri: $request->path(),
            original: $request,
        );
    }
}
