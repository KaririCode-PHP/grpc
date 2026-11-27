<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Tests\Unit\Integration;

use KaririCode\Grpc\Integration\ForwardedClientCertificate;
use KaririCode\Grpc\Integration\Silently;
use KaririCode\Grpc\Tests\Support\TestCertificate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ForwardedClientCertificate::class)]
#[CoversClass(Silently::class)]
final class ForwardedClientCertificateTest extends TestCase
{
    #[Test]
    public function anElementIsReadWithQuotedValues(): void
    {
        $elements = ForwardedClientCertificate::elements('By=spiffe://cluster.local/ns/shop/sa/api;Hash=1f2e3d;Subject="CN=client-7,O=Acme\, Inc.,OU=\"ops\"";URI=;DNS=client.example');

        self::assertSame([[
            'by' => 'spiffe://cluster.local/ns/shop/sa/api',
            'hash' => '1f2e3d',
            'subject' => 'CN=client-7,O=Acme\\, Inc.,OU="ops"',
            'uri' => '',
            'dns' => 'client.example',
        ]], $elements);
    }

    #[Test]
    public function onlyQuotesAreUnescapedSoDnEscapesSurvive(): void
    {
        $elements = ForwardedClientCertificate::elements('Subject="O=a\\,CN=admin"');

        self::assertSame('O=a\\,CN=admin', $elements[0]['subject'] ?? null);
        self::assertSame('', ForwardedClientCertificate::certificate($elements[0] ?? [])->commonName(), 'the escaped comma keeps CN=admin inside O');
    }

    #[Test]
    public function elementsAreSeparatedByCommasOutsideQuotes(): void
    {
        $elements = ForwardedClientCertificate::elements('Hash=aa;Subject="CN=a,O=x",HASH=bb;SUBJECT="CN=b"');

        self::assertSame([['hash' => 'aa', 'subject' => 'CN=a,O=x'], ['hash' => 'bb', 'subject' => 'CN=b']], $elements);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformed(): iterable
    {
        yield 'empty' => [''];
        yield 'no equals sign' => ['Hash'];
        yield 'unterminated quote' => ['Subject="CN=a'];
        yield 'text after a quoted value' => ['Subject="CN=a"x;Hash=1'];
        yield 'trailing semicolon' => ['Hash=1;'];
        yield 'trailing comma' => ['Hash=1,'];
        yield 'empty element' => ['Hash=1,,Hash=2'];
        yield 'bare quote in a value' => ['Hash=a"b'];
        yield 'key with digits' => ['Hash2=1'];
        yield 'space before a key' => ['Hash=1; Subject=x'];
    }

    #[Test]
    #[DataProvider('malformed')]
    public function aMalformedHeaderHasNoElements(string $header): void
    {
        self::assertNull(ForwardedClientCertificate::elements($header));
    }

    #[Test]
    public function withoutAPemTheCertificateIsSubjectAndHash(): void
    {
        $certificate = ForwardedClientCertificate::certificate(['subject' => 'CN=client-7,O=Acme', 'hash' => 'AB:CD']);

        self::assertSame('CN=client-7,O=Acme', $certificate->subjectDn);
        self::assertSame('client-7', $certificate->commonName());
        self::assertSame('AB:CD', $certificate->fingerprint);
        self::assertNull($certificate->validTo);
        self::assertTrue(ForwardedClientCertificate::certificate([])->isEmpty());
    }

    #[Test]
    #[RequiresPhpExtension('openssl')]
    public function aForwardedPemGivesTheWholeCertificate(): void
    {
        $test = TestCertificate::create(['commonName' => 'pem-client']);

        $certificate = ForwardedClientCertificate::certificate(['cert' => rawurlencode($test->pem), 'subject' => 'CN=ignored', 'hash' => 'ignored']);

        self::assertSame('pem-client', $certificate->commonName());
        self::assertSame($test->fingerprint, $certificate->fingerprint);
        self::assertNotNull($certificate->validTo);
    }

    #[Test]
    public function anUnreadablePemFallsBackToSubjectAndHash(): void
    {
        error_clear_last();

        $certificate = ForwardedClientCertificate::certificate(['cert' => rawurlencode("-----BEGIN CERTIFICATE-----\nnot base64\n-----END CERTIFICATE-----"), 'subject' => 'CN=fallback', 'hash' => 'ff']);

        self::assertSame('CN=fallback', $certificate->subjectDn);
        self::assertSame('ff', $certificate->fingerprint);
        self::assertNull(error_get_last(), 'OpenSSL warnings never reach PHP');

        while (function_exists('openssl_error_string') && openssl_error_string() !== false) {
            // OpenSSL keeps its own queue of the parse error.
        }
    }
}
