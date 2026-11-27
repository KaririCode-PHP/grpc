<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Integration;

use KaririCode\Grpc\Contract\Interceptor;
use KaririCode\Grpc\Message\GrpcRequest;
use KaririCode\Grpc\Message\GrpcResponse;
use KaririCode\Grpc\Message\Metadata;
use KaririCode\Grpc\Message\Status;
use KaririCode\Grpc\Server\SystemClock;
use KaririCode\Http\Security\ClientCertificate;
use KaririCode\Http\Security\TlsConfiguration;
use Psr\Clock\ClockInterface;

/**
 * Applies kariricode/http's client-certificate policy to gRPC calls. The
 * certificate counts only from two sources: the transport's own TLS layer,
 * which puts the verified ClientCertificate in the PEER_CERTIFICATE
 * attribute, or a trusted proxy (TlsConfiguration::$trustedProxies,
 * matched against the request's peer), which forwards it as Envoy's
 * x-forwarded-client-cert or as x-ssl-client-* metadata with
 * x-ssl-client-verify: SUCCESS. From anyone else that metadata is removed
 * before the call goes on, so a client cannot forge an identity. The
 * certificate must be within its validity period on the injected clock,
 * its subject DN allowed, and its fingerprint pinned when pins are set (a
 * certificate without fingerprint is refused). Handlers read the accepted
 * certificate from the CLIENT_CERTIFICATE attribute.
 *
 * ARFA 1.3: P4 (the TLS policy of kariricode/http reused). Spec V4.3.3:
 * final readonly.
 *
 * Preconditions: the transport sets the peer address; the configuration
 * lists the proxies whose metadata may be believed. An XFCC header with
 * more than one element is refused: which element names the client
 * depends on the proxies' settings.
 *
 * Thread-safety: immutable; safe to share.
 *
 * Side effects: none beyond the next steps.
 *
 * Example:
 *
 *     $tls = TlsConfiguration::mutual($cert, $key, $ca)->withTrustedProxies(['10.0.0.0/8'])->withAllowedDns(['CN=billing,O=Acme']);
 *     $pipeline = (new InterceptorPipeline())->with(new MutualTlsInterceptor($tls));
 *     // in a handler:
 *     $request->attribute(MutualTlsInterceptor::CLIENT_CERTIFICATE)?->commonName();
 *
 * References: kariricode/http ClientCertificateMiddleware and its decision
 * record 007 (client-certificate trust);
 * Envoy x-forwarded-client-cert; gRPC authentication guide; ADR-005.
 *
 * @see https://grpc.io/docs/guides/auth/
 */
final readonly class MutualTlsInterceptor implements Interceptor
{
    /** Attribute the transport sets: the ClientCertificate its TLS layer verified. */
    public const string PEER_CERTIFICATE = 'kariricode.grpc.peer_certificate';

    /** Attribute this interceptor sets: the ClientCertificate it accepted. */
    public const string CLIENT_CERTIFICATE = 'kariricode.grpc.client_certificate';

    private const array PROXY_KEYS = [
        'x-forwarded-client-cert', 'x-ssl-client-verify', 'x-ssl-client-dn', 'x-client-dn', 'x-ssl-client-issuer',
        'x-client-issuer-dn', 'x-ssl-client-serial', 'x-ssl-client-sha256', 'x-ssl-client-fingerprint',
        'x-ssl-client-cert', 'x-ssl-client-notbefore', 'x-ssl-client-notafter',
    ];

    public function __construct(
        private TlsConfiguration $config,
        private ClockInterface $clock = new SystemClock(),
    ) {}

    #[\Override]
    public function intercept(GrpcRequest $request, \Closure $next): GrpcResponse
    {
        $verified = self::certificateIn($request->attribute(self::PEER_CERTIFICATE));

        if ($verified === null && $request->peer !== '' && $this->config->isTrustedProxy($request->peer)) {
            return $this->decide(self::fromProxy($request->metadata), $request, $next);
        }

        return $this->decide($verified, $request->withMetadata(self::withoutProxyKeys($request->metadata)), $next);
    }

    private static function certificateIn(mixed $attribute): ?ClientCertificate
    {
        return $attribute instanceof ClientCertificate ? $attribute : null;
    }

    /**
     * @param \Closure(GrpcRequest): GrpcResponse $next
     */
    private function decide(ClientCertificate|Status|null $certificate, GrpcRequest $request, \Closure $next): GrpcResponse
    {
        if ($certificate instanceof Status) {
            return GrpcResponse::error($certificate);
        }

        $refusal = $this->refusal($certificate);

        if ($refusal !== null) {
            return GrpcResponse::error($refusal);
        }

        return $certificate === null || $certificate->isEmpty() ? $next($request) : $next($request->withAttribute(self::CLIENT_CERTIFICATE, $certificate));
    }

    /** Why the call is refused, or null to go on (with the certificate, or without one when it is optional). */
    private function refusal(?ClientCertificate $certificate): ?Status
    {
        if ($certificate === null || $certificate->isEmpty()) {
            return $this->config->peerVerifyMode->requireClientCert() ? Status::unauthenticated('Client certificate required') : null;
        }

        return match (true) {
            !$certificate->isWithinValidityPeriod($this->clock->now()) => Status::unauthenticated('Client certificate is expired or not yet valid'),
            $this->config->hasFingerprintPinning() && !$this->config->isFingerprintTrusted($certificate->fingerprint) => Status::unauthenticated('Client certificate is not pinned'),
            !$this->config->isDnAllowed($certificate->subjectDn) => Status::permissionDenied('Client certificate is not authorized'),
            default => null,
        };
    }

    /** The certificate a trusted proxy forwarded, null for none, or the status that refuses the call. */
    private static function fromProxy(Metadata $metadata): ClientCertificate|Status|null
    {
        $xfcc = $metadata->get('x-forwarded-client-cert');

        if ($xfcc !== null) {
            $elements = ForwardedClientCertificate::elements($xfcc);

            return $elements !== null && count($elements) === 1 ? ForwardedClientCertificate::certificate($elements[0]) : Status::unauthenticated('Forwarded client certificate is malformed or ambiguous');
        }

        $verify = strtoupper($metadata->get('x-ssl-client-verify') ?? '');

        return match ($verify) {
            'SUCCESS' => ClientCertificate::fromProxyHeaders(self::proxyHeaders($metadata)),
            '', 'NONE' => null,
            default => Status::unauthenticated('Client certificate verification failed'),
        };
    }

    /**
     * @return array<string, string>
     */
    private static function proxyHeaders(Metadata $metadata): array
    {
        $headers = [];

        foreach (self::PROXY_KEYS as $key) {
            $value = $metadata->get($key);

            if ($value !== null) {
                $headers[$key] = $value;
            }
        }

        return $headers;
    }

    private static function withoutProxyKeys(Metadata $metadata): Metadata
    {
        foreach (self::PROXY_KEYS as $key) {
            $metadata = $metadata->has($key) ? $metadata->withoutKey($key) : $metadata;
        }

        return $metadata;
    }
}
