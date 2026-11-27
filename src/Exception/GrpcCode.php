<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Exception;

use KaririCode\Exception\CodeBlock;
use KaririCode\Exception\ErrorCode;

/**
 * The error codes of kariricode/grpc: block 1900–1999 of the ecosystem
 * registry under the prefix GRPC, so a log line that starts with
 * `[GRPC_1930]` names this package and one failure. These codes say what
 * went wrong inside the server; the gRPC status a client receives is a
 * separate value (Enum\StatusCode), which a FrameException and a
 * StatusException carry. The layout:
 *
 *     1900–1909  a call ended by the application with a status
 *     1910–1929  values from a client or the transport: metadata, names, the peer, timeouts, JSON
 *     1930–1939  length-prefixed messages
 *     1940–1949  configuration: limits, method descriptors, the service registry
 *     1950–1959  the router normalizer
 *
 * Preconditions: a code is stable API (registry rule 4). A case is never
 * renumbered and a retired value is never reused.
 *
 * Thread-safety: enum cases are immutable.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     try {
 *         $result = $framer->parse($buffer);
 *     } catch (FrameException $e) {
 *         $e->errorCode;   // GrpcCode::MessageTooLarge (1930)
 *         $e->status;      // StatusCode::ResourceExhausted, for the client
 *     }
 *
 * References: KaririCode Spec V4.3.3 §8.4; kariricode/exception ErrorCode
 * and CODE-BLOCKS.md; ADR-003.
 *
 * @see https://github.com/KaririCode-PHP/exception/blob/main/CODE-BLOCKS.md
 */
enum GrpcCode: int implements ErrorCode
{
    case CallFailed = 1900;

    case InvalidMetadataKey = 1910;
    case ReservedMetadataKey = 1911;
    case InvalidMetadataValue = 1912;
    case InvalidServiceName = 1913;
    case InvalidMethodName = 1914;
    case InvalidPath = 1915;
    case InvalidPeer = 1916;
    case InvalidTimeout = 1917;
    case MalformedJson = 1918;
    case JsonNotAnObject = 1919;
    case UnencodableJson = 1920;

    case MessageTooLarge = 1930;
    case InvalidCompressedFlag = 1931;

    case NonPositiveLimit = 1940;
    case InvalidDescriptorService = 1941;
    case InvalidDescriptorMethod = 1942;
    case DuplicateService = 1943;
    case DuplicateMethod = 1944;
    case ForeignMethod = 1945;

    case NotAGrpcRequest = 1950;

    #[\Override]
    public static function prefix(): string
    {
        return 'GRPC';
    }

    #[\Override]
    public static function block(): CodeBlock
    {
        return new CodeBlock(1900, 1999);
    }
}
