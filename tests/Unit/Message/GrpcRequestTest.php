<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Tests\Unit\Message;

use KaririCode\Grpc\Enum\CallType;
use KaririCode\Grpc\Exception\GrpcCode;
use KaririCode\Grpc\Exception\InvalidMessageException;
use KaririCode\Grpc\Message\GrpcRequest;
use KaririCode\Grpc\Message\Metadata;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GrpcRequest::class)]
final class GrpcRequestTest extends TestCase
{
    #[Test]
    public function aRequestNamesItsMethodAndKeepsTheMessageBytes(): void
    {
        $metadata = new Metadata(['x-request-id' => 'r-1']);
        $request = new GrpcRequest('shop.v1.Cart', 'AddItem', "\x0A\x03A-1", $metadata);

        self::assertSame('/shop.v1.Cart/AddItem', $request->path());
        self::assertSame("\x0A\x03A-1", $request->message);
        self::assertSame($metadata, $request->metadata);
        self::assertSame(CallType::Unary, $request->callType);
        self::assertNull($request->deadline);
        self::assertFalse($request->hasDeadline());
        self::assertSame('', $request->peer);
        self::assertSame([], $request->attributes);
    }

    #[Test]
    public function aRequestIsBuiltFromItsPath(): void
    {
        $request = GrpcRequest::fromPath('/grpc.health.v1.Health/Check', 'm', new Metadata(['a' => 'b']));

        self::assertSame('grpc.health.v1.Health', $request->service);
        self::assertSame('Check', $request->method);
        self::assertSame('m', $request->message);
        self::assertSame('b', $request->metadata->get('a'));
        self::assertSame('', GrpcRequest::fromPath('/a/B')->message);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidPaths(): iterable
    {
        yield 'no leading slash' => ['shop.Cart/AddItem'];
        yield 'no method' => ['/shop.Cart'];
        yield 'empty method' => ['/shop.Cart/'];
        yield 'extra segment' => ['/shop.Cart/AddItem/x'];
        yield 'empty service' => ['//AddItem'];
        yield 'query' => ['/shop.Cart/AddItem?x=1'];
    }

    #[Test]
    #[DataProvider('invalidPaths')]
    public function aPathOutsideTheGrammarIsRefused(string $path): void
    {
        try {
            GrpcRequest::fromPath($path);
            self::fail('the path was accepted');
        } catch (InvalidMessageException $e) {
            self::assertContains($e->errorCode, [GrpcCode::InvalidPath, GrpcCode::InvalidMethodName], $path);
        }
    }

    /**
     * @return iterable<string, array{string, string, string, GrpcCode}>
     */
    public static function invalidNames(): iterable
    {
        yield 'empty service' => ['', 'M', '', GrpcCode::InvalidServiceName];
        yield 'service with slash' => ['shop/Cart', 'M', '', GrpcCode::InvalidServiceName];
        yield 'service ending in a dot' => ['shop.', 'M', '', GrpcCode::InvalidServiceName];
        yield 'service starting with a digit' => ['1shop.Cart', 'M', '', GrpcCode::InvalidServiceName];
        yield 'service with CR LF' => ["shop\r\nx", 'M', '', GrpcCode::InvalidServiceName];
        yield 'empty method' => ['shop.Cart', '', '', GrpcCode::InvalidMethodName];
        yield 'method with a dot' => ['shop.Cart', 'Add.Item', '', GrpcCode::InvalidMethodName];
        yield 'method with a space' => ['shop.Cart', 'Add Item', '', GrpcCode::InvalidMethodName];
        yield 'peer with port' => ['shop.Cart', 'M', '10.0.0.1:5000', GrpcCode::InvalidPeer];
        yield 'peer as a host name' => ['shop.Cart', 'M', 'proxy.local', GrpcCode::InvalidPeer];
        yield 'peer in brackets' => ['shop.Cart', 'M', '[::1]', GrpcCode::InvalidPeer];
    }

    #[Test]
    #[DataProvider('invalidNames')]
    public function namesAndPeerAreChecked(string $service, string $method, string $peer, GrpcCode $code): void
    {
        try {
            new GrpcRequest($service, $method, peer: $peer);
            self::fail('the request was accepted');
        } catch (InvalidMessageException $e) {
            self::assertSame($code, $e->errorCode);
        }
    }

    #[Test]
    public function identifiersMayUseUnderscoresAndDigits(): void
    {
        $request = new GrpcRequest('_internal.v2_beta.Cart_Service', 'Add_Item2', peer: '2001:db8::1');

        self::assertSame('/_internal.v2_beta.Cart_Service/Add_Item2', $request->path());
        self::assertSame('2001:db8::1', $request->peer);
        self::assertSame('192.0.2.7', $request->withPeer('192.0.2.7')->peer);
    }

    #[Test]
    public function theDeadlineIsReachedAtItsInstant(): void
    {
        $deadline = new \DateTimeImmutable('2026-11-27 12:00:00.500000');
        $request = (new GrpcRequest('a.B', 'C'))->withDeadline($deadline);

        self::assertTrue($request->hasDeadline());
        self::assertFalse($request->isDeadlineExceeded(new \DateTimeImmutable('2026-11-27 12:00:00.499999')));
        self::assertTrue($request->isDeadlineExceeded(new \DateTimeImmutable('2026-11-27 12:00:00.500000')));
        self::assertTrue($request->isDeadlineExceeded(new \DateTimeImmutable('2026-11-27 12:00:01')));
        self::assertFalse((new GrpcRequest('a.B', 'C'))->isDeadlineExceeded(new \DateTimeImmutable('9999-01-01')));
        self::assertFalse($request->withDeadline(null)->hasDeadline());
    }

    #[Test]
    public function attributesAreServerSideValues(): void
    {
        $request = (new GrpcRequest('a.B', 'C'))->withAttribute('tenant', 't-1')->withAttribute('nothing', null);

        self::assertSame('t-1', $request->attribute('tenant'));
        self::assertNull($request->attribute('nothing', 'default'), 'a stored null is a value');
        self::assertSame('default', $request->attribute('absent', 'default'));
        self::assertSame(['tenant' => 't-1', 'nothing' => null], $request->attributes);
        self::assertSame('t-2', $request->withAttribute('tenant', 't-2')->attribute('tenant'));
    }

    #[Test]
    public function withersKeepEverythingElse(): void
    {
        $deadline = new \DateTimeImmutable('2026-11-27 12:00:00');
        $metadata = new Metadata(['k' => 'v']);
        $request = new GrpcRequest('a.B', 'C', 'm', $metadata, CallType::ServerStreaming, $deadline, '10.0.0.1', ['x' => 1]);

        foreach ([
            $request->withMessage('n'),
            $request->withMetadata(new Metadata()),
            $request->withCallType(CallType::Unary),
            $request->withDeadline(null),
            $request->withPeer('10.0.0.2'),
            $request->withAttribute('y', 2),
        ] as $copy) {
            self::assertSame('a.B', $copy->service);
            self::assertSame('C', $copy->method);
        }

        $changed = $request->withMessage('n')->withMetadata(new Metadata())->withCallType(CallType::Unary)->withDeadline(null)->withPeer('10.0.0.2')->withAttribute('y', 2);
        self::assertSame('n', $changed->message);
        self::assertTrue($changed->metadata->isEmpty());
        self::assertSame(CallType::Unary, $changed->callType);
        self::assertNull($changed->deadline);
        self::assertSame('10.0.0.2', $changed->peer);
        self::assertSame(['x' => 1, 'y' => 2], $changed->attributes);

        $kept = $request->withAttribute('y', 2);
        self::assertSame('m', $kept->message);
        self::assertSame($metadata, $kept->metadata);
        self::assertSame(CallType::ServerStreaming, $kept->callType);
        self::assertSame($deadline, $kept->deadline);
        self::assertSame('10.0.0.1', $kept->peer);
        self::assertSame('m', $request->withPeer('10.0.0.3')->message);
        self::assertSame(['x' => 1], $request->withMessage('z')->attributes);
        self::assertSame($deadline, $request->withCallType(CallType::Unary)->deadline);
        self::assertSame('10.0.0.1', $request->withDeadline(null)->peer);
        self::assertSame(CallType::ServerStreaming, $request->withMetadata(new Metadata())->callType);
    }
}
