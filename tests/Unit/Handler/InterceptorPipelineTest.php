<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Tests\Unit\Handler;

use KaririCode\Grpc\Contract\Interceptor;
use KaririCode\Grpc\Handler\InterceptorPipeline;
use KaririCode\Grpc\Message\GrpcRequest;
use KaririCode\Grpc\Message\GrpcResponse;
use KaririCode\Grpc\Message\Metadata;
use PHPUnit\Framework\TestCase;

final class InterceptorPipelineTest extends TestCase
{
    public function testEmptyPipeline(): void
    {
        $pipeline = new InterceptorPipeline();
        self::assertTrue($pipeline->isEmpty());
        self::assertSame(0, $pipeline->count());
    }

    public function testPassesThroughToHandler(): void
    {
        $pipeline = new InterceptorPipeline();
        $request = new GrpcRequest('svc', 'Method', ['id' => 1]);

        $response = $pipeline->process($request, fn (GrpcRequest $r) => GrpcResponse::ok($r->payload));

        self::assertTrue($response->isOk());
        self::assertSame(['id' => 1], $response->payload);
    }

    public function testInterceptorModifiesRequest(): void
    {
        $interceptor = new class implements Interceptor {
            public function intercept(GrpcRequest $request, callable $next): GrpcResponse
            {
                return $next($request->withMetadata(
                    $request->metadata->withValue('injected', 'yes'),
                ));
            }
        };

        $pipeline = new InterceptorPipeline();
        $pipeline->pipe($interceptor);

        $captured = null;
        $pipeline->process(
            new GrpcRequest('svc', 'M'),
            function (GrpcRequest $r) use (&$captured) {
                $captured = $r;
                return GrpcResponse::ok();
            },
        );

        self::assertSame('yes', $captured->metadata->get('injected'));
    }

    public function testInterceptorModifiesResponse(): void
    {
        $interceptor = new class implements Interceptor {
            public function intercept(GrpcRequest $request, callable $next): GrpcResponse
            {
                $response = $next($request);
                return $response->withHeaders(new Metadata(['x-trace-id' => 'abc']));
            }
        };

        $pipeline = new InterceptorPipeline();
        $pipeline->pipe($interceptor);

        $response = $pipeline->process(
            new GrpcRequest('svc', 'M'),
            fn () => GrpcResponse::ok(),
        );

        self::assertSame('abc', $response->headers->get('x-trace-id'));
    }

    public function testOrderIsInsideOut(): void
    {
        $order = [];

        $makeInterceptor = fn (string $name) => new class($name, $order) implements Interceptor {
            public function __construct(private string $name, private array &$order) {}
            public function intercept(GrpcRequest $request, callable $next): GrpcResponse
            {
                $this->order[] = "{$this->name}:before";
                $response = $next($request);
                $this->order[] = "{$this->name}:after";
                return $response;
            }
        };

        $pipeline = new InterceptorPipeline();
        $pipeline->pipe($makeInterceptor('A'));
        $pipeline->pipe($makeInterceptor('B'));

        $pipeline->process(new GrpcRequest('svc', 'M'), fn () => GrpcResponse::ok());

        self::assertSame(['A:before', 'B:before', 'B:after', 'A:after'], $order);
    }

    public function testShortCircuit(): void
    {
        $interceptor = new class implements Interceptor {
            public function intercept(GrpcRequest $request, callable $next): GrpcResponse
            {
                return GrpcResponse::error(\KaririCode\Grpc\Message\Status::unauthenticated());
            }
        };

        $pipeline = new InterceptorPipeline();
        $pipeline->pipe($interceptor);

        $handlerCalled = false;
        $response = $pipeline->process(
            new GrpcRequest('svc', 'M'),
            function () use (&$handlerCalled) {
                $handlerCalled = true;
                return GrpcResponse::ok();
            },
        );

        self::assertFalse($handlerCalled);
        self::assertFalse($response->isOk());
    }

    public function testPipeReturnsSelf(): void
    {
        $pipeline = new InterceptorPipeline();
        $interceptor = new class implements Interceptor {
            public function intercept(GrpcRequest $r, callable $n): GrpcResponse { return $n($r); }
        };

        $result = $pipeline->pipe($interceptor);
        self::assertSame($pipeline, $result);
        self::assertSame(1, $pipeline->count());
    }
}
