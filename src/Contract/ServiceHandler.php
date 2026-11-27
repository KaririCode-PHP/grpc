<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Contract;

use KaririCode\Grpc\Message\GrpcRequest;
use KaririCode\Grpc\Message\GrpcResponse;

/**
 * Contract for gRPC service implementations.
 *
 * ARFA 1.3 P4 (Protocol Agnosticism): services are registered
 * by fully qualified gRPC path (package.Service/Method) so the
 * Router can dispatch them alongside HTTP/WebSocket/SSE handlers.
 *
 * Spec V4.0 §1.3: interface without "Interface" suffix.
 *
 * Implementations bind one or more RPC methods and process requests
 * through the service's business logic.
 *
 * @see https://grpc.io/docs/what-is-grpc/core-concepts/#service-definition
 */
interface ServiceHandler
{
    /**
     * Fully qualified gRPC service name (e.g. "myapp.v1.UserService").
     */
    public function serviceName(): string;

    /**
     * Handle an RPC call to a specific method.
     *
     * @param string      $method  Method name (e.g. "GetUser")
     * @param GrpcRequest $request The decoded request message
     * @return GrpcResponse
     */
    public function handle(string $method, GrpcRequest $request): GrpcResponse;
}
