<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Tests\Unit\Handler;

use KaririCode\Grpc\Contract\ServiceHandler;
use KaririCode\Grpc\Enum\StatusCode;
use KaririCode\Grpc\Exception\ServiceException;
use KaririCode\Grpc\Handler\InterceptorPipeline;
use KaririCode\Grpc\Handler\UnaryHandler;
use KaririCode\Grpc\Message\GrpcRequest;
use KaririCode\Grpc\Message\GrpcResponse;
use KaririCode\Grpc\Server\ServiceRegistry;
use PHPUnit\Framework\TestCase;

final class UnaryHandlerTest extends TestCase
{
    private function createEchoService(string $name = 'echo.Service'): ServiceHandler
    {
        return new class($name) implements ServiceHandler {
            public function __construct(private string $name) {}
            public function serviceName(): string { return $this->name; }
            public function handle(string $method, GrpcRequest $request): GrpcResponse
            {
                return GrpcResponse::ok(['echo' => $method, 'payload' => $request->payload]);
            }
        };
    }

    public function testSuccessfulUnaryCall(): void
    {
        $registry = new ServiceRegistry();
        $registry->register($this->createEchoService());

        $handler = new UnaryHandler($registry);
        $request = new GrpcRequest('echo.Service', 'Ping', ['msg' => 'hello']);

        $response = $handler->handle($request);

        self::assertTrue($response->isOk());
        self::assertSame('Ping', $response->payload['echo']);
    }

    public function testUnimplementedService(): void
    {
        $handler = new UnaryHandler(new ServiceRegistry());
        $response = $handler->handle(new GrpcRequest('unknown.Service', 'Method'));

        self::assertSame(StatusCode::Unimplemented, $response->status->code);
    }

    public function testDeadlineExceeded(): void
    {
        $registry = new ServiceRegistry();
        $registry->register($this->createEchoService());

        $handler = new UnaryHandler($registry);
        $request = new GrpcRequest('echo.Service', 'Slow', deadline: microtime(true) - 1);

        $response = $handler->handle($request);

        self::assertSame(StatusCode::DeadlineExceeded, $response->status->code);
    }

    public function testServiceExceptionTranslated(): void
    {
        $failService = new class implements ServiceHandler {
            public function serviceName(): string { return 'fail.Service'; }
            public function handle(string $method, GrpcRequest $request): GrpcResponse
            {
                throw ServiceException::notFound('User', '123');
            }
        };

        $registry = new ServiceRegistry();
        $registry->register($failService);

        $handler = new UnaryHandler($registry);
        $response = $handler->handle(new GrpcRequest('fail.Service', 'Get'));

        self::assertSame(StatusCode::NotFound, $response->status->code);
        self::assertStringContainsString('User', $response->status->message);
    }

    public function testGenericExceptionBecomesInternal(): void
    {
        $crashService = new class implements ServiceHandler {
            public function serviceName(): string { return 'crash.Service'; }
            public function handle(string $method, GrpcRequest $request): GrpcResponse
            {
                throw new \RuntimeException('segfault');
            }
        };

        $registry = new ServiceRegistry();
        $registry->register($crashService);

        $handler = new UnaryHandler($registry);
        $response = $handler->handle(new GrpcRequest('crash.Service', 'Boom'));

        self::assertSame(StatusCode::Internal, $response->status->code);
    }

    public function testInterceptorPipelineApplied(): void
    {
        $registry = new ServiceRegistry();
        $registry->register($this->createEchoService());

        $pipeline = new InterceptorPipeline();
        $pipeline->pipe(new class implements \KaririCode\Grpc\Contract\Interceptor {
            public function intercept(GrpcRequest $request, callable $next): GrpcResponse
            {
                return $next($request->withPayload([...$request->payload, 'intercepted' => true]));
            }
        });

        $handler = new UnaryHandler($registry, $pipeline);
        $response = $handler->handle(new GrpcRequest('echo.Service', 'Test', ['x' => 1]));

        self::assertTrue($response->payload['payload']['intercepted']);
    }
}
