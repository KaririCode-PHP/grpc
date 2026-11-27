<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Handler;

use KaririCode\Grpc\Contract\Interceptor;
use KaririCode\Grpc\Message\GrpcRequest;
use KaririCode\Grpc\Message\GrpcResponse;

/**
 * Inside-out interceptor pipeline for gRPC requests.
 *
 * ARFA 1.3 P2 (Reactive Flow Composition): composes interceptors
 * into a single callable, identical to PSR-15 middleware dispatch
 * but typed for gRPC request/response.
 *
 * Spec V4.0: final class.
 *
 * @see https://grpc.io/docs/guides/interceptors/
 */
final class InterceptorPipeline
{
    /** @var list<Interceptor> */
    private array $interceptors = [];

    public function pipe(Interceptor $interceptor): self
    {
        $this->interceptors[] = $interceptor;
        return $this;
    }

    /**
     * Execute the pipeline with a final handler.
     *
     * @param callable(GrpcRequest): GrpcResponse $handler The service method handler
     */
    public function process(GrpcRequest $request, callable $handler): GrpcResponse
    {
        // Build from inside-out: last piped = outermost
        $next = $handler;

        foreach (array_reverse($this->interceptors) as $interceptor) {
            $next = static fn (GrpcRequest $req): GrpcResponse => $interceptor->intercept($req, $next);
        }

        return $next($request);
    }

    public function isEmpty(): bool
    {
        return $this->interceptors === [];
    }

    public function count(): int
    {
        return count($this->interceptors);
    }
}
