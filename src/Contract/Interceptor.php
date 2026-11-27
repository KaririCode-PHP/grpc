<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Contract;

use KaririCode\Grpc\Message\GrpcRequest;
use KaririCode\Grpc\Message\GrpcResponse;

/**
 * gRPC server interceptor (middleware equivalent).
 *
 * ARFA 1.3 P2 (Reactive Flow Composition): interceptors compose
 * into an inside-out pipeline identical to HTTP middleware (PSR-15).
 * Each interceptor can inspect/modify the request, delegate to the
 * next handler, and inspect/modify the response.
 *
 * Spec V4.0 §1.3: interface without "Interface" suffix.
 *
 * Common use cases: authentication, logging, rate-limiting,
 * tracing (OpenTelemetry), deadline enforcement, validation.
 *
 * @see https://grpc.io/docs/guides/interceptors/
 */
interface Interceptor
{
    /**
     * Process a gRPC request and produce a response.
     *
     * @param GrpcRequest $request  The incoming RPC request
     * @param callable(GrpcRequest): GrpcResponse $next Next interceptor or final handler
     * @return GrpcResponse
     */
    public function intercept(GrpcRequest $request, callable $next): GrpcResponse;
}
