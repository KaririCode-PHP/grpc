<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Server;

use Psr\Clock\ClockInterface;

/**
 * The wall clock in UTC, as a PSR-20 clock: the default time source for
 * deadline checks. Tests pass a fixed clock instead.
 *
 * ARFA 1.3: P5 (time as an injected dependency). Spec V4.3.3: final
 * readonly, stateless.
 *
 * Preconditions: none.
 *
 * Thread-safety: stateless; safe to share.
 *
 * Side effects: reads the system clock.
 *
 * Example:
 *
 *     $deadline = Timeout::deadline('5S', (new SystemClock())->now());
 *
 * References: PSR-20 (Clock); ADR-001.
 *
 * @see https://www.php-fig.org/psr/psr-20/
 */
final readonly class SystemClock implements ClockInterface
{
    #[\Override]
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
