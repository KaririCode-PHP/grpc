<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Server;

use KaririCode\Grpc\Enum\CallType;

/**
 * Immutable descriptor for a single gRPC method.
 *
 * ARFA 1.3 P1 (Immutable State Transformation): final readonly class.
 *
 * Spec V4.0: pure data carrier with no behavior beyond accessors.
 *
 * Holds metadata about an RPC method: its name, call type, and the
 * fully qualified gRPC path used for routing.
 *
 * @see https://grpc.io/docs/what-is-grpc/core-concepts/#service-definition
 */
final readonly class MethodDescriptor
{
    public string $fullPath;

    /**
     * @param string   $serviceName Fully qualified service name (e.g. "myapp.v1.UserService")
     * @param string   $methodName  RPC method name (e.g. "GetUser")
     * @param CallType $callType    RPC cardinality
     */
    public function __construct(
        public string $serviceName,
        public string $methodName,
        public CallType $callType = CallType::Unary,
    ) {
        $this->fullPath = "/{$serviceName}/{$methodName}";
    }

    /**
     * The Router method string for this descriptor's call type.
     */
    public function routerMethod(): string
    {
        return $this->callType->routerMethod();
    }
}
