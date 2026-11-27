# ADR-001 — Dispatch: Interceptors, Services, Deadlines and Statuses

**Status:** Accepted
**Date:** 2026-11-27 (first written 2026-03-03)

## Context

A gRPC server receives a call for "/package.Service/Method", runs the steps
every call shares (authentication, logging, limits), runs the method, and
always answers with a status, even when the code behind the method fails.

The reviewed 4.0 (kariricode-grpc-v4.0, 2026-03-03) built the pipeline with
a mutable `pipe()`, typed the next step as `callable`, and its comment said
the last interceptor was the outermost when the first was. `UnaryHandler`
checked the deadline before the call only, against `microtime()`; it
dispatched methods that were not declared and streaming methods to the
service; and it answered any exception with INTERNAL carrying the
exception's message, so a database error reached the client. The status for
an unknown method repeated the service and method names the client sent.

## Decision

- `Handler\InterceptorPipeline` is immutable: `with()` returns a new
  pipeline, the first interceptor is the outermost, and `$next` is a typed
  `\Closure(GrpcRequest): GrpcResponse`.
- `Handler\UnaryHandler` serves unary calls and maps every outcome to a
  status:
  - a deadline that has passed, before the call or when it returns, gives
    DEADLINE_EXCEEDED, read from an injected PSR-20 clock;
  - an unknown service gives UNIMPLEMENTED; for a service registered with
    method descriptors, an undeclared method and a streaming method give
    UNIMPLEMENTED before any application code runs;
  - `Exception\StatusException`, thrown by a service or an interceptor,
    becomes its status;
  - any other exception goes to an optional reporter
    (`Closure(\Throwable, GrpcRequest): void`) and becomes INTERNAL with the
    fixed message "Internal error"; a failing reporter does not change that.
- `Server\ServiceRegistry` refuses, at registration, a malformed service
  name, a service registered twice, a descriptor of another service and a
  method described twice (1941–1945).
- `Message\Status` factories carry fixed messages; the application writes
  its own when it wants the client to read more.
- `Integration\GrpcNormalizer` gives kariricode/router the call type as the
  method (UNARY, SERVER_STREAM, ...) and the :path as the URI, and refuses
  anything that is not a `GrpcRequest` (1950) instead of relying on
  `assert()`.

## Consequences

- No exception text, SQL or stack detail reaches a client; operators read
  them through the reporter.
- A service declared with descriptors cannot be reached through a method it
  did not declare.
- Streaming calls are described (CallType, descriptors, routing) but not
  served in 4.0: the transport that carries them also has to drive them.

## Alternatives Considered

- **Keep `pipe()` mutating the pipeline.** Rejected: a shared pipeline could
  change under concurrent bootstrap code, and Spec V4.3.3 prefers immutable
  values (ARFA P1).
- **Send the exception message as the status message.** Rejected: it leaks
  internals (ASVS 5.0 V16); the reporter keeps the detail on the server.
- **Check the deadline only before the call.** Rejected: a client that gave
  up must not read a late success as if it had arrived in time.

## References

- gRPC status codes; gRPC deadlines; gRPC interceptors.
- PSR-20 (Clock).
- kariricode/router RequestNormalizer (its ADR-002).
- https://grpc.io/docs/guides/deadlines/
