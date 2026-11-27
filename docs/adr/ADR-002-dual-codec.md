# ADR-002: Dual Codec Strategy (JSON + Protobuf)

## Status

Accepted — V4.0.0

## Date

2026-03-03

## Context

gRPC's canonical serialization is Protocol Buffers (protobuf), but grpc-web and internal tooling frequently use JSON for debugging and interoperability. A PHP gRPC library that only supports protobuf excludes these use cases.

### Trade-offs

| Codec | Throughput | Size | Human-Readable | Tooling |
|---|---|---|---|---|
| Protobuf | Highest | Smallest | No | Requires codegen |
| JSON | Good | Larger | Yes | Universal |

## Decision

Define a `Codec` contract with `encode(array): string` and `decode(string): array` methods. Ship two implementations:

- **`JsonCodec`** — `json_encode`/`json_decode` with strict error checking, configurable flags.
- **`ProtobufCodec`** — delegates to `google/protobuf` PHP extension when available.

The codec is selected per-request via the `content-type` metadata header:
- `application/grpc+json` → JsonCodec
- `application/grpc+proto` → ProtobufCodec (default)

### Extension Point

The `Codec` contract enables custom codecs (MessagePack, FlatBuffers) without modifying the server or interceptor pipeline.

## Consequences

### Positive

- JSON mode enables grpc-web clients and debugging without protobuf toolchain.
- Codec selection is per-request — a single server serves both formats.
- Custom codecs are plug-and-play.

### Negative

- JSON encoding of protobuf-specific types (bytes, int64) requires careful mapping.
- `ProtobufCodec` depends on an optional PHP extension — graceful degradation needed.

## References

- ARFA 1.3 — P1 (Immutable State Transformation): codec output is a new string, not in-place mutation.
- gRPC Wire Format: https://github.com/grpc/grpc/blob/master/doc/PROTOCOL-HTTP2.md
