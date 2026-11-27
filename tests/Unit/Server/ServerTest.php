<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Tests\Unit\Server;

use KaririCode\Grpc\Enum\CallType;
use KaririCode\Grpc\Exception\GrpcCode;
use KaririCode\Grpc\Exception\InvalidConfigurationException;
use KaririCode\Grpc\Server\MethodDescriptor;
use KaririCode\Grpc\Server\ServiceRegistry;
use KaririCode\Grpc\Server\SystemClock;
use KaririCode\Grpc\Tests\Support\CallbackService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MethodDescriptor::class)]
#[CoversClass(ServiceRegistry::class)]
#[CoversClass(SystemClock::class)]
final class ServerTest extends TestCase
{
    #[Test]
    public function aDescriptorNamesItsPathAndCallType(): void
    {
        $unary = new MethodDescriptor('shop.Cart', 'AddItem');
        $stream = new MethodDescriptor('shop.Cart', 'Watch', CallType::ServerStreaming);

        self::assertSame('/shop.Cart/AddItem', $unary->fullPath);
        self::assertSame(CallType::Unary, $unary->callType);
        self::assertSame('UNARY', $unary->routerMethod());
        self::assertSame('SERVER_STREAM', $stream->routerMethod());
    }

    #[Test]
    public function aDescriptorWithAMalformedNameIsRefused(): void
    {
        self::assertRefused(GrpcCode::InvalidDescriptorService, static fn() => new MethodDescriptor('shop/Cart', 'AddItem'));
        self::assertRefused(GrpcCode::InvalidDescriptorMethod, static fn() => new MethodDescriptor('shop.Cart', 'Add.Item'));
    }

    #[Test]
    public function servicesAndMethodsAreFoundByNameAndPath(): void
    {
        $cart = new CallbackService('shop.Cart');
        $orders = new CallbackService('shop.Orders');
        $add = new MethodDescriptor('shop.Cart', 'AddItem');
        $remove = new MethodDescriptor('shop.Cart', 'RemoveItem');
        $registry = new ServiceRegistry();

        $registry->register($cart, $add, $remove);
        $registry->register($orders);

        self::assertSame($cart, $registry->resolve('shop.Cart'));
        self::assertNull($registry->resolve('shop.Missing'));
        self::assertSame($add, $registry->resolveMethod('/shop.Cart/AddItem'));
        self::assertNull($registry->resolveMethod('/shop.Cart/Missing'));
        self::assertTrue($registry->describes('shop.Cart'));
        self::assertFalse($registry->describes('shop.Orders'), 'registered without descriptors');
        self::assertFalse($registry->describes('shop.Missing'), 'not registered at all');
        self::assertTrue($registry->hasService('shop.Orders'));
        self::assertFalse($registry->hasService('shop.Missing'));
        self::assertSame([$add, $remove], $registry->allMethods());
        self::assertSame(['shop.Cart', 'shop.Orders'], $registry->serviceNames());
    }

    #[Test]
    public function wiringMistakesAreRefusedAtRegistration(): void
    {
        $registry = new ServiceRegistry();
        $registry->register(new CallbackService('shop.Cart'), new MethodDescriptor('shop.Cart', 'AddItem'));

        self::assertRefused(GrpcCode::DuplicateService, static fn() => $registry->register(new CallbackService('shop.Cart')));
        self::assertRefused(GrpcCode::InvalidDescriptorService, static fn() => $registry->register(new CallbackService('shop Orders')));
        self::assertRefused(GrpcCode::ForeignMethod, static fn() => $registry->register(new CallbackService('shop.Orders'), new MethodDescriptor('shop.Cart', 'List')));
        self::assertRefused(GrpcCode::DuplicateMethod, static fn() => $registry->register(new CallbackService('shop.Orders'), new MethodDescriptor('shop.Orders', 'List'), new MethodDescriptor('shop.Orders', 'List', CallType::ServerStreaming)));
        self::assertFalse($registry->hasService('shop.Orders'), 'a refused registration leaves nothing behind');
        self::assertCount(1, $registry->allMethods());
    }

    #[Test]
    public function theSystemClockReadsUtc(): void
    {
        $before = new \DateTimeImmutable();
        $now = (new SystemClock())->now();

        self::assertSame('UTC', $now->getTimezone()->getName());
        self::assertGreaterThanOrEqual($before->getTimestamp(), $now->getTimestamp());
    }

    private static function assertRefused(GrpcCode $code, \Closure $operation): void
    {
        try {
            $operation();
            self::fail('the configuration was accepted');
        } catch (InvalidConfigurationException $e) {
            self::assertSame($code, $e->errorCode);
        }
    }
}
