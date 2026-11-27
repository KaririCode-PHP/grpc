<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Message;

use KaririCode\Grpc\Exception\InvalidMessageException;

/**
 * The custom metadata of a call (request headers, response headers or
 * trailers): lower-case keys, each with one or more values. Keys follow
 * gRPC over HTTP/2: 0-9, a-z, "_", "-" and "."; keys that start with
 * "grpc-" belong to the protocol and are refused, so an application cannot
 * forge grpc-status or grpc-message. A key ending in "-bin" holds raw
 * bytes (base64 on the wire, which the transport handles); every other
 * value is space and printable ASCII, so no value can split a header.
 *
 * ARFA 1.3: P1 (immutable; with*() returns a new instance). Spec V4.3.3:
 * final readonly; get() and getAll() take the key they read.
 *
 * Preconditions: keys and values as above (InvalidMessageException 1910,
 * 1911, 1912). Keys are lower-cased first, as HTTP/2 requires.
 *
 * Thread-safety: immutable; safe to share.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     $metadata = new Metadata(['x-request-id' => 'r-42', 'trace-bin' => random_bytes(16)]);
 *     $metadata->get('X-Request-Id');               // "r-42"
 *     $metadata->withAddedValue('tag', 'b')->getAll('tag');
 *
 * References: gRPC over HTTP/2 (Custom-Metadata, Binary-Header); RFC 9113
 * §8.2.1 (field validity); ADR-006.
 *
 * @see https://github.com/grpc/grpc/blob/master/doc/PROTOCOL-HTTP2.md#requests
 */
final readonly class Metadata
{
    private const string KEY = '/\A[0-9a-z_.\-]+\z/';

    private const string ASCII_VALUE = '/\A[\x20-\x7E]*\z/';

    /** @var array<string, non-empty-list<string>> */
    private array $pairs;

    /**
     * @param array<array-key, string|list<string>> $pairs key => value, or key => values
     */
    public function __construct(array $pairs = [])
    {
        $normalized = [];

        foreach ($pairs as $key => $values) {
            $key = self::key((string) $key);

            foreach (is_array($values) ? $values : [$values] as $value) {
                $normalized[$key][] = self::value($key, $value);
            }
        }

        $this->pairs = $normalized;
    }

    /** The first value of $key, or $default. */
    public function get(string $key, ?string $default = null): ?string
    {
        return $this->pairs[strtolower($key)][0] ?? $default;
    }

    /**
     * @return list<string> every value of $key, in order
     */
    public function getAll(string $key): array
    {
        return $this->pairs[strtolower($key)] ?? [];
    }

    public function has(string $key): bool
    {
        return isset($this->pairs[strtolower($key)]);
    }

    /** $key with $value as its only value. */
    public function withValue(string $key, string $value): self
    {
        $pairs = $this->pairs;
        $pairs[strtolower($key)] = [$value];

        return new self($pairs);
    }

    /** $key with $value after its current values. */
    public function withAddedValue(string $key, string $value): self
    {
        $pairs = $this->pairs;
        $pairs[$key][] = $value;   // the constructor lower-cases and merges the key

        return new self($pairs);
    }

    public function withoutKey(string $key): self
    {
        $pairs = $this->pairs;
        unset($pairs[strtolower($key)]);

        return new self($pairs);
    }

    /**
     * @return array<string, non-empty-list<string>>
     */
    public function all(): array
    {
        return $this->pairs;
    }

    public function isEmpty(): bool
    {
        return $this->pairs === [];
    }

    public static function isBinaryKey(string $key): bool
    {
        return str_ends_with(strtolower($key), '-bin');
    }

    private static function key(string $key): string
    {
        $key = strtolower($key);

        if (preg_match(self::KEY, $key) !== 1) {
            throw InvalidMessageException::invalidMetadataKey();
        }

        return str_starts_with($key, 'grpc-') ? throw InvalidMessageException::reservedMetadataKey() : $key;
    }

    private static function value(string $key, string $value): string
    {
        return self::isBinaryKey($key) || preg_match(self::ASCII_VALUE, $value) === 1 ? $value : throw InvalidMessageException::invalidMetadataValue();
    }
}
