# ADR-006 — The Wire Format: Length-Prefixed Messages, Timeouts, Status Messages and Metadata

**Status:** Accepted
**Date:** 2026-11-27

## Context

gRPC over HTTP/2 defines what travels in the stream: each message as a
compressed flag, a 4-byte length and the bytes; the deadline as
grpc-timeout; the status as grpc-status and a percent-encoded grpc-message;
custom metadata as headers with lower-case keys, where "grpc-" keys belong
to the protocol and "-bin" keys carry binary values. A transport that hands
the server a raw body needs these rules, and a server that lets values
through unchecked lets them split headers or exhaust memory.

The reviewed 4.0 had none of this: no framing, no timeout parsing, no
status message encoding, and `Metadata` accepted any key and value, trimmed
and lower-cased.

## Decision

- `Wire\MessageFramer` writes and reads Length-Prefixed-Messages. The
  declared length is compared with the receive limit (4 MiB by default)
  from the 5-byte prefix, before the message is buffered: RESOURCE_EXHAUSTED
  (1930). A flag other than 0 or 1 is INTERNAL (1931). A compressed message
  is returned with its flag; decompressing it with the call's grpc-encoding
  (`Enum\CompressionAlgorithm`) is the transport's step.
- `Wire\Timeout` reads grpc-timeout: one to eight digits and a unit (H, M,
  S, m, u, n), nothing else (1917); nanoseconds round up to a microsecond.
  `deadline()` adds it to the transport's receive time.
- `Wire\StatusMessage` percent-encodes every byte outside space–tilde and
  "%" for grpc-message, and decodes leniently, as the specification asks.
- `Message\Metadata` lower-cases keys and accepts only 0-9, a-z, "_", "-"
  and "." (1910); refuses "grpc-" keys (1911), so an application cannot
  forge grpc-status; and accepts only space and printable ASCII in values
  of keys that do not end in "-bin" (1912).
- `GrpcRequest::fromPath()` reads ":path" as "/" service "/" method (1915),
  and the request checks the names (1913, 1914) and the peer address (1916).

## Consequences

- No value that passes these types can add a header or end the trailers
  early.
- A transport on any HTTP/2 server (Swoole, amphp, RoadRunner's raw mode)
  can carry calls with these pieces; it still owns HTTP/2 itself, TLS and
  compression.

## Alternatives Considered

- **Leave framing to every transport (the reviewed scope).** Rejected: the
  rules are small, exact and security-relevant; one tested implementation is
  better than one per transport.
- **Decompress in the framer.** Deferred: it needs ext-zlib and a limit on
  the inflated size; transports that compress already do it.
- **Trim and accept any metadata (the reviewed behaviour).** Rejected: CR,
  LF and NUL in values, and grpc-status as a custom key, are exactly what
  header injection uses.

## References

- gRPC over HTTP/2 (doc/PROTOCOL-HTTP2.md): Length-Prefixed-Message,
  Timeout, Status-Message, Custom-Metadata.
- gRPC compression (doc/compression.md).
- RFC 9113 §8.2.1 (field validity).
- https://github.com/grpc/grpc/blob/master/doc/PROTOCOL-HTTP2.md
