<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Handler;

use KaririCode\Grpc\Contract\ServiceHandler;
use KaririCode\Grpc\Enum\CallType;
use KaririCode\Grpc\Exception\StatusException;
use KaririCode\Grpc\Message\GrpcRequest;
use KaririCode\Grpc\Message\GrpcResponse;
use KaririCode\Grpc\Message\Status;
use KaririCode\Grpc\Server\ServiceRegistry;
use KaririCode\Grpc\Server\SystemClock;
use Psr\Clock\ClockInterface;

/**
 * Serves unary calls: finds the service, runs the interceptors and the
 * service's handle(), and turns every outcome into a response. A call
 * whose deadline has passed gets DEADLINE_EXCEEDED, before the work and
 * after it; an unknown service, a method a described service does not
 * declare, and a streaming method get UNIMPLEMENTED; a StatusException
 * becomes its status; any other exception goes to the error reporter and
 * becomes INTERNAL with a fixed message, so no exception text reaches the
 * client.
 *
 * ARFA 1.3: P2 (the dispatch stage). Spec V4.3.3: final readonly.
 *
 * Preconditions: the registry is complete before the first call. The
 * reporter returns quickly; an exception it throws is dropped.
 *
 * Thread-safety: immutable; safe to share between calls.
 *
 * Side effects: those of the interceptors and services; calls the
 * reporter for unexpected exceptions.
 *
 * Example:
 *
 *     $handler = new UnaryHandler($registry, $pipeline, onError: static fn(\Throwable $e, GrpcRequest $r) => $logger->error($e->getMessage(), ['path' => $r->path()]));
 *     $response = $handler->handle($request);
 *
 * References: gRPC status codes (UNIMPLEMENTED, DEADLINE_EXCEEDED,
 * INTERNAL); gRPC deadlines; ADR-001.
 *
 * @see https://grpc.io/docs/guides/deadlines/
 */
final readonly class UnaryHandler
{
    /** @var \Closure(\Throwable, GrpcRequest): void */
    private \Closure $onError;

    /**
     * @param (\Closure(\Throwable, GrpcRequest): void)|null $onError reporter for unexpected exceptions
     */
    public function __construct(
        private ServiceRegistry $registry,
        private InterceptorPipeline $pipeline = new InterceptorPipeline(),
        private ClockInterface $clock = new SystemClock(),
        ?\Closure $onError = null,
    ) {
        $this->onError = $onError ?? static function (): void {};
    }

    public function handle(GrpcRequest $request): GrpcResponse
    {
        $received = $this->clock->now();

        if ($request->isDeadlineExceeded($received)) {
            return GrpcResponse::error(Status::deadlineExceeded());
        }

        $service = $this->registry->resolve($request->service);

        if ($service === null) {
            return GrpcResponse::error(Status::unimplemented('Unknown service'));
        }

        $refusal = $this->refusal($request);

        if ($refusal !== null) {
            return GrpcResponse::error($refusal);
        }

        $response = $this->dispatch($service, $request);
        $answered = $this->clock->now();

        return $request->isDeadlineExceeded($answered) ? GrpcResponse::error(Status::deadlineExceeded()) : $response;
    }

    /** Why a call to a known service is not served here, or null to serve it. */
    private function refusal(GrpcRequest $request): ?Status
    {
        $descriptor = $this->registry->resolveMethod($request->path());

        if ($descriptor === null) {
            return $this->registry->describes($request->service) ? Status::unimplemented('Unknown method') : null;
        }

        return $descriptor->callType === CallType::Unary ? null : Status::unimplemented('Streaming calls are not served by this handler');
    }

    private function dispatch(ServiceHandler $service, GrpcRequest $request): GrpcResponse
    {
        try {
            return $this->pipeline->process($request, static fn(GrpcRequest $request): GrpcResponse => $service->handle($request->method, $request));
        } catch (StatusException $e) {
            return GrpcResponse::error($e->status);
        } catch (\Throwable $e) {
            $this->report($e, $request);

            return GrpcResponse::error(Status::internal());
        }
    }

    private function report(\Throwable $error, GrpcRequest $request): void
    {
        try {
            ($this->onError)($error, $request);
        } catch (\Throwable) {
            // The reporter itself failed; the client still gets INTERNAL.
        }
    }
}
