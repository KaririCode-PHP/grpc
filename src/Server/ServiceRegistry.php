<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Server;

use KaririCode\Grpc\Contract\ServiceHandler;

/**
 * Registry for gRPC service handlers.
 *
 * ARFA 1.3 P4 (Protocol Agnosticism): services are registered
 * by fully qualified name so the Router can dispatch them via
 * the GrpcNormalizer.
 *
 * Spec V4.0: final class.
 *
 * @see https://grpc.io/docs/what-is-grpc/core-concepts/#service-definition
 */
final class ServiceRegistry
{
    /** @var array<string, ServiceHandler> serviceName → handler */
    private array $services = [];

    /** @var array<string, MethodDescriptor> fullPath → descriptor */
    private array $methods = [];

    /**
     * Register a service handler with its method descriptors.
     *
     * @param ServiceHandler        $handler     The service implementation
     * @param list<MethodDescriptor> $descriptors Method metadata (call types)
     */
    public function register(ServiceHandler $handler, array $descriptors = []): void
    {
        $name = $handler->serviceName();
        $this->services[$name] = $handler;

        foreach ($descriptors as $descriptor) {
            $this->methods[$descriptor->fullPath] = $descriptor;
        }
    }

    public function resolve(string $serviceName): ?ServiceHandler
    {
        return $this->services[$serviceName] ?? null;
    }

    public function resolveMethod(string $fullPath): ?MethodDescriptor
    {
        return $this->methods[$fullPath] ?? null;
    }

    /**
     * @return list<MethodDescriptor>
     */
    public function allMethods(): array
    {
        return array_values($this->methods);
    }

    /**
     * @return list<string>
     */
    public function serviceNames(): array
    {
        return array_keys($this->services);
    }

    public function hasService(string $serviceName): bool
    {
        return isset($this->services[$serviceName]);
    }
}
