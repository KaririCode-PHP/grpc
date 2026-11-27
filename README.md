# KaririCode gRPC

The server layer of gRPC for PHP 8.4: services and method descriptors, an
interceptor pipeline, a dispatcher that maps every outcome to a gRPC status
and enforces deadlines, the wire rules a transport needs (length-prefixed
messages, grpc-timeout, grpc-message, metadata), client-certificate trust,
and the bridge to [kariricode/router](https://github.com/KaririCode-PHP/router).
HTTP/2 itself belongs to the transport that carries the calls.

[![CI](https://github.com/KaririCode-PHP/grpc/actions/workflows/ci.yml/badge.svg)](https://github.com/KaririCode-PHP/grpc/actions/workflows/ci.yml)

## Installation

```bash
composer require kariricode/grpc
```

Requires PHP 8.4. ext-openssl is suggested: with it, a PEM forwarded by a
proxy gives the certificate's validity dates.

## A service

```php
use KaririCode\Grpc\Codec\JsonCodec;
use KaririCode\Grpc\Contract\ServiceHandler;
use KaririCode\Grpc\Handler\UnaryHandler;
use KaririCode\Grpc\Message\GrpcRequest;
use KaririCode\Grpc\Message\GrpcResponse;
use KaririCode\Grpc\Message\Status;
use KaririCode\Grpc\Server\MethodDescriptor;
use KaririCode\Grpc\Server\ServiceRegistry;

final class CartService implements ServiceHandler
{
    public function serviceName(): string
    {
        return 'shop.Cart';
    }

    public function handle(string $method, GrpcRequest $request): GrpcResponse
    {
        $codec = new JsonCodec();
        $item = $codec->decode($request->message);   // or: (new AddItemRequest())->mergeFromString($request->message)

        return ($item['quantity'] ?? 0) > 0
            ? GrpcResponse::ok($codec->encode(['added' => $item['sku']]))
            : GrpcResponse::error(Status::invalidArgument('quantity must be positive'));
    }
}

$registry = new ServiceRegistry();
$registry->register(new CartService(), new MethodDescriptor('shop.Cart', 'AddItem'));

$handler = new UnaryHandler($registry, onError: static fn(\Throwable $e, GrpcRequest $r) => $logger->error($e->getMessage(), ['path' => $r->path()]));
$response = $handler->handle($request);
```

The message is the bytes the client sent; protobuf services decode them
with the classes protoc generates. An unknown service or undeclared method
gets UNIMPLEMENTED, a passed deadline DEADLINE_EXCEEDED, and an unexpected
exception INTERNAL with the fixed message "Internal error": the exception
goes to `onError`, never to the client. To end a call from deep inside,
throw `StatusException::failed(Status::notFound('No such cart'))`.

## Interceptors

```php
use KaririCode\Grpc\Contract\Interceptor;
use KaririCode\Grpc\Handler\InterceptorPipeline;

final class RequireApiKey implements Interceptor
{
    public function __construct(private readonly string $key) {}

    public function intercept(GrpcRequest $request, \Closure $next): GrpcResponse
    {
        return hash_equals($this->key, $request->metadata->get('x-api-key') ?? '')
            ? $next($request)
            : GrpcResponse::error(Status::unauthenticated());
    }
}

$pipeline = (new InterceptorPipeline())->with(new RequireApiKey($key));   // the first is the outermost
$handler = new UnaryHandler($registry, $pipeline);
```

## In a transport

```php
use KaririCode\Grpc\Message\Metadata;
use KaririCode\Grpc\Wire\MessageFramer;
use KaririCode\Grpc\Wire\StatusMessage;
use KaririCode\Grpc\Wire\Timeout;

$framer = new MessageFramer();                         // 4 MiB receive limit, checked from the prefix
[$frame] = $framer->parse($body) ?? throw new \UnderflowException('incomplete message');
$request = GrpcRequest::fromPath($headers[':path'], $frame->message, new Metadata($customHeaders))
    ->withPeer($remoteIp);

if (isset($headers['grpc-timeout'])) {
    $request = $request->withDeadline(Timeout::deadline($headers['grpc-timeout'], $clock->now()));
}

$response = $handler->handle($request);
$body = $framer->frame($response->message);
$trailers = ['grpc-status' => (string) $response->status->code->value, 'grpc-message' => StatusMessage::encode($response->status->message)];
```

`Metadata` refuses keys outside 0-9, a-z, `_`, `-`, `.`, keys that start
with `grpc-`, and control characters in ASCII values, so no value can split
the headers or forge `grpc-status`.

## Client certificates (mTLS)

```php
use KaririCode\Grpc\Integration\MutualTlsInterceptor;
use KaririCode\Http\Security\TlsConfiguration;

$tls = TlsConfiguration::mutual($cert, $key, $ca)
    ->withTrustedProxies(['10.0.0.0/8'])                // Envoy or nginx in front
    ->withAllowedDns(['CN=billing,O=Acme']);
$pipeline = (new InterceptorPipeline())->with(new MutualTlsInterceptor($tls));

// in a handler
$request->attribute(MutualTlsInterceptor::CLIENT_CERTIFICATE)?->commonName();
```

The certificate counts only when the transport verified it (the
`PEER_CERTIFICATE` attribute) or when a trusted proxy forwards it; from
anyone else, the proxy metadata is removed.

## Routing

```php
use KaririCode\Grpc\Enum\CallType;
use KaririCode\Grpc\Integration\GrpcNormalizer;
use KaririCode\Router\Core\RouteRegistry;

$routes = (new RouteRegistry())->route([CallType::Unary->routerMethod()], '/shop.Cart/AddItem', 'cart.add');
$normalized = (new GrpcNormalizer())->normalize($request);
$match = $routes->match($normalized->method, $normalized->uri);
```

## Errors

Each failure is a final exception in a kariricode/contract family, with a code
from block 1900–1999 (prefix `GRPC`):

| Exception | Family | Codes |
|---|---|---|
| `StatusException` | Runtime, with the `Status` the client receives | 1900 |
| `InvalidMessageException` | Validation | 1910–1920 |
| `FrameException` | Protocol, with the `StatusCode` to answer | 1930–1931 |
| `InvalidConfigurationException` | Configuration | 1940–1945 |
| `UnsupportedRequestException` | Validation | 1950 |

Messages name the rule that failed and never repeat a value the client sent.

## Development

```bash
composer install
kcode init          # KaririCode Devkit 1.2.1: installs the pinned toolchain into .kcode/
composer check      # style, PHPStan (max), Psalm (1), complexity, tests, mutation
composer security   # composer audit (runtime and toolchain), secret scan
```

## Documentation

| Document | Topic |
|---|---|
| [SPEC-001](docs/spec/SPEC-001-grpc-architecture.md) | architecture and behaviour |
| [ADR-001](docs/adr/ADR-001-dispatch-and-interceptors.md) | dispatch, interceptors, deadlines, statuses |
| [ADR-002](docs/adr/ADR-002-message-model-and-codecs.md) | message bytes and codecs |
| [ADR-003](docs/adr/ADR-003-exception-families-and-code-block.md) | exception families, codes 1900–1999 |
| [ADR-004](docs/adr/ADR-004-toolchain-through-kcode.md) | quality gates |
| [ADR-005](docs/adr/ADR-005-client-certificate-trust.md) | client-certificate trust |
| [ADR-006](docs/adr/ADR-006-wire-format.md) | framing, timeouts, status messages, metadata |
| [Quality report](docs/quality/QD-V433-COMPLIANCE.md) | Spec V4.3.3 gates and deviations |
| [Security policy](SECURITY.md) | reporting, threat model |

## License

MIT. See [LICENSE](LICENSE).
