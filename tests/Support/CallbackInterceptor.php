<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Tests\Support;

use KaririCode\Grpc\Contract\Interceptor;
use KaririCode\Grpc\Message\GrpcRequest;
use KaririCode\Grpc\Message\GrpcResponse;

/**
 * An interceptor whose body is a closure, for tests that need a few
 * different steps without a class each.
 */
final readonly class CallbackInterceptor implements Interceptor
{
    /**
     * @param \Closure(GrpcRequest, \Closure(GrpcRequest): GrpcResponse): GrpcResponse $body
     */
    public function __construct(private \Closure $body) {}

    #[\Override]
    public function intercept(GrpcRequest $request, \Closure $next): GrpcResponse
    {
        return ($this->body)($request, $next);
    }
}
