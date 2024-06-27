<?php

declare(strict_types=1);

namespace Documentation\ArrayAccess;

use ArrayAccess;
use InvalidArgumentException;

use function property_exists;
use function sprintf;

abstract class PropertyAccessObject implements ArrayAccess
{
    public function offsetExists($offset): bool
    {
        return isset($this->$offset);
    }

    public function offsetGet($offset): mixed
    {
        if (! property_exists($this, $offset)) {
            throw new InvalidArgumentException(sprintf('The property "%s" is not defined.', $offset));
        }

        return $this->$offset;
    }

    public function offsetSet($offset, $value): void
    {
        if (! property_exists($this, $offset)) {
            throw new InvalidArgumentException(sprintf('The property "%s" is not defined.', $offset));
        }

        $this->$offset = $value;
    }

    public function offsetUnset($offset): void
    {
        unset($this->$offset);
    }
}
