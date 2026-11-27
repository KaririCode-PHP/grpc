# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the package
follows [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [4.0.0] — 2026-11-27

First published release, under the package name kariricode/grpc (the
repository was a placeholder named grpc-handler). The code is the reviewed
4.0 (kariricode-grpc-v4.0, 2026-03-03), brought to KaririCode Spec V4.3.3
before publication; the changes below are relative to it.

### Security

- **Forged client identities.** `MutualTlsInterceptor` read
  x-forwarded-client-cert and x-client-cert-dn from every client, so any
  client could claim any certificate. The certificate now counts only from
  the transport (an attribute) or from a trusted proxy, and that metadata is
  removed for everyone else (ADR-005).
- **Pinning and validity bypass.** A certificate without fingerprint
  skipped fingerprint pinning, and validity dates were never checked. Pins
  now refuse a missing fingerprint, and the validity period is checked on
  an injected PSR-20 clock.
- **Ambiguous forwarded chains.** The first `Subject="..."` of an XFCC
  header was taken, whatever element it came from, and escapes were not
  read. A header with more than one element is refused, values are read
  with the header's quoting rules, and DN escapes such as `O=a\,CN=admin`
  are kept.
- **Internal errors leaked to clients.** `UnaryHandler` answered any
  exception with INTERNAL carrying the exception's message (SQL, paths). The
  message is now the fixed "Internal error", and the exception goes to an
  optional server-side reporter (ADR-001).
- **Header injection through metadata.** `Metadata` accepted any key and
  value, so CR or LF in a value, or `grpc-status` as a custom key, reached
  the transport. Keys and values are now checked against gRPC over HTTP/2,
  and `grpc-` keys are refused (1910–1912, ADR-006).
- **Client input reflected.** The status for an unknown method repeated the
  service and method names the client sent, and a refused certificate
  repeated its CN. Those messages are fixed now (ADR-003).

### Fixed

- `ProtobufCodec` masked every varint with `0xFFFFFFFFFFFFFFFF`, a float in
  PHP, so every tag and integer was written as zero and decoding its own
  output gave garbage and warnings; it also numbered fields by position,
  read past the end of truncated input and refused 32-bit fields. It is
  removed: messages are bytes, and protobuf services decode them with their
  generated classes (ADR-002).
- The deadline was checked before the call only, against `microtime()`. It
  is checked before and after, on a PSR-20 clock, and a late answer is
  replaced by DEADLINE_EXCEEDED.
- Undeclared methods and streaming methods were dispatched to the service.
  A service registered with method descriptors now receives only its
  declared unary methods; the rest get UNIMPLEMENTED.
- The registry accepted a service twice and descriptors of other services;
  both are refused at registration (1941–1945).
- `InterceptorPipeline::pipe()` changed the pipeline in place, and its
  comment named the last interceptor as the outermost when it was the first.
- `JsonCodec::decode()` returned `[]` for JSON that was not an object; it is
  refused now (1919), as is malformed JSON (1918), and encoding failures are
  reported (1920).
- `GrpcNormalizer::normalize()` relied on `assert()`, which production
  configurations disable; anything other than a `GrpcRequest` is refused
  (1950).
- composer.json required only PHP, while `GrpcNormalizer` imported
  kariricode/router and `MutualTlsInterceptor` kariricode/http. Both are
  dependencies now, with kariricode/exception and psr/clock.
- `StatusCode::tryFromCode()` kept a static map (static state, §18).

### Changed

These break code written against the reviewed 4.0, which was never
published:

- `GrpcRequest` and `GrpcResponse` carry the message bytes (`$message`)
  instead of an array (`$payload`). `GrpcRequest` gains `fromPath()`, the
  peer address, attributes and `withCallType()`, `withPeer()`,
  `withAttribute()`; its deadline is a `DateTimeImmutable` checked with
  `isDeadlineExceeded($now)`.
- `GrpcResponse::unimplemented($service, $method)` is gone; use
  `GrpcResponse::error(Status::unimplemented())`.
  `Status::unimplemented()` takes an optional message instead of the
  service and method.
- Exceptions: `GrpcException` and `ServiceException` are replaced by five
  final classes in kariricode/contract families with codes 1900–1999 from
  `GrpcCode` (ADR-003); a service ends a call with
  `StatusException::failed(Status)`.
- Enums carry their wire values (E-003): `StatusCode` is backed by the
  status number (`->value` and `tryFrom()` replace `numericCode()` and
  `tryFromCode()`), `CallType` by its router method (`tryFrom()` replaces
  `fromRouterMethod()`), `CompressionAlgorithm` by its grpc-encoding name
  (`->value` replaces `wireValue()`, `fromHeader()` replaces
  `fromWireValue()`).
- `Interceptor::intercept()` takes a `\Closure` as `$next`;
  `InterceptorPipeline` is immutable, built with `with()` or a list, and
  `process()` takes a `\Closure`.
- `UnaryHandler::__construct(ServiceRegistry, InterceptorPipeline,
  ClockInterface, ?Closure $onError)`.
- `ServiceRegistry::register()` takes descriptors as variadic arguments and
  gains `describes()`.
- `MutualTlsInterceptor` moved to `Integration\`; the accepted certificate
  is the `CLIENT_CERTIFICATE` attribute instead of the
  x-validated-client-cert metadata.
- `Metadata` refuses invalid keys and values instead of trimming them.

### Added

- `Wire\MessageFramer` and `Wire\MessageFrame` (Length-Prefixed-Message
  with a receive limit), `Wire\Timeout` (grpc-timeout),
  `Wire\StatusMessage` (grpc-message percent-encoding).
- `Integration\ForwardedClientCertificate` (Envoy XFCC) and
  `Integration\Silently`.
- `Server\SystemClock` (PSR-20), the error reporter of `UnaryHandler`, and
  `Status::alreadyExists()` and `Status::resourceExhausted()`.
- Documentation: SPEC-001, ADR-001 to ADR-006, SECURITY.md, CONTRIBUTING.md
  and a quality report.
- Quality gates through KaririCode Devkit (ADR-004), a conformance suite,
  and CI with the actions pinned by commit SHA.

### Removed

- `Codec\ProtobufCodec` (see Fixed).
- `require-dev`: the toolchain comes from `devkit.php`.

[Unreleased]: https://github.com/KaririCode-PHP/grpc/compare/v4.0.0...HEAD
[4.0.0]: https://github.com/KaririCode-PHP/grpc/releases/tag/v4.0.0
