<?php

declare(strict_types=1);

namespace Documentation\ArrayAccess;

use ArrayAccess;
use InvalidArgumentException;

use function method_exists;
use function sprintf;

abstract class GetterSetterAccessObject implements ArrayAccess
{
    public function offsetExists($offset): bool
    {
        $getter = 'get' . $offset;

        // If the method does not exist, we say that the offset does not exist
        if (! method_exists($this, $getter)) {
            return false;
        }

        // In this example we say that exists means it is not null
        return $this->$getter() !== null;
    }

    public function offsetGet($offset): mixed
    {
        $getter = 'get' . $offset;

        // If the method does not exist, we say that the offset does not exist
        if (! method_exists($this, $getter)) {
            throw new InvalidArgumentException(sprintf('The property "%s" is not readable.', $offset));
        }

        return $this->$getter();
    }

    public function offsetSet($offset, $value): void
    {
        $setter = 'set' . $offset;

        if (! method_exists($this, $setter)) {
            throw new InvalidArgumentException(sprintf('The property "%s" is not writable.', $offset));
        }

        $this->$setter($value);
    }

    public function offsetUnset($offset): void
    {
        $this->offsetSet($offset, null);
    }
}
