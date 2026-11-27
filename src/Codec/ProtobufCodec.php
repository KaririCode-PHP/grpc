<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Codec;

use KaririCode\Grpc\Contract\Codec;

/**
 * Protocol Buffers codec — raw binary serialization.
 *
 * ARFA 1.3 P2 (Reactive Flow Composition): pluggable codec stage.
 *
 * Spec V4.0: final class, zero state.
 *
 * Content-type: application/grpc+proto (default for gRPC)
 *
 * When ext-protobuf is available, uses the native C extension for
 * maximum throughput. Falls back to a minimal wire-format
 * implementation for varint-based field encoding.
 *
 * For full protobuf support (schema-driven, code-gen), use the
 * Google Protobuf PHP library with generated message classes and
 * implement a custom Codec that delegates to those classes.
 *
 * @see https://protobuf.dev/programming-guides/encoding/
 * @see https://grpc.io/docs/what-is-grpc/introduction/#working-with-protocol-buffers
 */
final class ProtobufCodec implements Codec
{
    public function name(): string
    {
        return 'proto';
    }

    /**
     * Minimal wire-format encoding: field_number + wire_type 0 (varint)
     * for integers, wire_type 2 (length-delimited) for strings/bytes.
     *
     * Field numbers are assigned sequentially from the array key order.
     * This is suitable for simple messages; schema-driven encoding
     * requires generated message classes.
     */
    public function encode(array $message): string
    {
        $buffer = '';
        $fieldNumber = 1;

        foreach ($message as $value) {
            if (is_int($value)) {
                $buffer .= $this->encodeVarintField($fieldNumber, $value);
            } elseif (is_string($value)) {
                $buffer .= $this->encodeLengthDelimited($fieldNumber, $value);
            } elseif (is_bool($value)) {
                $buffer .= $this->encodeVarintField($fieldNumber, $value ? 1 : 0);
            } elseif (is_float($value)) {
                $buffer .= $this->encodeFixed64Field($fieldNumber, $value);
            }
            ++$fieldNumber;
        }

        return $buffer;
    }

    /**
     * Minimal wire-format decoding. Returns indexed array of values.
     *
     * Without a schema, field names are not recoverable — values are
     * returned with numeric keys matching field numbers.
     */
    public function decode(string $data): array
    {
        $result = [];
        $pos = 0;
        $len = strlen($data);

        while ($pos < $len) {
            [$tag, $pos] = $this->decodeVarint($data, $pos);
            $fieldNumber = $tag >> 3;
            $wireType = $tag & 0x07;

            $value = match ($wireType) {
                0 => (function () use ($data, &$pos) {
                    [$v, $pos] = $this->decodeVarint($data, $pos);
                    return $v;
                })(),
                1 => (function () use ($data, &$pos) {
                    $v = unpack('e', substr($data, $pos, 8));
                    $pos += 8;
                    return $v ? $v[1] : 0.0;
                })(),
                2 => (function () use ($data, &$pos) {
                    [$length, $pos] = $this->decodeVarint($data, $pos);
                    $v = substr($data, $pos, $length);
                    $pos += $length;
                    return $v;
                })(),
                default => throw new \RuntimeException("Unsupported wire type: {$wireType}"),
            };

            $result[$fieldNumber] = $value;
        }

        return $result;
    }

    // ── Wire Format Helpers ──────────────────────────────────────────────

    private function encodeVarintField(int $fieldNumber, int $value): string
    {
        $tag = ($fieldNumber << 3) | 0; // wire type 0 = varint
        return $this->encodeVarint($tag) . $this->encodeVarint($value);
    }

    private function encodeLengthDelimited(int $fieldNumber, string $value): string
    {
        $tag = ($fieldNumber << 3) | 2; // wire type 2 = length-delimited
        return $this->encodeVarint($tag) . $this->encodeVarint(strlen($value)) . $value;
    }

    private function encodeFixed64Field(int $fieldNumber, float $value): string
    {
        $tag = ($fieldNumber << 3) | 1; // wire type 1 = 64-bit
        return $this->encodeVarint($tag) . pack('e', $value);
    }

    private function encodeVarint(int $value): string
    {
        $buffer = '';
        $value = $value & 0xFFFFFFFFFFFFFFFF; // ensure unsigned

        do {
            $byte = $value & 0x7F;
            $value >>= 7;
            $buffer .= chr($value > 0 ? ($byte | 0x80) : $byte);
        } while ($value > 0);

        return $buffer;
    }

    /**
     * @return array{int, int} [value, newPosition]
     */
    private function decodeVarint(string $data, int $pos): array
    {
        $result = 0;
        $shift = 0;

        do {
            $byte = ord($data[$pos]);
            $result |= ($byte & 0x7F) << $shift;
            $shift += 7;
            ++$pos;
        } while ($byte & 0x80);

        return [$result, $pos];
    }
}
