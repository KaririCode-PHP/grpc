<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Contract;

use KaririCode\Grpc\Message\GrpcRequest;
use KaririCode\Grpc\Message\GrpcResponse;

/**
 * A step around every call: it can read or replace the request, answer
 * without calling $next (an authentication failure), or read or replace
 * the response. Handler\InterceptorPipeline runs the steps in order, the
 * first outermost.
 *
 * ARFA 1.3: P2 (a stage of the call). Spec V4.3.3: an interface; $next is
 * a typed Closure.
 *
 * Preconditions: an interceptor calls $next at most once, and returns a
 * response either way. To end a call from deep inside, it may throw
 * KaririCode\Grpc\Exception\StatusException.
 *
 * Thread-safety: an interceptor is shared by every call; it keeps no
 * per-call state in properties.
 *
 * Side effects: those of the implementation.
 *
 * Example:
 *
 *     final class RequireApiKey implements Interceptor
 *     {
 *         public function intercept(GrpcRequest $request, \Closure $next): GrpcResponse
 *         {
 *             return $request->metadata->get('x-api-key') === $this->key
 *                 ? $next($request)
 *                 : GrpcResponse::error(Status::unauthenticated());
 *         }
 *     }
 *
 * References: gRPC interceptors (grpc-go, grpc-java); ADR-001.
 *
 * @see https://grpc.io/docs/guides/interceptors/
 */
interface Interceptor
{
    /**
     * @param \Closure(GrpcRequest): GrpcResponse $next the rest of the pipeline
     */
    public function intercept(GrpcRequest $request, \Closure $next): GrpcResponse;
}
