<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Tests\Support;

use KaririCode\Grpc\Contract\ServiceHandler;
use KaririCode\Grpc\Message\GrpcRequest;
use KaririCode\Grpc\Message\GrpcResponse;

/**
 * A service whose methods are closures, recording every call it serves.
 */
final class CallbackService implements ServiceHandler
{
    /** @var list<string> the methods called, in order */
    public private(set) array $calls = [];

    /**
     * @param array<string, \Closure(GrpcRequest): GrpcResponse> $methods
     */
    public function __construct(
        private readonly string $name = 'shop.Cart',
        private readonly array $methods = [],
    ) {}

    #[\Override]
    public function serviceName(): string
    {
        return $this->name;
    }

    #[\Override]
    public function handle(string $method, GrpcRequest $request): GrpcResponse
    {
        $this->calls[] = $method;
        $body = $this->methods[$method] ?? static fn(GrpcRequest $request): GrpcResponse => GrpcResponse::ok($request->message);

        return $body($request);
    }
}
