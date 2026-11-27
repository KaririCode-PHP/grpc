# KaririCode gRPC

PHP 8.4+ gRPC server framework with interceptor pipeline, dual codec support (JSON/Protobuf), immutable messages, deadline enforcement, 17 status codes, and Router V4.1 integration.

## Overview

KaririCode gRPC provides the server-side application layer for gRPC services. It handles service registration, interceptor composition, codec selection, deadline enforcement, and structured error responses. Transport (HTTP/2 framing) is delegated to infrastructure.

## Features

- **Interceptor pipeline** — onion-model middleware for auth, logging, deadlines, compression
- **Dual codec** — JSON and Protobuf with per-request content-type selection
- **Immutable messages** — `GrpcRequest`, `GrpcResponse`, `Metadata`, `Status` are `final readonly`
- **17 status codes** — full gRPC specification compliance
- **4 call types** — Unary, ServerStream, ClientStream, BidiStream
- **Deadline enforcement** — automatic timeout detection before and after handler invocation
- **Structured errors** — `ServiceException` with typed details (google.rpc.Status)
- **Router integration** — `GrpcNormalizer` bridges to Router V4.1 protocol-agnostic matching
- **mTLS interceptor** — client certificate validation via kariricode/authenticator
- **Zero external deps** — only `kariricode/contract` required

## Installation

```bash
composer require kariricode/grpc
```

## Quick Start

```php
use KaririCode\Grpc\Enum\StatusCode;
use KaririCode\Grpc\Handler\UnaryHandler;
use KaririCode\Grpc\Interceptor\InterceptorPipeline;
use KaririCode\Grpc\Message\{GrpcRequest, GrpcResponse, Metadata, Status};

// Define a service handler
$handler = new UnaryHandler(function (GrpcRequest $request): GrpcResponse {
    $name = $request->payload['name'] ?? 'World';
    return new GrpcResponse(
        Status::ok(),
        ['message' => "Hello, {$name}!"],
    );
});

// Compose with interceptors
$pipeline = new InterceptorPipeline([$authInterceptor, $loggingInterceptor]);
$response = $pipeline->handle($request, $handler);
```

## Interceptors

```php
use KaririCode\Grpc\Contract\Interceptor;

final class LoggingInterceptor implements Interceptor
{
    public function intercept(GrpcRequest $request, callable $next): GrpcResponse
    {
        $start = microtime(true);
        $response = $next($request);
        $elapsed = microtime(true) - $start;
        // log: $request->path(), $response->status->code, $elapsed
        return $response;
    }
}
```

## Status Codes

```php
use KaririCode\Grpc\Enum\StatusCode;
use KaririCode\Grpc\Exception\ServiceException;

// Structured error with details
throw ServiceException::invalidArgument('email', 'must be valid');
throw ServiceException::notFound('User', '42');
throw ServiceException::deadlineExceeded(5.5);
```

## Router Integration

```php
use KaririCode\Grpc\Integration\GrpcNormalizer;

$normalizer = new GrpcNormalizer();
$router->route('GRPC_UNARY', '/mypackage.UserService/GetUser', $handler);
```

## Error Handling

| Exception Factory | Status Code | When |
|---|---|---|
| `invalidArgument(field, reason)` | InvalidArgument (3) | Bad input |
| `notFound(resource, id)` | NotFound (5) | Missing entity |
| `alreadyExists(resource, id)` | AlreadyExists (6) | Duplicate |
| `permissionDenied(resource, action)` | PermissionDenied (7) | Forbidden |
| `deadlineExceeded(seconds)` | DeadlineExceeded (4) | Timeout |
| `internal(message, cause)` | Internal (13) | Server error |
| `unimplemented(service, method)` | Unimplemented (12) | Missing RPC |

## Testing

```bash
./vendor/bin/phpunit
```

## Documentation

| Document | Location |
|---|---|
| ADR-001: Interceptor Pipeline | `docs/adr/ADR-001-interceptor-pipeline.md` |
| ADR-002: Dual Codec Strategy | `docs/adr/ADR-002-dual-codec.md` |
| SPEC-001: Architecture | `docs/spec/SPEC-001-grpc-architecture.md` |

## License

MIT License. See [LICENSE](LICENSE) for details.
