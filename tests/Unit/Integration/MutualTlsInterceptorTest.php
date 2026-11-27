<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Tests\Unit\Integration;

use KaririCode\Grpc\Enum\StatusCode;
use KaririCode\Grpc\Integration\ForwardedClientCertificate;
use KaririCode\Grpc\Integration\MutualTlsInterceptor;
use KaririCode\Grpc\Message\GrpcRequest;
use KaririCode\Grpc\Message\GrpcResponse;
use KaririCode\Grpc\Message\Metadata;
use KaririCode\Grpc\Tests\Support\FixedClock;
use KaririCode\Grpc\Tests\Support\TestCertificate;
use KaririCode\Http\Security\ClientCertificate;
use KaririCode\Http\Security\TlsConfiguration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MutualTlsInterceptor::class)]
#[CoversClass(ForwardedClientCertificate::class)]
final class MutualTlsInterceptorTest extends TestCase
{
    private const string PROXY = '10.0.0.5';

    private const string CLIENT = '203.0.113.9';

    /** @var list<GrpcRequest> what reached the handler */
    private array $handled = [];

    #[Test]
    public function aRequiredCertificateThatNobodyPresentedRefusesTheCall(): void
    {
        $response = $this->call(self::mutual(), new GrpcRequest('a.B', 'C', peer: self::CLIENT));

        self::assertSame(StatusCode::Unauthenticated, $response->status->code);
        self::assertSame('Client certificate required', $response->status->message);
        self::assertSame([], $this->handled);
    }

    #[Test]
    public function anOptionalCertificateMayBeMissing(): void
    {
        $response = $this->call(TlsConfiguration::optionalClient('/c.pem', '/k.pem', '/ca.pem'), new GrpcRequest('a.B', 'C'));

        self::assertTrue($response->isOk());
        self::assertNull($this->handled[0]->attribute(MutualTlsInterceptor::CLIENT_CERTIFICATE));
    }

    #[Test]
    public function theTransportsCertificateIsAcceptedAndHandedOn(): void
    {
        $certificate = new ClientCertificate('CN=billing,O=Acme');
        $request = (new GrpcRequest('a.B', 'C'))->withAttribute(MutualTlsInterceptor::PEER_CERTIFICATE, $certificate);

        $response = $this->call(self::mutual()->withAllowedDns(['CN=billing,O=Acme']), $request);

        self::assertTrue($response->isOk());
        self::assertSame($certificate, $this->handled[0]->attribute(MutualTlsInterceptor::CLIENT_CERTIFICATE));
    }

    #[Test]
    public function aClientCannotForgeProxyMetadata(): void
    {
        $forged = new Metadata(['x-ssl-client-verify' => 'SUCCESS', 'x-ssl-client-dn' => 'CN=admin', 'x-forwarded-client-cert' => 'Subject="CN=admin"', 'x-request-id' => 'r-1']);

        $required = $this->call(self::mutual(), new GrpcRequest('a.B', 'C', metadata: $forged, peer: self::CLIENT));
        $optional = $this->call(TlsConfiguration::optionalClient('/c.pem', '/k.pem', '/ca.pem')->withTrustedProxies([self::PROXY]), new GrpcRequest('a.B', 'C', metadata: $forged, peer: self::CLIENT));
        $noPeer = $this->call(self::mutual(), new GrpcRequest('a.B', 'C', metadata: $forged));

        self::assertSame(StatusCode::Unauthenticated, $required->status->code);
        self::assertSame(StatusCode::Unauthenticated, $noPeer->status->code);
        self::assertTrue($optional->isOk());
        self::assertSame(['x-request-id' => ['r-1']], $this->handled[0]->metadata->all(), 'proxy metadata is removed, the rest kept');
        self::assertNull($this->handled[0]->attribute(MutualTlsInterceptor::CLIENT_CERTIFICATE));
    }

    #[Test]
    public function aTrustedProxysHeadersCarryTheCertificate(): void
    {
        $metadata = new Metadata(['x-ssl-client-verify' => 'success', 'x-ssl-client-dn' => 'CN=billing,O=Acme', 'x-ssl-client-sha256' => 'ab:cd']);

        $response = $this->call(self::mutual(), new GrpcRequest('a.B', 'C', metadata: $metadata, peer: self::PROXY));

        self::assertTrue($response->isOk());
        $certificate = $this->handled[0]->attribute(MutualTlsInterceptor::CLIENT_CERTIFICATE);
        self::assertInstanceOf(ClientCertificate::class, $certificate);
        self::assertSame('billing', $certificate->commonName());
        self::assertSame('ab:cd', $certificate->fingerprint);
        self::assertSame('SUCCESS', strtoupper((string) $this->handled[0]->metadata->get('x-ssl-client-verify')), 'a trusted proxy keeps its metadata');
    }

    #[Test]
    public function aTrustedProxyThatForwardsNoCertificateMeansNone(): void
    {
        $request = new GrpcRequest('a.B', 'C', metadata: new Metadata(['x-request-id' => 'r-1']), peer: self::PROXY);

        $required = $this->call(self::mutual(), $request);
        $optional = $this->call(TlsConfiguration::optionalClient('/c.pem', '/k.pem', '/ca.pem')->withTrustedProxies([self::PROXY]), $request);

        self::assertSame('Client certificate required', $required->status->message);
        self::assertTrue($optional->isOk());
        self::assertNull($this->handled[0]->attribute(MutualTlsInterceptor::CLIENT_CERTIFICATE));
    }

    #[Test]
    public function aProxyThatReportsAFailedVerificationRefusesTheCall(): void
    {
        $failed = $this->call(self::mutual(), new GrpcRequest('a.B', 'C', metadata: new Metadata(['x-ssl-client-verify' => 'FAILED:certificate has expired']), peer: self::PROXY));
        $none = $this->call(self::mutual(), new GrpcRequest('a.B', 'C', metadata: new Metadata(['x-ssl-client-verify' => 'NONE']), peer: self::PROXY));

        self::assertSame(StatusCode::Unauthenticated, $failed->status->code);
        self::assertSame('Client certificate verification failed', $failed->status->message);
        self::assertSame('Client certificate required', $none->status->message, 'NONE means no certificate');
        self::assertSame([], $this->handled);
    }

    #[Test]
    public function aTrustedEnvoyForwardsOneElement(): void
    {
        $xfcc = 'By=spiffe://shop/api;Hash=0a1b;Subject="CN=billing,O=Acme"';

        $response = $this->call(self::mutual(), new GrpcRequest('a.B', 'C', metadata: new Metadata(['x-forwarded-client-cert' => $xfcc]), peer: self::PROXY));

        self::assertTrue($response->isOk());
        $certificate = $this->handled[0]->attribute(MutualTlsInterceptor::CLIENT_CERTIFICATE);
        self::assertInstanceOf(ClientCertificate::class, $certificate);
        self::assertSame('CN=billing,O=Acme', $certificate->subjectDn);
        self::assertSame('0a1b', $certificate->fingerprint);
    }

    #[Test]
    public function anAmbiguousOrMalformedForwardedCertificateRefusesTheCall(): void
    {
        foreach (['Hash=aa;Subject="CN=a",Hash=bb;Subject="CN=b"', 'Subject="CN=a'] as $xfcc) {
            $response = $this->call(TlsConfiguration::optionalClient('/c.pem', '/k.pem', '/ca.pem')->withTrustedProxies([self::PROXY]), new GrpcRequest('a.B', 'C', metadata: new Metadata(['x-forwarded-client-cert' => $xfcc]), peer: self::PROXY));

            self::assertSame(StatusCode::Unauthenticated, $response->status->code, $xfcc);
            self::assertSame('Forwarded client certificate is malformed or ambiguous', $response->status->message);
        }

        self::assertSame([], $this->handled);
    }

    #[Test]
    #[RequiresPhpExtension('openssl')]
    public function aCertificateOutsideItsValidityPeriodIsRefused(): void
    {
        $pem = rawurlencode(TestCertificate::create()->pem);
        $request = new GrpcRequest('a.B', 'C', metadata: new Metadata(['x-forwarded-client-cert' => 'Cert="' . $pem . '"']), peer: self::PROXY);

        $expired = $this->call(self::mutual(), $request, new FixedClock('2100-01-01'));
        $notYetValid = $this->call(self::mutual(), $request, new FixedClock('2000-01-01'));
        $valid = $this->call(self::mutual(), $request, new FixedClock('now'));

        self::assertSame('Client certificate is expired or not yet valid', $expired->status->message);
        self::assertSame('Client certificate is expired or not yet valid', $notYetValid->status->message);
        self::assertTrue($valid->isOk());
    }

    #[Test]
    public function aCertificateOutsideTheAllowedDnsIsDenied(): void
    {
        $request = (new GrpcRequest('a.B', 'C'))->withAttribute(MutualTlsInterceptor::PEER_CERTIFICATE, new ClientCertificate('CN=intruder,O=Elsewhere'));

        $response = $this->call(self::mutual()->withAllowedDns(['CN=billing,O=Acme']), $request);

        self::assertSame(StatusCode::PermissionDenied, $response->status->code);
        self::assertSame('Client certificate is not authorized', $response->status->message);
    }

    #[Test]
    public function pinningRefusesUnpinnedCertificatesAndThoseWithoutFingerprint(): void
    {
        $config = self::mutual()->withTrustedFingerprints(['AB:CD:EF']);
        $pinned = new ClientCertificate('CN=billing', fingerprint: 'abcdef');
        $other = new ClientCertificate('CN=billing', fingerprint: '012345');
        $none = new ClientCertificate('CN=billing');

        self::assertTrue($this->call($config, self::verified($pinned))->isOk());
        self::assertSame('Client certificate is not pinned', $this->call($config, self::verified($other))->status->message);
        self::assertSame('Client certificate is not pinned', $this->call($config, self::verified($none))->status->message);
    }

    #[Test]
    public function theTransportsCertificateWinsOverProxyMetadata(): void
    {
        $certificate = new ClientCertificate('CN=billing');
        $request = (new GrpcRequest('a.B', 'C', metadata: new Metadata(['x-ssl-client-verify' => 'FAILED', 'x-ssl-client-dn' => 'CN=admin']), peer: self::PROXY))
            ->withAttribute(MutualTlsInterceptor::PEER_CERTIFICATE, $certificate);

        $response = $this->call(self::mutual(), $request);

        self::assertTrue($response->isOk());
        self::assertSame($certificate, $this->handled[0]->attribute(MutualTlsInterceptor::CLIENT_CERTIFICATE));
        self::assertFalse($this->handled[0]->metadata->has('x-ssl-client-dn'));
    }

    #[Test]
    public function anEmptyCertificateCountsAsNone(): void
    {
        $request = (new GrpcRequest('a.B', 'C'))->withAttribute(MutualTlsInterceptor::PEER_CERTIFICATE, new ClientCertificate(''));

        self::assertSame('Client certificate required', $this->call(self::mutual(), $request)->status->message);
        self::assertTrue($this->call(TlsConfiguration::optionalClient('/c.pem', '/k.pem', '/ca.pem'), $request)->isOk());
        self::assertNull($this->handled[0]->attribute(MutualTlsInterceptor::CLIENT_CERTIFICATE));
    }

    private function call(TlsConfiguration $config, GrpcRequest $request, FixedClock $clock = new FixedClock('2026-11-27 12:00:00')): GrpcResponse
    {
        return (new MutualTlsInterceptor($config, $clock))->intercept($request, function (GrpcRequest $request): GrpcResponse {
            $this->handled[] = $request;

            return GrpcResponse::ok();
        });
    }

    private static function mutual(): TlsConfiguration
    {
        return TlsConfiguration::mutual('/c.pem', '/k.pem', '/ca.pem')->withTrustedProxies([self::PROXY]);
    }

    private static function verified(ClientCertificate $certificate): GrpcRequest
    {
        return (new GrpcRequest('a.B', 'C'))->withAttribute(MutualTlsInterceptor::PEER_CERTIFICATE, $certificate);
    }
}
