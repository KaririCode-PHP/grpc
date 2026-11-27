<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Tests\Unit\Handler;

use KaririCode\Grpc\Enum\CallType;
use KaririCode\Grpc\Enum\StatusCode;
use KaririCode\Grpc\Exception\StatusException;
use KaririCode\Grpc\Handler\InterceptorPipeline;
use KaririCode\Grpc\Handler\UnaryHandler;
use KaririCode\Grpc\Message\GrpcRequest;
use KaririCode\Grpc\Message\GrpcResponse;
use KaririCode\Grpc\Message\Status;
use KaririCode\Grpc\Server\MethodDescriptor;
use KaririCode\Grpc\Server\ServiceRegistry;
use KaririCode\Grpc\Tests\Support\CallbackInterceptor;
use KaririCode\Grpc\Tests\Support\CallbackService;
use KaririCode\Grpc\Tests\Support\FixedClock;
use KaririCode\Grpc\Tests\Support\SteppingClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UnaryHandler::class)]
final class UnaryHandlerTest extends TestCase
{
    private const string NOW = '2026-11-27 12:00:00';

    #[Test]
    public function aCallGoesThroughTheInterceptorsToTheService(): void
    {
        $service = new CallbackService('shop.Cart', ['AddItem' => static fn(GrpcRequest $request): GrpcResponse => GrpcResponse::ok('added ' . $request->message)]);
        $pipeline = (new InterceptorPipeline())->with(new CallbackInterceptor(static fn(GrpcRequest $request, \Closure $next): GrpcResponse => $next($request->withMessage(strtoupper($request->message)))));

        $response = $this->handler($service, pipeline: $pipeline)->handle(new GrpcRequest('shop.Cart', 'AddItem', 'sku'));

        self::assertTrue($response->isOk());
        self::assertSame('added SKU', $response->message);
        self::assertSame(['AddItem'], $service->calls);
    }

    #[Test]
    public function anUnknownServiceIsUnimplemented(): void
    {
        $response = $this->handler(new CallbackService('shop.Cart'))->handle(new GrpcRequest('shop.Orders', 'List'));

        self::assertSame(StatusCode::Unimplemented, $response->status->code);
        self::assertSame('Unknown service', $response->status->message);
    }

    #[Test]
    public function aDescribedServiceNeverSeesAnUnknownMethod(): void
    {
        $service = new CallbackService('shop.Cart');
        $handler = $this->handler($service, [new MethodDescriptor('shop.Cart', 'AddItem')]);

        $unknown = $handler->handle(new GrpcRequest('shop.Cart', 'Delete'));
        $known = $handler->handle(new GrpcRequest('shop.Cart', 'AddItem'));

        self::assertSame(StatusCode::Unimplemented, $unknown->status->code);
        self::assertSame('Unknown method', $unknown->status->message);
        self::assertTrue($known->isOk());
        self::assertSame(['AddItem'], $service->calls);
    }

    #[Test]
    public function anUndescribedServiceDecidesItsMethods(): void
    {
        $service = new CallbackService('shop.Cart');

        $response = $this->handler($service)->handle(new GrpcRequest('shop.Cart', 'Anything', 'm'));

        self::assertTrue($response->isOk());
        self::assertSame(['Anything'], $service->calls);
    }

    #[Test]
    public function streamingMethodsAreNotServedHere(): void
    {
        $service = new CallbackService('shop.Cart');
        $handler = $this->handler($service, [new MethodDescriptor('shop.Cart', 'Watch', CallType::ServerStreaming)]);

        $response = $handler->handle(new GrpcRequest('shop.Cart', 'Watch'));

        self::assertSame(StatusCode::Unimplemented, $response->status->code);
        self::assertSame('Streaming calls are not served by this handler', $response->status->message);
        self::assertSame([], $service->calls);
    }

    #[Test]
    public function aCallPastItsDeadlineIsNotStarted(): void
    {
        $service = new CallbackService('shop.Cart');
        $request = (new GrpcRequest('shop.Cart', 'AddItem'))->withDeadline(new \DateTimeImmutable(self::NOW, new \DateTimeZone('UTC')));

        $response = $this->handler($service)->handle($request);

        self::assertSame(StatusCode::DeadlineExceeded, $response->status->code);
        self::assertSame([], $service->calls);
    }

    #[Test]
    public function aCallThatOutlivesItsDeadlineEndsWithDeadlineExceeded(): void
    {
        $service = new CallbackService('shop.Cart');
        $deadline = new \DateTimeImmutable('2026-11-27 12:00:01', new \DateTimeZone('UTC'));
        $handler = new UnaryHandler(self::registry($service), clock: new SteppingClock('2026-11-27 12:00:00', '2026-11-27 12:00:02'));

        $response = $handler->handle((new GrpcRequest('shop.Cart', 'AddItem', 'late'))->withDeadline($deadline));

        self::assertSame(StatusCode::DeadlineExceeded, $response->status->code);
        self::assertSame('', $response->message, 'the late answer is dropped');
        self::assertSame(['AddItem'], $service->calls);
    }

    #[Test]
    public function aCallWithinItsDeadlineIsServed(): void
    {
        $deadline = new \DateTimeImmutable('2026-11-27 12:00:01', new \DateTimeZone('UTC'));
        $handler = new UnaryHandler(self::registry(new CallbackService('shop.Cart')), clock: new SteppingClock('2026-11-27 12:00:00', '2026-11-27 12:00:00.999999'));

        self::assertTrue($handler->handle((new GrpcRequest('shop.Cart', 'AddItem'))->withDeadline($deadline))->isOk());
    }

    #[Test]
    public function aStatusExceptionBecomesItsStatus(): void
    {
        $status = Status::notFound('No such cart');
        $fromService = new CallbackService('shop.Cart', ['Get' => static fn(): GrpcResponse => throw StatusException::failed($status)]);
        $fromInterceptor = (new InterceptorPipeline())->with(new CallbackInterceptor(static fn(): GrpcResponse => throw StatusException::failed(Status::permissionDenied())));

        self::assertSame($status, $this->handler($fromService)->handle(new GrpcRequest('shop.Cart', 'Get'))->status);
        self::assertSame(StatusCode::PermissionDenied, $this->handler(new CallbackService(), pipeline: $fromInterceptor)->handle(new GrpcRequest('shop.Cart', 'Get'))->status->code);
    }

    #[Test]
    public function anyOtherExceptionIsReportedAndBecomesAFixedInternalError(): void
    {
        /** @var \ArrayObject<int, array{\Throwable, GrpcRequest}> $reports */
        $reports = new \ArrayObject();
        $failure = new \LogicException('SQLSTATE[42S02]: table secrets missing');
        $service = new CallbackService('shop.Cart', ['Get' => static fn(): GrpcResponse => throw $failure]);
        $request = new GrpcRequest('shop.Cart', 'Get');
        $handler = new UnaryHandler(self::registry($service), clock: new FixedClock(self::NOW), onError: static function (\Throwable $error, GrpcRequest $failed) use ($reports): void {
            $reports[] = [$error, $failed];
        });

        $response = $handler->handle($request);

        self::assertSame(StatusCode::Internal, $response->status->code);
        self::assertSame('Internal error', $response->status->message, 'no exception text reaches the client');
        self::assertSame([[$failure, $request]], $reports->getArrayCopy());
    }

    #[Test]
    public function aFailingReporterStillAnswersInternal(): void
    {
        $service = new CallbackService('shop.Cart', ['Get' => static fn(): GrpcResponse => throw new \RuntimeException('boom')]);
        $handler = new UnaryHandler(self::registry($service), onError: static fn(): never => throw new \RuntimeException('the logger is down'));

        self::assertSame(StatusCode::Internal, $handler->handle(new GrpcRequest('shop.Cart', 'Get'))->status->code);
        self::assertSame(StatusCode::Internal, $this->handler($service)->handle(new GrpcRequest('shop.Cart', 'Get'))->status->code, 'and without a reporter');
    }

    /**
     * @param list<MethodDescriptor> $descriptors
     */
    private function handler(CallbackService $service, array $descriptors = [], InterceptorPipeline $pipeline = new InterceptorPipeline()): UnaryHandler
    {
        return new UnaryHandler(self::registry($service, ...$descriptors), $pipeline, new FixedClock(self::NOW));
    }

    private static function registry(CallbackService $service, MethodDescriptor ...$descriptors): ServiceRegistry
    {
        $registry = new ServiceRegistry();
        $registry->register($service, ...$descriptors);

        return $registry;
    }
}
