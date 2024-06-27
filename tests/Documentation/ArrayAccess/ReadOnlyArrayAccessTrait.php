<?php

declare(strict_types=1);

namespace Documentation\ArrayAccess;

use BadMethodCallException;

use function sprintf;

trait ReadOnlyArrayAccessTrait
{
    public function offsetSet($offset, $value): void
    {
        throw new BadMethodCallException(sprintf('Array access of class "%s" is read-only!', static::class));
    }

    public function offsetUnset($offset): void
    {
        throw new BadMethodCallException(sprintf('Array access of class "%s" is read-only!', static::class));
    }
}
