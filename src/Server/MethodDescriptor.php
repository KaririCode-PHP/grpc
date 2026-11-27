<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Server;

use KaririCode\Grpc\Enum\CallType;
use KaririCode\Grpc\Exception\InvalidConfigurationException;
use KaririCode\Grpc\Message\GrpcRequest;

/**
 * One method of a service, as its .proto declares it: the service, the
 * method and the kind of call. Registering descriptors with a service lets
 * the dispatcher refuse unknown methods and streaming calls before any
 * application code runs.
 *
 * ARFA 1.3: P1 (immutable description). Spec V4.3.3: final readonly.
 *
 * Preconditions: $serviceName is dot-separated identifiers (1941),
 * $methodName one identifier (1942).
 *
 * Thread-safety: immutable; safe to share.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     new MethodDescriptor('shop.Cart', 'AddItem');                          // "/shop.Cart/AddItem"
 *     new MethodDescriptor('shop.Cart', 'Watch', CallType::ServerStreaming);
 *
 * References: Protocol Buffers language guide (services); gRPC over HTTP/2
 * (Path); ADR-001.
 *
 * @see https://protobuf.dev/programming-guides/proto3/#services
 */
final readonly class MethodDescriptor
{
    public string $fullPath;

    public function __construct(
        public string $serviceName,
        public string $methodName,
        public CallType $callType = CallType::Unary,
    ) {
        if (preg_match(GrpcRequest::SERVICE_PATTERN, $serviceName) !== 1) {
            throw InvalidConfigurationException::invalidDescriptorService($serviceName);
        }

        if (preg_match(GrpcRequest::METHOD_PATTERN, $methodName) !== 1) {
            throw InvalidConfigurationException::invalidDescriptorMethod($methodName);
        }

        $this->fullPath = '/' . $serviceName . '/' . $methodName;
    }

    public function routerMethod(): string
    {
        return $this->callType->routerMethod();
    }
}
