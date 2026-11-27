<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Server;

use KaririCode\Grpc\Contract\ServiceHandler;
use KaririCode\Grpc\Exception\InvalidConfigurationException;
use KaririCode\Grpc\Message\GrpcRequest;

/**
 * The services a server answers, by name, with the methods each declared.
 * A service registered with descriptors is described: the dispatcher
 * refuses its unknown methods with UNIMPLEMENTED. Without descriptors, the
 * service's handle() decides.
 *
 * ARFA 1.3: P5 (wiring checked at bootstrap). Spec V4.3.3: final; filled
 * once at bootstrap, then only read.
 *
 * Preconditions: a service name is registered once (1943), with valid
 * names (1941); each descriptor belongs to that service (1945) and is
 * given once (1944).
 *
 * Thread-safety: register everything before serving; reads are safe to
 * share afterwards.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     $registry = new ServiceRegistry();
 *     $registry->register(new CartService(), new MethodDescriptor('shop.Cart', 'AddItem'), new MethodDescriptor('shop.Cart', 'Checkout'));
 *     $registry->resolveMethod('/shop.Cart/AddItem')?->callType;   // CallType::Unary
 *
 * References: gRPC concepts (service definition); ADR-001.
 *
 * @see https://grpc.io/docs/what-is-grpc/core-concepts/#service-definition
 */
final class ServiceRegistry
{
    /** @var array<string, ServiceHandler> service name => handler */
    private array $services = [];

    /** @var array<string, MethodDescriptor> full path => descriptor */
    private array $methods = [];

    /** @var array<string, bool> service name => registered with descriptors */
    private array $described = [];

    public function register(ServiceHandler $handler, MethodDescriptor ...$descriptors): void
    {
        $name = $handler->serviceName();

        if (preg_match(GrpcRequest::SERVICE_PATTERN, $name) !== 1) {
            throw InvalidConfigurationException::invalidDescriptorService($name);
        }

        if (isset($this->services[$name])) {
            throw InvalidConfigurationException::duplicateService($name);
        }

        $methods = self::methods($name, $descriptors);
        $this->services[$name] = $handler;
        $this->methods = [...$this->methods, ...$methods];
        $this->described[$name] = $methods !== [];
    }

    public function resolve(string $serviceName): ?ServiceHandler
    {
        return $this->services[$serviceName] ?? null;
    }

    public function resolveMethod(string $fullPath): ?MethodDescriptor
    {
        return $this->methods[$fullPath] ?? null;
    }

    /** True when the service was registered with descriptors, so only those methods exist. */
    public function describes(string $serviceName): bool
    {
        return $this->described[$serviceName] ?? false;
    }

    public function hasService(string $serviceName): bool
    {
        return isset($this->services[$serviceName]);
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

    /**
     * @param array<array-key, MethodDescriptor> $descriptors
     *
     * @return array<string, MethodDescriptor> full path => descriptor
     */
    private static function methods(string $service, array $descriptors): array
    {
        $methods = [];

        foreach ($descriptors as $descriptor) {
            if ($descriptor->serviceName !== $service) {
                throw InvalidConfigurationException::foreignMethod($descriptor->fullPath, $service);
            }

            $methods[$descriptor->fullPath] = isset($methods[$descriptor->fullPath]) ? throw InvalidConfigurationException::duplicateMethod($descriptor->fullPath) : $descriptor;
        }

        return $methods;
    }
}
