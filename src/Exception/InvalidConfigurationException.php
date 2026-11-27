<?php

declare(strict_types=1);

namespace KaririCode\Grpc\Exception;

use KaririCode\Exception\ConfigurationException;

/**
 * The server was wired wrong: a size limit below one byte, a method
 * descriptor or service handler with a malformed name, a service or
 * method registered twice, or a descriptor given to another service. The
 * names in messages come from the application's own configuration.
 *
 * ARFA 1.3: P5 (fail at bootstrap, not at the first call). Spec V4.3.3:
 * final, a kariricode/contract Configuration failure, built by named
 * constructors.
 *
 * Preconditions: none.
 *
 * Thread-safety: an exception belongs to the call that threw it.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     $registry->register(new CartService(), new MethodDescriptor('shop.Cart', 'AddItem'));
 *     $registry->register(new CartService());   // InvalidConfigurationException 1943
 *
 * References: KaririCode Spec V4.3.3 §8.4; ADR-001, ADR-003.
 *
 * @see https://github.com/KaririCode-PHP/exception/blob/main/CODE-BLOCKS.md
 */
final class InvalidConfigurationException extends ConfigurationException
{
    public static function nonPositiveLimit(string $name): self
    {
        return new self(GrpcCode::NonPositiveLimit, sprintf('The limit %s must be at least 1.', self::quote($name)));
    }

    public static function invalidDescriptorService(string $service): self
    {
        return new self(GrpcCode::InvalidDescriptorService, sprintf('The service name %s is not dot-separated identifiers.', self::quote($service)));
    }

    public static function invalidDescriptorMethod(string $method): self
    {
        return new self(GrpcCode::InvalidDescriptorMethod, sprintf('The method name %s is not one identifier.', self::quote($method)));
    }

    public static function duplicateService(string $service): self
    {
        return new self(GrpcCode::DuplicateService, sprintf('The service %s is already registered.', self::quote($service)));
    }

    public static function duplicateMethod(string $path): self
    {
        return new self(GrpcCode::DuplicateMethod, sprintf('The method %s is described twice.', self::quote($path)));
    }

    public static function foreignMethod(string $path, string $service): self
    {
        return new self(GrpcCode::ForeignMethod, sprintf('The method %s does not belong to the service %s.', self::quote($path), self::quote($service)));
    }
}
