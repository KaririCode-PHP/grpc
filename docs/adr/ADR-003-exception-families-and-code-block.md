# ADR-003 — Exception Families and the Code Block 1900–1999

**Status:** Accepted
**Date:** 2026-11-27

## Context

The reviewed 4.0 had a non-final `GrpcException` with a public constructor
and a `ServiceException` whose factories built messages from their
arguments ("Invalid argument 'email': ..."). KaririCode Spec V4.3.3 §8.4
identifies a failure by its kariricode/contract family plus a four-digit
code from a block the ecosystem registry (kariricode/exception,
CODE-BLOCKS.md) gives one package; E-047 forbids repeating input in
messages.

A gRPC server has two kinds of codes: the status a client receives (0–16,
`Enum\StatusCode`) and what went wrong inside the server. They are kept
apart.

## Decision

- The registry gives kariricode/grpc block 1900–1999 under the prefix GRPC.
  kariricode/exception 2.0.2 lists the block, so the constraint is
  `^2.0.2`.
- `GrpcCode` (an `ErrorCode` enum) holds the codes:

  | Range | Area | Class (family) |
  |---|---|---|
  | 1900–1909 | a call the application ended with a status | `StatusException` (Runtime), with the `Status` |
  | 1910–1929 | values from a client or the transport: metadata, names, :path, peer, grpc-timeout, JSON | `InvalidMessageException` (Validation) |
  | 1930–1939 | length-prefixed messages | `FrameException` (Protocol), with the `StatusCode` to answer |
  | 1940–1949 | configuration: limits, method descriptors, the registry | `InvalidConfigurationException` (Configuration) |
  | 1950–1959 | the router normalizer | `UnsupportedRequestException` (Validation) |

- Five final classes on the kariricode/exception bases, built by named
  constructors only, one per code.
- Messages are fixed text. The values in them come from the application's
  configuration (a limit, a service or method name) or are a status code
  name, and go through `quote()`. Nothing a client sent appears.
- `StatusException::failed(Status)` replaces `ServiceException`: the status
  message and details are the application's, written for the client.

## Consequences

- Logs and handlers branch on `$e->errorCode` (a `GrpcCode`) and the family;
  `FrameException::$status` and `StatusException::$status` say what the
  client receives.
- Codes are stable API: a code is never reused or renumbered, and a new one
  takes the next free number in its range.

## Alternatives Considered

- **Use the gRPC status code as the error code.** Rejected: one status
  (INVALID_ARGUMENT, INTERNAL) covers many different causes; the GRPC codes
  tell them apart.
- **One ServiceException factory per gRPC status.** Rejected: `Status`
  already has those factories; one exception that carries any status keeps
  the catalogue small.
- **Messages with the offending value.** Rejected: E-047; the value is in
  the request the application already has.

## References

- KaririCode Spec V4.3.3 §8.4, E-047; kariricode/exception CODE-BLOCKS.md.
- gRPC status codes.
- https://grpc.io/docs/guides/status-codes/
