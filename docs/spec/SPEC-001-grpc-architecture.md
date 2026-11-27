# SPEC-001 — kariricode/grpc Architecture

**Version:** 4.0.0 · **Spec:** KaririCode V4.3.3 · **ARFA:** 1.3, level 1 · **ASVS:** 5.0, level 1
**Date:** 2026-11-27 (first written 2026-03-03)

## 1. Scope

kariricode/grpc is the server layer of gRPC for PHP 8.4: services and their
method descriptors, an interceptor pipeline, a dispatcher for unary calls
that maps every outcome to a gRPC status and enforces deadlines, the wire
rules a transport needs (length-prefixed messages, grpc-timeout,
grpc-message, metadata), client-certificate trust, and the bridge to
kariricode/router. It does not implement HTTP/2, TLS or compression, and it
does not serve streaming calls in 4.0; a transport (an HTTP/2 server) carries
the calls and uses these types.

Dependencies: `kariricode/exception` ^2.0.2 (family bases, code table),
`kariricode/http` ^4.0 (TLS configuration and client certificates) and
`kariricode/router` ^4.1, both imported only by `src/Integration`, and
`psr/clock` ^1.0. ext-openssl is suggested for forwarded PEMs.

## 2. Types

| Type | Kind | Role |
|---|---|---|
| `Message\GrpcRequest` | final readonly | service, method, message bytes, metadata, call type, deadline, peer, attributes (§3) |
| `Message\GrpcResponse` | final readonly | message bytes, status, headers, trailers |
| `Message\Metadata` | final readonly | custom metadata, validated (§6) |
| `Message\Status` | final readonly | code, message, details |
| `Contract\ServiceHandler` | interface | one service: name and `handle()` |
| `Contract\Interceptor` | interface | a step around every call |
| `Contract\Codec` | interface | array codec named by content subtype |
| `Codec\JsonCodec` | final readonly | application/grpc+json (§7) |
| `Handler\InterceptorPipeline` | final readonly, `Countable` | interceptors, the first outermost |
| `Handler\UnaryHandler` | final readonly | dispatch of unary calls (§4) |
| `Server\ServiceRegistry` | final | services and method descriptors by name |
| `Server\MethodDescriptor` | final readonly | service, method, call type |
| `Server\SystemClock` | final readonly, PSR-20 | wall clock in UTC |
| `Wire\MessageFramer` | final readonly | Length-Prefixed-Message (§5) |
| `Wire\MessageFrame` | final readonly | one message and its compressed flag |
| `Wire\Timeout` | final, static | grpc-timeout |
| `Wire\StatusMessage` | final, static | grpc-message percent-encoding |
| `Integration\MutualTlsInterceptor` | final readonly | client-certificate policy (§8) |
| `Integration\ForwardedClientCertificate` | final, static | Envoy x-forwarded-client-cert |
| `Integration\GrpcNormalizer` | final | router `RequestNormalizer` for calls (§9) |
| `Integration\Silently` | final, static | runs an OpenSSL call with warnings turned into return values |
| `Enum\StatusCode` | backed enum | the 17 gRPC statuses, HTTP mapping |
| `Enum\CallType` | backed enum | unary and streaming kinds, router methods |
| `Enum\CompressionAlgorithm` | backed enum | grpc-encoding names |
| `Exception\GrpcCode` | enum, `ErrorCode` | codes 1900–1999, prefix GRPC (ADR-003) |
| `Exception\StatusException` | final, Runtime | 1900, with the `Status` |
| `Exception\InvalidMessageException` | final, Validation | 1910–1920 |
| `Exception\FrameException` | final, Protocol | 1930–1931, with the `StatusCode` |
| `Exception\InvalidConfigurationException` | final, Configuration | 1940–1945 |
| `Exception\UnsupportedRequestException` | final, Validation | 1950 |

## 3. Calls

A transport builds a `GrpcRequest` from the :path (`fromPath()`), the
message bytes it de-framed, the custom metadata, the deadline
(`Timeout::deadline()` of grpc-timeout) and the peer's IP address. Service
names are dot-separated identifiers, method names one identifier; the peer
is an IP address without port or empty. Attributes hold server-side values
and never come from the client. A handler decodes the message with its
protobuf classes or `JsonCodec` and answers with `GrpcResponse::ok()` or
`GrpcResponse::error(Status)` (ADR-002).

## 4. Dispatch

`UnaryHandler::handle()`:

| Situation | Status |
|---|---|
| deadline already passed | DEADLINE_EXCEEDED; nothing runs |
| unknown service | UNIMPLEMENTED "Unknown service" |
| described service, undeclared method | UNIMPLEMENTED "Unknown method" |
| method declared as streaming | UNIMPLEMENTED |
| `StatusException` from a service or interceptor | its status |
| any other exception | INTERNAL "Internal error"; the reporter gets the exception |
| deadline passed while the call ran | DEADLINE_EXCEEDED; the answer is dropped |
| otherwise | the service's response |

Interceptors run in order, the first outermost; one may answer without
calling `$next` (ADR-001).

## 5. Length-prefixed messages

`MessageFramer::frame()` writes flag 0, the length as 4 bytes big-endian and
the message. `parse()` returns the first message and the bytes used, or null
while it is incomplete; a declared length above `maxMessageSize` (4 MiB) is
RESOURCE_EXHAUSTED (1930) before the message arrives, a flag other than 0 or
1 INTERNAL (1931). A compressed message keeps its flag for the transport to
decompress (ADR-006).

## 6. Metadata, timeouts and status messages

Metadata keys are lower-cased, made of 0-9, a-z, "_", "-" and "." (1910),
and never start with "grpc-" (1911). Values of keys ending in "-bin" are any
bytes; other values are space and printable ASCII (1912). grpc-timeout is
one to eight digits and H, M, S, m, u or n (1917); nanoseconds round up to a
microsecond. `StatusMessage::encode()` percent-encodes every byte outside
space–tilde and "%"; `decode()` leaves malformed escapes as they are.

## 7. JSON codec

`JsonCodec` decodes one JSON object (1919 otherwise; 1918 for malformed
JSON, nesting deeper than 511 objects included) and encodes with Unicode and
slashes unescaped and zero fractions kept (1920 for invalid UTF-8, INF or
NAN). An empty message decodes to an empty array.

## 8. Client certificates

`MutualTlsInterceptor` takes the certificate from the transport's
`PEER_CERTIFICATE` attribute, or from a trusted proxy's metadata (Envoy
x-forwarded-client-cert with one element, or x-ssl-client-* with
x-ssl-client-verify: SUCCESS); for any other request it removes that
metadata. It refuses an expired or not-yet-valid certificate and an unpinned
one when pins are set (UNAUTHENTICATED), a DN outside the allowed list
(PERMISSION_DENIED), and a missing certificate when one is required
(UNAUTHENTICATED). Handlers read the accepted certificate from
`CLIENT_CERTIFICATE` (ADR-005).

## 9. Router integration

`GrpcNormalizer::normalize($request)` returns
`NormalizedRequest($request->callType->routerMethod(), $request->path(),
$request)`, so routes registered for UNARY, SERVER_STREAM, CLIENT_STREAM or
BIDI_STREAM match calls by :path. Anything other than a `GrpcRequest` is
refused (1950).

## 10. ARFA 1.3

| Principle | Where |
|---|---|
| P1 immutable state | requests, responses, metadata, statuses, descriptors, the pipeline |
| P2 reactive flow | bytes → frame → request → interceptors → service → response |
| P4 protocol agnosticism | `GrpcNormalizer` keeps the router free of gRPC types; codecs stay outside the dispatch |
| P5 observability | codes in every exception (1900–1999); statuses on the wire; the error reporter |
