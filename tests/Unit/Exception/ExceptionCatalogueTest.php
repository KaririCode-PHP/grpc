<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Tests\Unit\Exception;

use KaririCode\Contract\Exception\Configuration;
use KaririCode\Contract\Exception\Protocol;
use KaririCode\Contract\Exception\Runtime;
use KaririCode\Contract\Exception\Validation;
use KaririCode\Exception\CodeBlock;
use KaririCode\Grpc\Enum\StatusCode;
use KaririCode\Grpc\Exception\FrameException;
use KaririCode\Grpc\Exception\GrpcCode;
use KaririCode\Grpc\Exception\InvalidConfigurationException;
use KaririCode\Grpc\Exception\InvalidMessageException;
use KaririCode\Grpc\Exception\StatusException;
use KaririCode\Grpc\Exception\UnsupportedRequestException;
use KaririCode\Grpc\Message\Status;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GrpcCode::class)]
#[CoversClass(StatusException::class)]
#[CoversClass(InvalidMessageException::class)]
#[CoversClass(FrameException::class)]
#[CoversClass(InvalidConfigurationException::class)]
#[CoversClass(UnsupportedRequestException::class)]
final class ExceptionCatalogueTest extends TestCase
{
    /**
     * @return iterable<string, array{\Closure(): \Throwable, GrpcCode, class-string, string}>
     */
    public static function factories(): iterable
    {
        yield 'call failed' => [static fn() => StatusException::failed(Status::notFound()), GrpcCode::CallFailed, Runtime::class, 'failed with status "NotFound"'];
        yield 'metadata key' => [static fn() => InvalidMessageException::invalidMetadataKey(), GrpcCode::InvalidMetadataKey, Validation::class, '0-9, a-z'];
        yield 'reserved key' => [static fn() => InvalidMessageException::reservedMetadataKey(), GrpcCode::ReservedMetadataKey, Validation::class, 'reserved for the protocol'];
        yield 'metadata value' => [static fn() => InvalidMessageException::invalidMetadataValue(), GrpcCode::InvalidMetadataValue, Validation::class, 'printable ASCII'];
        yield 'service name' => [static fn() => InvalidMessageException::invalidServiceName(), GrpcCode::InvalidServiceName, Validation::class, 'dot-separated identifiers'];
        yield 'method name' => [static fn() => InvalidMessageException::invalidMethodName(), GrpcCode::InvalidMethodName, Validation::class, 'one identifier'];
        yield 'path' => [static fn() => InvalidMessageException::invalidPath(), GrpcCode::InvalidPath, Validation::class, ':path'];
        yield 'peer' => [static fn() => InvalidMessageException::invalidPeer(), GrpcCode::InvalidPeer, Validation::class, 'without a port'];
        yield 'timeout' => [static fn() => InvalidMessageException::invalidTimeout(), GrpcCode::InvalidTimeout, Validation::class, 'one to eight digits'];
        yield 'malformed JSON' => [static fn() => InvalidMessageException::malformedJson(), GrpcCode::MalformedJson, Validation::class, 'not valid JSON'];
        yield 'JSON not an object' => [static fn() => InvalidMessageException::jsonNotAnObject(), GrpcCode::JsonNotAnObject, Validation::class, 'is an object'];
        yield 'unencodable JSON' => [static fn() => InvalidMessageException::unencodableJson(), GrpcCode::UnencodableJson, Validation::class, 'cannot be encoded'];
        yield 'message too large' => [static fn() => FrameException::messageTooLarge(), GrpcCode::MessageTooLarge, Protocol::class, 'receive limit'];
        yield 'compressed flag' => [static fn() => FrameException::invalidCompressedFlag(), GrpcCode::InvalidCompressedFlag, Protocol::class, '0 or 1'];
        yield 'limit' => [static fn() => InvalidConfigurationException::nonPositiveLimit('maxMessageSize'), GrpcCode::NonPositiveLimit, Configuration::class, '"maxMessageSize" must be at least 1'];
        yield 'descriptor service' => [static fn() => InvalidConfigurationException::invalidDescriptorService('shop/Cart'), GrpcCode::InvalidDescriptorService, Configuration::class, '"shop/Cart" is not'];
        yield 'descriptor method' => [static fn() => InvalidConfigurationException::invalidDescriptorMethod('Add Item'), GrpcCode::InvalidDescriptorMethod, Configuration::class, '"Add Item" is not'];
        yield 'duplicate service' => [static fn() => InvalidConfigurationException::duplicateService('shop.Cart'), GrpcCode::DuplicateService, Configuration::class, '"shop.Cart" is already registered'];
        yield 'duplicate method' => [static fn() => InvalidConfigurationException::duplicateMethod('/shop.Cart/Add'), GrpcCode::DuplicateMethod, Configuration::class, '"/shop.Cart/Add" is described twice'];
        yield 'foreign method' => [static fn() => InvalidConfigurationException::foreignMethod('/a.B/C', 'shop.Cart'), GrpcCode::ForeignMethod, Configuration::class, '"/a.B/C" does not belong to the service "shop.Cart"'];
        yield 'not a request' => [static fn() => UnsupportedRequestException::notAGrpcRequest(), GrpcCode::NotAGrpcRequest, Validation::class, 'GrpcRequest objects only'];
    }

    /**
     * @param \Closure(): \Throwable $factory
     * @param class-string           $family
     */
    #[Test]
    #[DataProvider('factories')]
    public function everyFactoryCarriesItsCodeFamilyAndPrefixedMessage(\Closure $factory, GrpcCode $code, string $family, string $message): void
    {
        $exception = $factory();

        self::assertInstanceOf($family, $exception);
        self::assertSame($code->value, $exception->getCode());
        self::assertTrue(property_exists($exception, 'errorCode'));
        self::assertSame($code, $exception->errorCode);
        self::assertStringStartsWith('[GRPC_' . $code->value . '] ', $exception->getMessage());
        self::assertStringContainsString($message, $exception->getMessage());
    }

    #[Test]
    public function everyCodeHasAFactoryAndSitsInTheGrpcBlock(): void
    {
        $used = array_map(static fn(array $row): GrpcCode => $row[1], iterator_to_array(self::factories()));

        self::assertEqualsCanonicalizing(GrpcCode::cases(), array_values($used), 'one factory per code, no orphan codes');
        self::assertSame('GRPC', GrpcCode::prefix());
        self::assertEquals(new CodeBlock(1900, 1999), GrpcCode::block());

        foreach (GrpcCode::cases() as $code) {
            self::assertTrue(GrpcCode::block()->contains($code->value), $code->name);
        }
    }

    #[Test]
    public function failuresCarryTheStatusTheClientReceives(): void
    {
        $status = Status::permissionDenied('Not your cart');

        self::assertSame($status, StatusException::failed($status)->status);
        self::assertSame(StatusCode::ResourceExhausted, FrameException::messageTooLarge()->status);
        self::assertSame(StatusCode::Internal, FrameException::invalidCompressedFlag()->status);
    }

    #[Test]
    public function quotedIdentifiersCannotBreakTheLogLine(): void
    {
        $message = InvalidConfigurationException::duplicateService("x\r\nInjected: 1")->getMessage();

        self::assertStringNotContainsString("\n", $message);
        self::assertStringContainsString('"x\r\nInjected: 1"', $message);
    }
}
