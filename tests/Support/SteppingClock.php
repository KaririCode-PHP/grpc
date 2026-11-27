<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Tests\Support;

use Psr\Clock\ClockInterface;

/**
 * A clock that reads the given instants one after the other and then stays
 * on the last, so a test can let time pass while a call runs.
 */
final class SteppingClock implements ClockInterface
{
    /** @var list<\DateTimeImmutable> */
    private array $instants;

    public function __construct(string $first, string ...$rest)
    {
        $this->instants = array_map(static fn(string $instant): \DateTimeImmutable => new \DateTimeImmutable($instant, new \DateTimeZone('UTC')), [$first, ...array_values($rest)]);
    }

    #[\Override]
    public function now(): \DateTimeImmutable
    {
        $now = $this->instants[0];

        if (count($this->instants) > 1) {
            array_shift($this->instants);
        }

        return $now;
    }
}
