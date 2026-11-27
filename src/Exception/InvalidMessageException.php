<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Exception;

use KaririCode\Exception\ValidationException;

/**
 * A value from a client or the transport breaks a rule of gRPC over
 * HTTP/2 or of the JSON codec: a metadata key or value, a reserved key, a
 * service or method name, a :path, the peer address, a grpc-timeout, or a
 * JSON message that is malformed, not an object, or cannot be encoded.
 * The transport answers the call with INVALID_ARGUMENT or INTERNAL; the
 * message names the rule and never the value (errata E-047).
 *
 * ARFA 1.3: P2 (input checked where it enters). Spec V4.3.3: final, a
 * kariricode/contract Validation failure, built by named constructors.
 *
 * Preconditions: none.
 *
 * Thread-safety: an exception belongs to the call that threw it.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     try {
 *         $request = GrpcRequest::fromPath($headers[':path'], $message, new Metadata($custom));
 *     } catch (InvalidMessageException $e) {
 *         $e->errorCode;   // GrpcCode::InvalidPath (1915), InvalidMetadataKey (1910), ...
 *     }
 *
 * References: gRPC over HTTP/2 (doc/PROTOCOL-HTTP2.md); RFC 8259 (JSON);
 * KaririCode Spec V4.3.3 §8.4; ADR-002, ADR-003, ADR-006.
 *
 * @see https://github.com/grpc/grpc/blob/master/doc/PROTOCOL-HTTP2.md
 */
final class InvalidMessageException extends ValidationException
{
    public static function invalidMetadataKey(): self
    {
        return new self(GrpcCode::InvalidMetadataKey, 'A metadata key is one or more of 0-9, a-z, "_", "-" and ".".');
    }

    public static function reservedMetadataKey(): self
    {
        return new self(GrpcCode::ReservedMetadataKey, 'Metadata keys that start with "grpc-" are reserved for the protocol.');
    }

    public static function invalidMetadataValue(): self
    {
        return new self(GrpcCode::InvalidMetadataValue, 'An ASCII metadata value holds space and printable ASCII only; binary values use a key ending in "-bin".');
    }

    public static function invalidServiceName(): self
    {
        return new self(GrpcCode::InvalidServiceName, 'A service name is dot-separated identifiers, such as "shop.Cart".');
    }

    public static function invalidMethodName(): self
    {
        return new self(GrpcCode::InvalidMethodName, 'A method name is one identifier, such as "AddItem".');
    }

    public static function invalidPath(): self
    {
        return new self(GrpcCode::InvalidPath, 'A gRPC :path is "/" service "/" method.');
    }

    public static function invalidPeer(): self
    {
        return new self(GrpcCode::InvalidPeer, 'The peer is an IPv4 or IPv6 address without a port, or empty.');
    }

    public static function invalidTimeout(): self
    {
        return new self(GrpcCode::InvalidTimeout, 'A grpc-timeout is one to eight digits and a unit: H, M, S, m, u or n.');
    }

    public static function malformedJson(): self
    {
        return new self(GrpcCode::MalformedJson, 'The message is not valid JSON.');
    }

    public static function jsonNotAnObject(): self
    {
        return new self(GrpcCode::JsonNotAnObject, 'A JSON message is an object.');
    }

    public static function unencodableJson(): self
    {
        return new self(GrpcCode::UnencodableJson, 'The message cannot be encoded as JSON: invalid UTF-8, a non-finite number or too deep.');
    }
}
