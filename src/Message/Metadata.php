<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Message;

/**
 * Immutable gRPC metadata (headers and trailers).
 *
 * ARFA 1.3 P1 (Immutable State Transformation): all with* methods
 * return a new instance.
 *
 * Spec V4.0: final readonly class.
 *
 * gRPC metadata is a set of key-value pairs where keys are
 * case-insensitive ASCII strings and values are either ASCII strings
 * or binary (keys ending in "-bin" carry base64-encoded binary).
 *
 * @see https://grpc.io/docs/what-is-grpc/core-concepts/#metadata
 */
final readonly class Metadata
{
    /** @var array<string, list<string>> Lowercase key → values */
    private array $pairs;

    /**
     * @param array<string, string|list<string>> $pairs Key-value pairs (values normalized to lists)
     */
    public function __construct(array $pairs = [])
    {
        $normalized = [];
        foreach ($pairs as $key => $value) {
            $key = strtolower(trim($key));
            $normalized[$key] = is_array($value) ? $value : [$value];
        }
        $this->pairs = $normalized;
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $values = $this->pairs[strtolower($key)] ?? null;
        return $values[0] ?? $default;
    }

    /**
     * @return list<string>
     */
    public function getAll(string $key): array
    {
        return $this->pairs[strtolower($key)] ?? [];
    }

    public function has(string $key): bool
    {
        return isset($this->pairs[strtolower($key)]);
    }

    public function withValue(string $key, string $value): self
    {
        $pairs = $this->pairs;
        $pairs[strtolower($key)] = [$value];
        return new self($pairs);
    }

    public function withAddedValue(string $key, string $value): self
    {
        $pairs = $this->pairs;
        $key = strtolower($key);
        $pairs[$key] = [...($pairs[$key] ?? []), $value];
        return new self($pairs);
    }

    public function withoutKey(string $key): self
    {
        $pairs = $this->pairs;
        unset($pairs[strtolower($key)]);
        return new self($pairs);
    }

    /**
     * @return array<string, list<string>>
     */
    public function all(): array
    {
        return $this->pairs;
    }

    public function isEmpty(): bool
    {
        return $this->pairs === [];
    }

    /**
     * Whether a key carries binary data (convention: "-bin" suffix).
     */
    public static function isBinaryKey(string $key): bool
    {
        return str_ends_with(strtolower($key), '-bin');
    }
}
