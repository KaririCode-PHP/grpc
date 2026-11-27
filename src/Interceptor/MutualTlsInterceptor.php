<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Interceptor;

use KaririCode\Grpc\Contract\Interceptor;
use KaririCode\Grpc\Message\GrpcRequest;
use KaririCode\Grpc\Message\GrpcResponse;
use KaririCode\Grpc\Message\Status;
use KaririCode\Http\Security\ClientCertificate;
use KaririCode\Http\Security\TlsConfiguration;

/**
 * gRPC interceptor for mutual TLS client certificate validation.
 *
 * In gRPC deployments, the TLS handshake is typically terminated by
 * the gRPC server runtime (or an Envoy/nginx proxy). The client
 * certificate details are then forwarded as metadata entries:
 *
 *   x-client-cert-dn      → Subject DN (set by Envoy/nginx/Istio)
 *   x-client-cert-chain   → URL-encoded PEM chain
 *   x-client-cert-hash    → SHA-256 fingerprint
 *   x-forwarded-client-cert (XFCC) → Envoy composite header
 *
 * This interceptor extracts the certificate from metadata, validates
 * it against the configured policy (DN allowlist, fingerprint pinning),
 * and injects the parsed {@see ClientCertificate} into request metadata
 * under the 'x-validated-client-cert' key for downstream handlers.
 *
 * Returns UNAUTHENTICATED when:
 *   - No client certificate metadata and mTLS is required
 *   - Subject DN not in allowedDns list
 *   - Fingerprint not in trustedFingerprints list
 *
 * ARFA 1.3 P2 (Reactive Flow Composition): interceptor pipeline stage.
 *
 * ARFA 1.3 P4 (Protocol Agnosticism): consumes the same
 * {@see TlsConfiguration} used by HTTP and WebSocket layers.
 *
 * Spec V4.0: final class, stateless.
 *
 * @see https://grpc.io/docs/guides/auth/#using-client-side-ssltls
 * @see https://www.envoyproxy.io/docs/envoy/latest/configuration/http/http_conn_man/headers#x-forwarded-client-cert
 */
final class MutualTlsInterceptor implements Interceptor
{
    /** Metadata key where the validated certificate DN is injected. */
    public const METADATA_KEY = 'x-validated-client-cert';

    public function __construct(
        private readonly TlsConfiguration $config,
    ) {
    }

    public function intercept(GrpcRequest $request, callable $next): GrpcResponse
    {
        $cert = $this->extractCertificate($request);

        // No certificate found
        if ($cert === null || $cert->isEmpty()) {
            if ($this->config->peerVerifyMode->requireClientCert()) {
                return GrpcResponse::error(
                    Status::unauthenticated('Client certificate required for mTLS'),
                );
            }

            // Optional mode — proceed without cert
            return $next($request);
        }

        // DN restriction
        if ($this->config->hasDnRestriction() && !$this->config->isDnAllowed($cert->subjectDn)) {
            return GrpcResponse::error(
                Status::unauthenticated("Client certificate DN not authorized: {$cert->commonName()}"),
            );
        }

        // Fingerprint pinning
        if ($this->config->hasFingerprintPinning() && $cert->fingerprint !== '') {
            if (!$this->config->isFingerprintTrusted($cert->fingerprint)) {
                return GrpcResponse::error(
                    Status::unauthenticated('Client certificate fingerprint not trusted'),
                );
            }
        }

        // Inject validated certificate DN into metadata for downstream handlers
        $enrichedMetadata = $request->metadata->withValue(
            self::METADATA_KEY,
            $cert->subjectDn,
        );

        return $next($request->withMetadata($enrichedMetadata));
    }

    // ── Certificate Extraction ───────────────────────────────────────────

    private function extractCertificate(GrpcRequest $request): ?ClientCertificate
    {
        $metadata = $request->metadata;

        // Try Envoy/Istio XFCC header first (composite format)
        $xfcc = $metadata->get('x-forwarded-client-cert');
        if ($xfcc !== null) {
            return $this->parseXfcc($xfcc);
        }

        // Try individual metadata entries (nginx/custom proxy)
        $subjectDn = $metadata->get('x-client-cert-dn') ?? '';
        if ($subjectDn === '') {
            return null;
        }

        return new ClientCertificate(
            subjectDn: $subjectDn,
            fingerprint: $metadata->get('x-client-cert-hash') ?? '',
        );
    }

    /**
     * Parses the Envoy x-forwarded-client-cert (XFCC) header.
     *
     * Format: By=<uri>;Hash=<sha256>;Subject="<dn>";URI=<uri>;DNS=<dns>
     */
    private function parseXfcc(string $xfcc): ClientCertificate
    {
        $subjectDn = '';
        $fingerprint = '';

        if (preg_match('/Subject="([^"]+)"/', $xfcc, $matches)) {
            $subjectDn = $matches[1];
        }

        if (preg_match('/Hash=([a-fA-F0-9]+)/', $xfcc, $matches)) {
            $fingerprint = $matches[1];
        }

        return new ClientCertificate(
            subjectDn: $subjectDn,
            fingerprint: $fingerprint,
        );
    }
}
