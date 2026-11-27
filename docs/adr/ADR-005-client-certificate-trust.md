# ADR-005 — Client-Certificate Trust for gRPC Calls

**Status:** Accepted
**Date:** 2026-11-27

## Context

gRPC services often authenticate clients by mutual TLS. TLS ends either in
the process that runs the service, which then knows the verified
certificate, or in a proxy (Envoy, nginx) that forwards what it verified in
metadata. Metadata is also what every client writes freely.

The reviewed 4.0's `MutualTlsInterceptor` read x-forwarded-client-cert and
x-client-cert-dn from every client, so any client could claim any identity.
It skipped fingerprint pinning for a certificate without fingerprint, never
checked validity dates, took the first `Subject="..."` match from an XFCC
header with several elements, wrote the DN into metadata for handlers, and
imported kariricode/http without requiring it.

## Decision

- The certificate counts only from:
  - the transport, which puts the `ClientCertificate` its TLS layer
    verified into the `MutualTlsInterceptor::PEER_CERTIFICATE` attribute;
    attributes never come from the client (ADR-002);
  - a trusted proxy: the request's peer address is in
    `TlsConfiguration::$trustedProxies` (kariricode/http), and the proxy
    forwards Envoy's x-forwarded-client-cert, or x-ssl-client-* metadata
    with x-ssl-client-verify: SUCCESS (NONE or nothing means no certificate;
    anything else is a failed verification).
- For every other request the proxy metadata is removed before the call goes
  on, so neither the policy nor a handler can read a forged value.
- An XFCC header must have exactly one element; with several, which one
  names the client depends on the proxies' settings, so the call is refused.
  Values are read with the header's quoting rules, and only `\"` is
  unescaped, so DN escapes such as `O=a\,CN=admin` survive. A forwarded PEM
  (`Cert`) is read with OpenSSL, which also gives the validity dates;
  otherwise Subject and Hash are used.
- The certificate must be within its validity period on an injected PSR-20
  clock and, when fingerprints are pinned, have a pinned fingerprint (a
  certificate without one is refused): UNAUTHENTICATED otherwise. Its
  subject DN must be allowed: PERMISSION_DENIED otherwise. Without a
  certificate, a configuration that requires one answers UNAUTHENTICATED.
- Handlers read the accepted certificate from the `CLIENT_CERTIFICATE`
  attribute. kariricode/http ^4.0 is a declared dependency, imported only by
  `src/Integration`.

## Consequences

- A client that reaches the server directly cannot impersonate anyone with
  metadata.
- Deployments behind Envoy use `forward_client_cert_details: SANITIZE_SET`
  (one element) and list the proxy's address.
- The policy is kariricode/http's: the same DN list, fingerprint pins and
  trusted proxies serve HTTP and gRPC.

## Alternatives Considered

- **Trust proxy metadata when it is present.** Rejected: that is the forgery
  the reviewed code allowed.
- **Pick the first or last XFCC element.** Rejected: with
  APPEND_FORWARD the order depends on every hop's settings; refusing is the
  safe reading.
- **Put the DN into metadata for handlers (the reviewed design).**
  Rejected: metadata is the client's namespace; attributes are the server's.

## References

- kariricode/http ClientCertificateMiddleware, TlsConfiguration and its
  decision record 007.
- Envoy, x-forwarded-client-cert; RFC 4514 (DN strings); RFC 5280.
- gRPC authentication guide.
- https://www.envoyproxy.io/docs/envoy/latest/configuration/http/http_conn_man/headers#x-forwarded-client-cert
