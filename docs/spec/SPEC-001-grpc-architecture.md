# SPEC-001: gRPC Architecture Specification

## Version

4.0.0 — 2026-03-03

## Abstract

KaririCode\Grpc is a PHP 8.4+ gRPC server framework with interceptor pipeline, dual codec support (JSON/Protobuf), immutable messages, deadline enforcement, 17 status codes, and Router V4.1 integration. It implements the gRPC protocol specification without depending on the C-extension gRPC transport.

---

## 1. Architecture Overview

```
┌─────────────────────────────────────────────────────────────────────┐
│                       InterceptorPipeline                            │
│  [Auth] → [Logging] → [Deadline] → UnaryHandler → Response          │
│                                                                     │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────────────────┐  │
│  │  GrpcRequest  │  │  GrpcResponse │  │  Metadata (headers/trail)│  │
│  │  immutable    │  │  immutable    │  │  immutable, normalized   │  │
│  └──────────────┘  └──────────────┘  └──────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────┐
│                        Service Layer                                 │
│  ServiceHandler contract → MethodDescriptor → ServiceRegistry        │
└─────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────┐
│  Codec: JsonCodec | ProtobufCodec        Integration: GrpcNormalizer │
└─────────────────────────────────────────────────────────────────────┘
```

### Request Flow

```
1. Transport delivers raw gRPC frame
2. Codec decodes payload → GrpcRequest (service, method, payload, metadata)
3. InterceptorPipeline processes request through registered interceptors
4. UnaryHandler resolves service/method via ServiceRegistry
5. ServiceHandler::handle() executes business logic
6. Response flows back through interceptor pipeline
7. Codec encodes GrpcResponse payload
8. Transport sends response with status and trailers
```

---

## 2. Message Types

### GrpcRequest (final readonly)

| Property | Type | Description |
|---|---|---|
| `service` | `string` | Fully qualified service name |
| `method` | `string` | RPC method name |
| `payload` | `array` | Decoded request fields |
| `metadata` | `Metadata` | Request headers |
| `callType` | `CallType` | Unary, ServerStream, ClientStream, BidiStream |
| `deadline` | `?float` | Absolute deadline (microtime) |

### GrpcResponse (final readonly)

| Property | Type | Description |
|---|---|---|
| `status` | `Status` | gRPC status (code + message) |
| `payload` | `array` | Response fields |
| `metadata` | `Metadata` | Response trailers |

### Metadata (final readonly)

Case-insensitive key-value store. Keys ending in `-bin` carry binary data (base64-encoded). All `with*` methods return new instances.

---

## 3. Status Codes

17 standard gRPC status codes implemented as `StatusCode` enum:

| Code | Name | Numeric |
|---|---|---|
| OK | OK | 0 |
| Cancelled | Cancelled | 1 |
| Unknown | Unknown | 2 |
| InvalidArgument | InvalidArgument | 3 |
| DeadlineExceeded | DeadlineExceeded | 4 |
| NotFound | NotFound | 5 |
| AlreadyExists | AlreadyExists | 6 |
| PermissionDenied | PermissionDenied | 7 |
| ResourceExhausted | ResourceExhausted | 8 |
| FailedPrecondition | FailedPrecondition | 9 |
| Aborted | Aborted | 10 |
| OutOfRange | OutOfRange | 11 |
| Unimplemented | Unimplemented | 12 |
| Internal | Internal | 13 |
| Unavailable | Unavailable | 14 |
| DataLoss | DataLoss | 15 |
| Unauthenticated | Unauthenticated | 16 |

---

## 4. Call Types

| CallType | Client Sends | Server Sends |
|---|---|---|
| Unary | 1 message | 1 message |
| ServerStream | 1 message | N messages |
| ClientStream | N messages | 1 message |
| BidiStream | N messages | N messages |

---

## 5. Error Model

| Exception | Carries | Usage |
|---|---|---|
| `GrpcException` | `StatusCode` | Base: any gRPC error |
| `ServiceException` | `StatusCode` + `details[]` | Service-level with structured details |

Static factories on `ServiceException`: `invalidArgument`, `notFound`, `alreadyExists`, `permissionDenied`, `deadlineExceeded`, `resourceExhausted`, `internal`, `unimplemented`.

---

## 6. Router Integration

`GrpcNormalizer` maps gRPC paths to router format:

| gRPC Path | Router Method | Router URI |
|---|---|---|
| `/package.Service/Method` | `GRPC_UNARY` | `/package.Service/Method` |

---

## 7. ARFA 1.3 Compliance

| Principle | Implementation |
|---|---|
| P1 (Immutable State) | GrpcRequest, GrpcResponse, Metadata, Status are `final readonly` |
| P2 (Reactive Flow) | Interceptor pipeline composes cross-cutting concerns |
| P4 (Protocol Agnosticism) | GrpcNormalizer bridges to Router, no HTTP coupling |
