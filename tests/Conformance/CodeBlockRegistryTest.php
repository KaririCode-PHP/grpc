<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Tests\Conformance;

use KaririCode\Exception\ErrorCodeTable;
use KaririCode\Grpc\Exception\GrpcCode;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * GrpcCode against the ecosystem registry of record, CODE-BLOCKS.md of
 * kariricode/exception, read from the installed package (ADR-003).
 */
#[CoversNothing]
final class CodeBlockRegistryTest extends TestCase
{
    private const string REGISTRY = __DIR__ . '/../../vendor/kariricode/exception/CODE-BLOCKS.md';

    #[Test]
    public function wsCodeIsAValidCodeTable(): void
    {
        $table = new ErrorCodeTable(GrpcCode::class);

        self::assertSame('GRPC', $table->prefix);
        self::assertSame([1900, 1999], [$table->block->first, $table->block->last]);
    }

    #[Test]
    public function theRegistryGivesThisPackageItsBlockAndPrefix(): void
    {
        $rows = array_values(array_filter(
            file(self::REGISTRY, FILE_IGNORE_NEW_LINES) ?: [],
            static fn(string $line): bool => str_contains($line, '| kariricode/grpc |'),
        ));

        self::assertCount(1, $rows, 'one block for kariricode/grpc');
        self::assertMatchesRegularExpression('/\A\| 1900–1999 \| GRPC \| kariricode\/grpc \| (released|claimed) \|/u', $rows[0]);
    }

    #[Test]
    public function codesFollowTheDocumentedLayout(): void
    {
        $ranges = [
            'a call ended by the application' => [1900, 1909],
            'values from a client or the transport' => [1910, 1929],
            'length-prefixed messages' => [1930, 1939],
            'configuration' => [1940, 1949],
            'router normalizer' => [1950, 1959],
        ];

        foreach (GrpcCode::cases() as $case) {
            $inRange = array_filter($ranges, static fn(array $range): bool => $case->value >= $range[0] && $case->value <= $range[1]);
            self::assertCount(1, $inRange, $case->name . ' sits in a documented range');
        }
    }
}
