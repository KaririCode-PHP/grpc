# Security Policy

## Supported versions

| Version | Supported |
|---|---|
| 4.x | yes |

## Reporting a vulnerability

Report it privately through GitHub's
[security advisories](https://github.com/KaririCode-PHP/grpc/security/advisories/new),
or by e-mail to walmir.silva@kariricode.org. Please do not open a public
issue.

## Threat model

The package receives calls a transport built from client bytes, runs
application code, and returns statuses and metadata the transport writes
back.

| Threat | Defence |
|---|---|
| Identity forged through metadata: a client sends x-forwarded-client-cert or x-ssl-client-dn itself | proxy metadata counts only when the peer is a trusted proxy (`TlsConfiguration::$trustedProxies`) and is removed for everyone else; the transport's own TLS result travels in an attribute the client cannot set (ADR-005) |
| An ambiguous XFCC chain picked in the attacker's favour | a header with more than one element is refused; DN escapes such as `O=a\,CN=admin` are kept |
| Pinning bypass with a certificate that has no fingerprint; expired certificates | pins refuse a missing fingerprint; the validity period is checked on an injected clock |
| Header injection through metadata or the status message: CR, LF or NUL in a value, `grpc-status` as a custom key | `Metadata` refuses keys outside 0-9, a-z, `_`, `-`, `.`, keys starting with `grpc-`, and non-printable ASCII values; grpc-message is percent-encoded (ADR-006) |
| Memory exhaustion through a huge declared message length | the 4-byte length is checked against the receive limit (4 MiB) before the message is buffered: RESOURCE_EXHAUSTED |
| Internal details leaked in errors: SQL, paths, stack traces in exception messages | unexpected exceptions become INTERNAL with a fixed message; the detail goes to the server-side reporter (ADR-001) |
| Client input reflected in statuses or logs | status messages for unknown services and methods are fixed; exception messages are fixed text (E-047) |
| Undeclared methods reached through a service's `handle()` | a service registered with descriptors only receives its declared unary methods |
| Calls answered after the client gave up | the deadline is checked before and after the call |
| Malformed names in the :path used as router input | service and method names are checked against the protobuf identifier grammar |

Out of scope, by design:

- HTTP/2, TLS and compression: the transport implements them; the framer
  reports the compressed flag and leaves decompression (and its size limit)
  to the transport.
- Authorization beyond the certificate's DN: services decide with the
  certificate and metadata they receive.
- Rate limits and concurrency limits: the transport or an interceptor sets
  them.
- Encoding `Status::$details` as grpc-status-details-bin: it needs protobuf.

## ASVS 5.0, level 1

The relevant requirements are input validation at the protocol boundary
(metadata, names, framing, timeouts), output encoding of header values,
error handling that does not leak internals, and authentication by client
certificates without trusting client-supplied metadata; the threat model
above maps them. The package stores no credentials and keeps no sessions.
