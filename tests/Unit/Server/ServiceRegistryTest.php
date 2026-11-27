<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Tests\Unit\Server;

use KaririCode\Grpc\Contract\ServiceHandler;
use KaririCode\Grpc\Enum\CallType;
use KaririCode\Grpc\Message\GrpcRequest;
use KaririCode\Grpc\Message\GrpcResponse;
use KaririCode\Grpc\Server\MethodDescriptor;
use KaririCode\Grpc\Server\ServiceRegistry;
use PHPUnit\Framework\TestCase;

final class ServiceRegistryTest extends TestCase
{
    private function createStubService(string $name): ServiceHandler
    {
        return new class($name) implements ServiceHandler {
            public function __construct(private string $name) {}
            public function serviceName(): string { return $this->name; }
            public function handle(string $method, GrpcRequest $request): GrpcResponse
            {
                return GrpcResponse::ok();
            }
        };
    }

    public function testRegisterAndResolve(): void
    {
        $registry = new ServiceRegistry();
        $svc = $this->createStubService('test.Service');
        $registry->register($svc);

        self::assertSame($svc, $registry->resolve('test.Service'));
        self::assertNull($registry->resolve('nonexistent'));
    }

    public function testHasService(): void
    {
        $registry = new ServiceRegistry();
        $registry->register($this->createStubService('test.Service'));

        self::assertTrue($registry->hasService('test.Service'));
        self::assertFalse($registry->hasService('other'));
    }

    public function testServiceNames(): void
    {
        $registry = new ServiceRegistry();
        $registry->register($this->createStubService('a.Service'));
        $registry->register($this->createStubService('b.Service'));

        self::assertSame(['a.Service', 'b.Service'], $registry->serviceNames());
    }

    public function testMethodDescriptors(): void
    {
        $registry = new ServiceRegistry();
        $svc = $this->createStubService('user.Service');
        $descriptors = [
            new MethodDescriptor('user.Service', 'Get', CallType::Unary),
            new MethodDescriptor('user.Service', 'List', CallType::ServerStreaming),
        ];

        $registry->register($svc, $descriptors);

        $get = $registry->resolveMethod('/user.Service/Get');
        self::assertNotNull($get);
        self::assertSame(CallType::Unary, $get->callType);

        $list = $registry->resolveMethod('/user.Service/List');
        self::assertNotNull($list);
        self::assertSame(CallType::ServerStreaming, $list->callType);

        self::assertNull($registry->resolveMethod('/user.Service/Delete'));
    }

    public function testAllMethods(): void
    {
        $registry = new ServiceRegistry();
        $registry->register($this->createStubService('svc'), [
            new MethodDescriptor('svc', 'A'),
            new MethodDescriptor('svc', 'B'),
        ]);

        self::assertCount(2, $registry->allMethods());
    }
}
