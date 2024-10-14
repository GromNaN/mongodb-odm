<?php

declare(strict_types=1);

namespace Doctrine\ODM\MongoDB;

use InvalidArgumentException as BaseInvalidArgumentException;

use function get_debug_type;
use function is_scalar;
use function sprintf;

/**
 * Contains exception messages for all invalid lifecycle state exceptions inside UnitOfWork
 */
final class InvalidArgumentException extends BaseInvalidArgumentException
{
    public static function proxyDirectoryRequired(): self
    {
        return new self('You must configure a proxy directory. See docs for details');
    }

    public static function proxyNamespaceRequired(): self
    {
        return new self('You must configure a proxy namespace');
    }

    public static function proxyDirectoryNotWritable(string $proxyDirectory): self
    {
        return new self(sprintf('Your proxy directory "%s" must be writable', $proxyDirectory));
    }

    public static function invalidAutoGenerateMode(mixed $value): self
    {
        return new self(sprintf('Invalid auto generate mode "%s" given.', is_scalar($value) ? (string) $value : get_debug_type($value)));
    }

    public static function missingPrimaryKeyValue(string $className, string $idField): self
    {
        return new self(sprintf('Missing value for primary key %s on %s', $idField, $className));
    }
}
