<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Handler;

use KaririCode\Grpc\Contract\Interceptor;
use KaririCode\Grpc\Message\GrpcRequest;
use KaririCode\Grpc\Message\GrpcResponse;

/**
 * The interceptors every call passes through, in order: the first is the
 * outermost, so it sees the request first and the response last. with()
 * returns a new pipeline, so one built at bootstrap can be shared.
 *
 * ARFA 1.3: P1 (immutable), P2 (onion-model composition). Spec V4.3.3:
 * final readonly.
 *
 * Preconditions: none.
 *
 * Thread-safety: immutable; safe to share.
 *
 * Side effects: those of the interceptors and the handler.
 *
 * Example:
 *
 *     $pipeline = (new InterceptorPipeline())
 *         ->with(new MutualTlsInterceptor($tls))   // outermost
 *         ->with(new RequestLogger($logger));
 *     $response = $pipeline->process($request, $handler);
 *
 * References: gRPC interceptors; ADR-001.
 *
 * @see https://grpc.io/docs/guides/interceptors/
 */
final readonly class InterceptorPipeline implements \Countable
{
    /**
     * @param list<Interceptor> $interceptors the outermost first
     */
    public function __construct(private array $interceptors = []) {}

    public function with(Interceptor $interceptor): self
    {
        return new self([...$this->interceptors, $interceptor]);
    }

    /**
     * @param \Closure(GrpcRequest): GrpcResponse $handler the step after the last interceptor
     */
    public function process(GrpcRequest $request, \Closure $handler): GrpcResponse
    {
        $next = $handler;

        foreach (array_reverse($this->interceptors) as $interceptor) {
            $next = static fn(GrpcRequest $request): GrpcResponse => $interceptor->intercept($request, $next);
        }

        return $next($request);
    }

    public function isEmpty(): bool
    {
        return $this->interceptors === [];
    }

    #[\Override]
    public function count(): int
    {
        return count($this->interceptors);
    }
}
