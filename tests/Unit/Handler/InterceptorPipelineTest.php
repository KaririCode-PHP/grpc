<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Tests\Unit\Handler;

use KaririCode\Grpc\Handler\InterceptorPipeline;
use KaririCode\Grpc\Message\GrpcRequest;
use KaririCode\Grpc\Message\GrpcResponse;
use KaririCode\Grpc\Message\Status;
use KaririCode\Grpc\Tests\Support\CallbackInterceptor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(InterceptorPipeline::class)]
final class InterceptorPipelineTest extends TestCase
{
    #[Test]
    public function theFirstInterceptorIsTheOutermost(): void
    {
        /** @var \ArrayObject<int, string> $trace */
        $trace = new \ArrayObject();
        $step = static fn(string $name): CallbackInterceptor => new CallbackInterceptor(static function (GrpcRequest $request, \Closure $next) use ($trace, $name): GrpcResponse {
            $trace[] = $name . ' in';
            $response = $next($request->withMessage($request->message . $name));
            $trace[] = $name . ' out';

            return $response->withMessage($response->message . '<' . $name);
        });
        $pipeline = (new InterceptorPipeline())->with($step('a'))->with($step('b'));

        $response = $pipeline->process(new GrpcRequest('a.B', 'C', '>'), static function (GrpcRequest $request) use ($trace): GrpcResponse {
            $trace[] = 'handler';

            return GrpcResponse::ok($request->message);
        });

        self::assertSame(['a in', 'b in', 'handler', 'b out', 'a out'], $trace->getArrayCopy());
        self::assertSame('>ab<b<a', $response->message);
        self::assertCount(2, $pipeline);
        self::assertFalse($pipeline->isEmpty());
    }

    #[Test]
    public function anInterceptorCanAnswerWithoutTheHandler(): void
    {
        /** @var \ArrayObject<int, bool> $handled */
        $handled = new \ArrayObject();
        $pipeline = new InterceptorPipeline([new CallbackInterceptor(static fn(): GrpcResponse => GrpcResponse::error(Status::unauthenticated()))]);

        $response = $pipeline->process(new GrpcRequest('a.B', 'C'), static function () use ($handled): GrpcResponse {
            $handled[] = true;

            return GrpcResponse::ok();
        });

        self::assertFalse($response->isOk());
        self::assertCount(0, $handled);
    }

    #[Test]
    public function withReturnsANewPipeline(): void
    {
        $empty = new InterceptorPipeline();
        $one = $empty->with(new CallbackInterceptor(static fn(GrpcRequest $request, \Closure $next): GrpcResponse => $next($request)));

        self::assertTrue($empty->isEmpty());
        self::assertCount(0, $empty);
        self::assertCount(1, $one);
        self::assertSame('m', $empty->process(new GrpcRequest('a.B', 'C', 'm'), static fn(GrpcRequest $request): GrpcResponse => GrpcResponse::ok($request->message))->message);
    }
}
