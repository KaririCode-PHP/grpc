<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Handler;

use KaririCode\Grpc\Exception\ServiceException;
use KaririCode\Grpc\Message\GrpcRequest;
use KaririCode\Grpc\Message\GrpcResponse;
use KaririCode\Grpc\Message\Status;
use KaririCode\Grpc\Server\ServiceRegistry;

/**
 * Dispatches unary (request-response) RPCs through the interceptor
 * pipeline to the registered service handler.
 *
 * ARFA 1.3 P2 (Reactive Flow Composition): request → interceptors →
 * handler → response.
 *
 * Spec V4.0: final class.
 *
 * Handles:
 *   1. Deadline enforcement (returns DEADLINE_EXCEEDED before dispatch)
 *   2. Service/method lookup in the {@see ServiceRegistry}
 *   3. Interceptor pipeline execution
 *   4. Exception-to-status translation
 *
 * @see https://grpc.io/docs/what-is-grpc/core-concepts/#unary-rpc
 */
final class UnaryHandler
{
    public function __construct(
        private readonly ServiceRegistry $registry,
        private readonly InterceptorPipeline $pipeline = new InterceptorPipeline(),
    ) {
    }

    public function handle(GrpcRequest $request): GrpcResponse
    {
        // Deadline enforcement before any work
        if ($request->isDeadlineExceeded()) {
            return GrpcResponse::error(Status::deadlineExceeded());
        }

        $service = $this->registry->resolve($request->service);

        if ($service === null) {
            return GrpcResponse::unimplemented($request->service, $request->method);
        }

        try {
            return $this->pipeline->process(
                $request,
                static fn (GrpcRequest $req): GrpcResponse => $service->handle($req->method, $req),
            );
        } catch (ServiceException $e) {
            return GrpcResponse::error(
                new Status($e->statusCode, $e->getMessage(), $e->details),
            );
        } catch (\Throwable $e) {
            return GrpcResponse::error(Status::internal($e->getMessage()));
        }
    }
}
