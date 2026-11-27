# ADR-002 — The Message Model: Raw Bytes in the Call, Codecs at the Edge

**Status:** Accepted
**Date:** 2026-11-27 (first written 2026-03-03)

## Context

A gRPC message is serialized bytes: protobuf for application/grpc and
application/grpc+proto, JSON for application/grpc+json. Protobuf bytes have
no field names; reading them needs the message's schema, which protoc turns
into generated classes.

The reviewed 4.0 kept the request and response payloads as arrays and
shipped a `ProtobufCodec` that its ADR described as delegating to
google/protobuf. It did not. It masked every varint with
`0xFFFFFFFFFFFFFFFF`, a float in PHP, and the bitwise AND wrote every tag
and integer as zero, so `decode(encode(['name' => 'x']))` returned
`[0 => 0, 15 => 0]` and two warnings. Even with that fixed, it numbered
fields by their position in the array, read past the end of truncated
input, and rejected 32-bit fields. Nothing in the dispatch used either
codec, so a transport had to pick one and fill the array.

## Decision

- `GrpcRequest::$message` and `GrpcResponse::$message` are the serialized
  bytes, after the transport removed the length prefix (ADR-006). A handler
  decodes them with its generated protobuf classes or with a codec.
- `Contract\Codec` stays the interface for array codecs, named by content
  subtype. `Codec\JsonCodec` implements application/grpc+json: a message is
  one JSON object (1919 otherwise), malformed JSON is 1918, data JSON
  cannot hold is 1920, and an empty message decodes to an empty array.
- `ProtobufCodec` is removed.
- Attributes (`GrpcRequest::attribute()`, `withAttribute()`) carry
  server-side values, such as the certificate the transport verified;
  metadata is what the client sent.

## Consequences

- Protobuf services work with the classes protoc generates
  (`$request = new AddItemRequest(); $request->mergeFromString($call->message);`),
  with the full schema, and without a second, partial protobuf
  implementation in this package.
- Interceptors that need the content decode it themselves; most work on
  metadata and statuses alone.
- Encoding an empty PHP array gives `[]`; a handler that must send an empty
  JSON object sends `{}` or an object.

## Alternatives Considered

- **Fix the schema-less ProtobufCodec.** Rejected: without a schema a
  decoder cannot tell a string from bytes or a nested message, an int from a
  zigzag-encoded sint, or a double from a fixed64; any answer would be a
  guess.
- **Depend on google/protobuf.** Rejected for 4.0: applications that use
  protobuf already have it, with their generated classes; the dispatch does
  not need it.
- **Decode in the dispatcher by content type.** Rejected: the dispatcher
  would need the schema of every method.

## References

- gRPC over HTTP/2 (Content-Type, Length-Prefixed-Message).
- Protocol Buffers encoding guide.
- RFC 8259 (JSON).
- https://protobuf.dev/programming-guides/encoding/
