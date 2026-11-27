<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Wire;

use KaririCode\Grpc\Exception\InvalidMessageException;

/**
 * Reads the grpc-timeout header: one to eight digits and a unit (H hours,
 * M minutes, S seconds, m milliseconds, u microseconds, n nanoseconds).
 * deadline() turns it into the absolute deadline a GrpcRequest carries,
 * counted from the moment the transport received the call.
 *
 * ARFA 1.3: P2 (wire value → domain value). Spec V4.3.3: final, stateless
 * functions.
 *
 * Preconditions: the value has exactly that form (1917): no sign, no
 * space, no fraction.
 *
 * Thread-safety: stateless; safe to share.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     Timeout::seconds('250m');                           // 0.25
 *     $request = $request->withDeadline(Timeout::deadline('5S', $clock->now()));
 *
 * References: gRPC over HTTP/2 (Timeout, TimeoutValue, TimeoutUnit);
 * ADR-006.
 *
 * @see https://github.com/grpc/grpc/blob/master/doc/PROTOCOL-HTTP2.md#requests
 */
final class Timeout
{
    /** Microseconds per unit. */
    private const array UNITS = ['H' => 3_600_000_000, 'M' => 60_000_000, 'S' => 1_000_000, 'm' => 1000, 'u' => 1];

    public static function seconds(string $value): float
    {
        return self::microseconds($value) / 1_000_000;
    }

    public static function deadline(string $value, \DateTimeImmutable $now): \DateTimeImmutable
    {
        $microseconds = self::microseconds($value);

        return $now->modify(sprintf('+%d seconds +%d microseconds', intdiv($microseconds, 1_000_000), $microseconds % 1_000_000));
    }

    /** The timeout in whole microseconds. */
    private static function microseconds(string $value): int
    {
        if (preg_match('/\A[0-9]{1,8}[HMSmun]\z/', $value) !== 1) {
            throw InvalidMessageException::invalidTimeout();
        }

        return self::scaled((int) substr($value, 0, -1), substr($value, -1));
    }

    /** Nanoseconds round up, so a timeout never shortens to zero. */
    private static function scaled(int $amount, string $unit): int
    {
        return $unit === 'n' ? intdiv($amount + 999, 1000) : $amount * self::UNITS[$unit];
    }
}
