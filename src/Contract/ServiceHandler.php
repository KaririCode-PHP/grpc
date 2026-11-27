<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Contract;

use KaririCode\Grpc\Message\GrpcRequest;
use KaririCode\Grpc\Message\GrpcResponse;

/**
 * One gRPC service: its package-qualified name and the code behind its
 * methods. Server\ServiceRegistry finds it by name, and
 * Handler\UnaryHandler calls handle() with the method of the request.
 *
 * ARFA 1.3: P2 (the end of the call pipeline). Spec V4.3.3: an interface.
 *
 * Preconditions: serviceName() is dot-separated identifiers and never
 * changes. handle() answers a method it does not know with
 * Status::unimplemented(); a service registered with method descriptors
 * never sees an unknown method.
 *
 * Thread-safety: one instance serves every call; per-call state stays in
 * locals.
 *
 * Side effects: those of the service.
 *
 * Example:
 *
 *     final class CartService implements ServiceHandler
 *     {
 *         public function serviceName(): string { return 'shop.Cart'; }
 *
 *         public function handle(string $method, GrpcRequest $request): GrpcResponse
 *         {
 *             return match ($method) {
 *                 'AddItem' => $this->addItem($request),
 *                 default => GrpcResponse::error(Status::unimplemented()),
 *             };
 *         }
 *     }
 *
 * References: gRPC concepts (service definition); ADR-001.
 *
 * @see https://grpc.io/docs/what-is-grpc/core-concepts/#service-definition
 */
interface ServiceHandler
{
    public function serviceName(): string;

    public function handle(string $method, GrpcRequest $request): GrpcResponse;
}
