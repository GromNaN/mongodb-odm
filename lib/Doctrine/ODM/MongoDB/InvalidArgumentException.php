<?php

namespace Doctrine\ODM\MongoDB;

use Doctrine\ORM\Mapping\AssociationMapping;
use Doctrine\ORM\Mapping\ClassMetadata;
use InvalidArgumentException as BasseInvalidArgumentException;
use Stringable;

use function array_map;
use function count;
use function get_debug_type;
use function gettype;
use function implode;
use function is_scalar;
use function reset;
use function spl_object_id;
use function sprintf;

/**
 * Contains exception messages for all invalid lifecycle state exceptions inside UnitOfWork
 */
class InvalidArgumentException extends BasseInvalidArgumentException
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
}