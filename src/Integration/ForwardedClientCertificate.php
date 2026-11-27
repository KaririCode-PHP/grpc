<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Integration;

use KaririCode\Http\Security\ClientCertificate;

/**
 * Reads Envoy's x-forwarded-client-cert (XFCC) header: elements separated
 * by commas, one per proxy, each a list of Key=Value pairs separated by
 * semicolons; a value with a comma, semicolon or equals sign is quoted,
 * with \" for a quote. certificate() builds the client certificate of one
 * element from its URL-encoded PEM (Cert) when OpenSSL reads it, which
 * also gives the validity dates, or else from Subject and Hash.
 *
 * ARFA 1.3: P4 (a proxy format as a domain value). Spec V4.3.3: final,
 * stateless functions.
 *
 * Preconditions: the header comes from a trusted proxy; MutualTlsInterceptor
 * removes it from everyone else. elements() returns null for a header that
 * does not follow the grammar.
 *
 * Thread-safety: stateless; safe to share.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     $elements = ForwardedClientCertificate::elements('Hash=1f2e…;Subject="CN=client-7,O=Acme"');
 *     $certificate = ForwardedClientCertificate::certificate($elements[0]);   // CN client-7
 *
 * References: Envoy, HTTP header manipulation (x-forwarded-client-cert);
 * RFC 4514 (DN strings); ADR-005.
 *
 * @see https://www.envoyproxy.io/docs/envoy/latest/configuration/http/http_conn_man/headers#x-forwarded-client-cert
 */
final class ForwardedClientCertificate
{
    /** One Key=Value pair and the separator after it, from the current offset. */
    private const string PAIR = '/\G([A-Za-z]+)=("(?:[^"\\\\]|\\\\.)*"|[^,;"]*)([;,]|\z)/';

    /**
     * The elements of an XFCC header, each a map of lower-case keys to
     * values; null when the header is empty or malformed.
     *
     * @return non-empty-list<array<string, string>>|null
     */
    public static function elements(string $header): ?array
    {
        $elements = [];
        $current = [];
        $offset = 0;

        while ($offset < strlen($header)) {
            if (preg_match(self::PAIR, $header, $match, 0, $offset) !== 1) {
                return null;
            }

            $current[strtolower($match[1])] = self::unquote($match[2]);
            $offset += strlen($match[0]);

            if ($match[3] !== ';') {
                $elements[] = $current;
                $current = [];
            }
        }

        return $elements === [] || $current !== [] || str_ends_with($header, ',') ? null : $elements;
    }

    /**
     * @param array<string, string> $element
     */
    public static function certificate(array $element): ClientCertificate
    {
        $pem = rawurldecode($element['cert'] ?? '');
        $parsed = function_exists('openssl_x509_read') ? Silently::run(static fn(): \OpenSSLCertificate|false => openssl_x509_read($pem)) : false;

        return $parsed instanceof \OpenSSLCertificate
            ? ClientCertificate::fromOpenSslCertificate($parsed)
            : new ClientCertificate(subjectDn: $element['subject'] ?? '', fingerprint: $element['hash'] ?? '');
    }

    private static function unquote(string $value): string
    {
        return str_starts_with($value, '"') ? str_replace('\\"', '"', substr($value, 1, -1)) : $value;
    }
}
