<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Tests\Unit\Interceptor;

use KaririCode\Grpc\Enum\StatusCode;
use KaririCode\Grpc\Interceptor\MutualTlsInterceptor;
use KaririCode\Grpc\Message\GrpcRequest;
use KaririCode\Grpc\Message\GrpcResponse;
use KaririCode\Grpc\Message\Metadata;
use KaririCode\Http\Security\PeerVerifyMode;
use KaririCode\Http\Security\TlsConfiguration;
use PHPUnit\Framework\TestCase;

final class MutualTlsInterceptorTest extends TestCase
{
    // ── No Certificate Metadata ──────────────────────────────────────────

    public function testNoCertWithRequireModeReturnsUnauthenticated(): void
    {
        $config = TlsConfiguration::mutual('/c.pem', '/k.pem', '/ca.pem');
        $interceptor = new MutualTlsInterceptor($config);

        $request = new GrpcRequest('test.Service', 'Method');

        $response = $interceptor->intercept($request, $this->neverCalledHandler());

        self::assertSame(StatusCode::Unauthenticated, $response->status->code);
        self::assertStringContainsString('required', $response->status->message);
    }

    public function testNoCertWithOptionalModeProceeds(): void
    {
        $config = TlsConfiguration::optionalClient('/c.pem', '/k.pem', '/ca.pem');
        $interceptor = new MutualTlsInterceptor($config);

        $request = new GrpcRequest('test.Service', 'Method');

        $response = $interceptor->intercept($request, $this->okHandler());

        self::assertTrue($response->isOk());
    }

    public function testNoCertWithNoneModeProceeds(): void
    {
        $config = new TlsConfiguration();
        $interceptor = new MutualTlsInterceptor($config);

        $request = new GrpcRequest('test.Service', 'Method');

        $response = $interceptor->intercept($request, $this->okHandler());

        self::assertTrue($response->isOk());
    }

    // ── Individual Metadata Headers ──────────────────────────────────────

    public function testValidCertDnProceeds(): void
    {
        $config = TlsConfiguration::mutual('/c.pem', '/k.pem', '/ca.pem');
        $interceptor = new MutualTlsInterceptor($config);

        $metadata = new Metadata([
            'x-client-cert-dn' => 'CN=service-a,O=Corp',
            'x-client-cert-hash' => 'aabbccdd',
        ]);
        $request = new GrpcRequest('test.Service', 'Method', metadata: $metadata);

        $response = $interceptor->intercept($request, $this->okHandler());

        self::assertTrue($response->isOk());
    }

    public function testValidCertDnInjectsMetadata(): void
    {
        $config = TlsConfiguration::mutual('/c.pem', '/k.pem', '/ca.pem');
        $interceptor = new MutualTlsInterceptor($config);

        $metadata = new Metadata(['x-client-cert-dn' => 'CN=service-a,O=Corp']);
        $request = new GrpcRequest('test.Service', 'Method', metadata: $metadata);

        $capturedDn = null;
        $handler = function (GrpcRequest $req) use (&$capturedDn): GrpcResponse {
            $capturedDn = $req->metadata->get(MutualTlsInterceptor::METADATA_KEY);
            return GrpcResponse::ok();
        };

        $interceptor->intercept($request, $handler);

        self::assertSame('CN=service-a,O=Corp', $capturedDn);
    }

    // ── DN Restriction ───────────────────────────────────────────────────

    public function testAllowedDnProceeds(): void
    {
        $config = TlsConfiguration::mutual('/c.pem', '/k.pem', '/ca.pem')
            ->withAllowedDns(['CN=api-gateway,O=Corp']);
        $interceptor = new MutualTlsInterceptor($config);

        $metadata = new Metadata(['x-client-cert-dn' => 'CN=api-gateway,O=Corp']);
        $request = new GrpcRequest('test.Service', 'Method', metadata: $metadata);

        $response = $interceptor->intercept($request, $this->okHandler());

        self::assertTrue($response->isOk());
    }

    public function testDisallowedDnReturnsUnauthenticated(): void
    {
        $config = TlsConfiguration::mutual('/c.pem', '/k.pem', '/ca.pem')
            ->withAllowedDns(['CN=api-gateway,O=Corp']);
        $interceptor = new MutualTlsInterceptor($config);

        $metadata = new Metadata(['x-client-cert-dn' => 'CN=evil,O=Hacker']);
        $request = new GrpcRequest('test.Service', 'Method', metadata: $metadata);

        $response = $interceptor->intercept($request, $this->neverCalledHandler());

        self::assertSame(StatusCode::Unauthenticated, $response->status->code);
        self::assertStringContainsString('DN not authorized', $response->status->message);
    }

    // ── Fingerprint Pinning ──────────────────────────────────────────────

    public function testTrustedFingerprintProceeds(): void
    {
        $config = TlsConfiguration::mutual('/c.pem', '/k.pem', '/ca.pem')
            ->withTrustedFingerprints(['aabbccdd']);
        $interceptor = new MutualTlsInterceptor($config);

        $metadata = new Metadata([
            'x-client-cert-dn' => 'CN=svc',
            'x-client-cert-hash' => 'aabbccdd',
        ]);
        $request = new GrpcRequest('test.Service', 'Method', metadata: $metadata);

        $response = $interceptor->intercept($request, $this->okHandler());

        self::assertTrue($response->isOk());
    }

    public function testUntrustedFingerprintReturnsUnauthenticated(): void
    {
        $config = TlsConfiguration::mutual('/c.pem', '/k.pem', '/ca.pem')
            ->withTrustedFingerprints(['aabbccdd']);
        $interceptor = new MutualTlsInterceptor($config);

        $metadata = new Metadata([
            'x-client-cert-dn' => 'CN=svc',
            'x-client-cert-hash' => '11223344',
        ]);
        $request = new GrpcRequest('test.Service', 'Method', metadata: $metadata);

        $response = $interceptor->intercept($request, $this->neverCalledHandler());

        self::assertSame(StatusCode::Unauthenticated, $response->status->code);
        self::assertStringContainsString('fingerprint not trusted', $response->status->message);
    }

    // ── XFCC Header (Envoy/Istio) ────────────────────────────────────────

    public function testXfccHeaderExtractsSubjectAndHash(): void
    {
        $config = TlsConfiguration::mutual('/c.pem', '/k.pem', '/ca.pem');
        $interceptor = new MutualTlsInterceptor($config);

        $xfcc = 'By=spiffe://cluster/ns/default;Hash=aabbccdd;Subject="CN=envoy-client,O=Mesh"';
        $metadata = new Metadata(['x-forwarded-client-cert' => $xfcc]);
        $request = new GrpcRequest('test.Service', 'Method', metadata: $metadata);

        $capturedDn = null;
        $handler = function (GrpcRequest $req) use (&$capturedDn): GrpcResponse {
            $capturedDn = $req->metadata->get(MutualTlsInterceptor::METADATA_KEY);
            return GrpcResponse::ok();
        };

        $interceptor->intercept($request, $handler);

        self::assertSame('CN=envoy-client,O=Mesh', $capturedDn);
    }

    public function testXfccWithDnRestrictionValidates(): void
    {
        $config = TlsConfiguration::mutual('/c.pem', '/k.pem', '/ca.pem')
            ->withAllowedDns(['CN=envoy-client,O=Mesh']);
        $interceptor = new MutualTlsInterceptor($config);

        $xfcc = 'Hash=aa;Subject="CN=envoy-client,O=Mesh"';
        $metadata = new Metadata(['x-forwarded-client-cert' => $xfcc]);
        $request = new GrpcRequest('test.Service', 'Method', metadata: $metadata);

        $response = $interceptor->intercept($request, $this->okHandler());

        self::assertTrue($response->isOk());
    }

    public function testXfccWithDisallowedDnRejects(): void
    {
        $config = TlsConfiguration::mutual('/c.pem', '/k.pem', '/ca.pem')
            ->withAllowedDns(['CN=allowed']);
        $interceptor = new MutualTlsInterceptor($config);

        $xfcc = 'Hash=aa;Subject="CN=evil-svc"';
        $metadata = new Metadata(['x-forwarded-client-cert' => $xfcc]);
        $request = new GrpcRequest('test.Service', 'Method', metadata: $metadata);

        $response = $interceptor->intercept($request, $this->neverCalledHandler());

        self::assertSame(StatusCode::Unauthenticated, $response->status->code);
    }

    public function testXfccTakesPriorityOverIndividualHeaders(): void
    {
        $config = TlsConfiguration::mutual('/c.pem', '/k.pem', '/ca.pem');
        $interceptor = new MutualTlsInterceptor($config);

        // Both XFCC and individual headers present — XFCC wins
        $metadata = new Metadata([
            'x-forwarded-client-cert' => 'Hash=ff;Subject="CN=from-xfcc"',
            'x-client-cert-dn' => 'CN=from-individual',
        ]);
        $request = new GrpcRequest('test.Service', 'Method', metadata: $metadata);

        $capturedDn = null;
        $handler = function (GrpcRequest $req) use (&$capturedDn): GrpcResponse {
            $capturedDn = $req->metadata->get(MutualTlsInterceptor::METADATA_KEY);
            return GrpcResponse::ok();
        };

        $interceptor->intercept($request, $handler);

        self::assertSame('CN=from-xfcc', $capturedDn);
    }

    // ── Metadata Key Constant ────────────────────────────────────────────

    public function testMetadataKeyConstant(): void
    {
        self::assertSame('x-validated-client-cert', MutualTlsInterceptor::METADATA_KEY);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /** @return callable(GrpcRequest): GrpcResponse */
    private function okHandler(): callable
    {
        return static fn (GrpcRequest $req): GrpcResponse => GrpcResponse::ok();
    }

    /** @return callable(GrpcRequest): GrpcResponse */
    private function neverCalledHandler(): callable
    {
        return static function (GrpcRequest $req): GrpcResponse {
            throw new \RuntimeException('Handler should not be called');
        };
    }
}
