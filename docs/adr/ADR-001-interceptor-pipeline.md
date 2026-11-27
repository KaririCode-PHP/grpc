# ADR-001: Interceptor Pipeline Architecture

## Status

Accepted — V4.0.0

## Date

2026-03-03

## Context

gRPC middleware (called "interceptors") must handle cross-cutting concerns like authentication, logging, deadline enforcement, and compression without modifying service handler logic.

The interceptor pattern in gRPC differs from HTTP middleware: interceptors operate on typed `GrpcRequest`/`GrpcResponse` messages and must propagate gRPC-specific metadata (status codes, trailers, deadlines).

### Prior Art

- **gRPC-Go** — unary and stream interceptors with typed context propagation.
- **gRPC-Java** — `ServerInterceptor` with `ServerCallHandler` delegation.
- **PHP grpc extension** — provides transport only, no interceptor abstraction.

## Decision

Implement an onion-model `InterceptorPipeline` composing `Interceptor` contract implementations:

```
Request → [Auth] → [Logging] → [Deadline] → Handler → [Deadline] → [Logging] → [Auth] → Response
```

Each interceptor receives the request, the next handler callable, and can:
- Modify the request (add metadata, enforce deadlines)
- Short-circuit with a `GrpcResponse` (auth rejection, rate limit)
- Delegate to the next interceptor
- Modify the response (add trailers, record metrics)

### Deadline Enforcement

Interceptors check `GrpcRequest::isDeadlineExceeded()` before and after handler invocation. Exceeded deadlines produce `StatusCode::DeadlineExceeded` without invoking the handler.

### mTLS Interceptor

`MutualTlsInterceptor` validates client certificate metadata injected by the transport layer. It bridges the `kariricode/authenticator` pipeline for certificate-based authentication.

## Consequences

### Positive

- Cross-cutting concerns compose declaratively via pipeline ordering.
- Each interceptor is independently testable.
- Service handlers remain pure business logic.

### Negative

- Pipeline ordering is implicit — misordering (e.g., logging before auth) may leak sensitive data.

## References

- ARFA 1.3 — P2 (Reactive Flow Composition)
- gRPC Core Concepts — Interceptors: https://grpc.io/docs/guides/interceptors/
