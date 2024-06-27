Implementing ArrayAccess for Domain Objects
===========================================

.. sectionauthor:: Roman Borschel <roman@code-factory.org>

This recipe will show you how to implement ArrayAccess for your
domain objects in order to allow more uniform access, for example
in templates. In these examples we will implement ArrayAccess on a
`Layer Supertype <http://martinfowler.com/eaaCatalog/layerSupertype.html>`_
for all our domain objects.

Option 1: Dynamic Property Access
---------------------------------

In this implementation we will make use of PHP's highly dynamic
nature to dynamically access properties of a subtype in a supertype
at runtime. Note that this implementation has 2 main caveats:

-  It will not work with private fields
-  It will not go through any getters/setters

.. code-block:: php

    <?php

    abstract class DomainObject implements ArrayAccess
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

Option 2: Dynamic Getter/Setter Invocation
------------------------------------------

In this implementation we will dynamically invoke getters/setters.
Again we use PHPs dynamic nature to invoke methods on a subtype
from a supertype at runtime. This implementation has the following
caveats:

-  It relies on a naming convention
-  The semantics of offsetExists can differ
-  offsetUnset will not work with typehinted setters

.. code-block:: php

    <?php

    abstract class DomainObject implements ArrayAccess
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

            // In this example we say that exists means it is not null
            return $this->$getter() !== null;
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

Read-only
---------

You can slightly tweak option 1 or option 2 in order to make array access
read-only. Make ``offsetSet`` and ``offsetUnset`` throw an exception (i.e.
``BadMethodCallException```).

.. code-block:: php

    <?php

    trait ReadOnlyArrayAccessTrait
    {
        public function offsetSet($offset, $value): void
        {
            throw new BadMethodCallException(sprintf('Array access of class "%s" is read-only!', static::class);
        }

        public function offsetUnset($offset): void
        {
            throw new BadMethodCallException(sprintf('Array access of class "%s" is read-only!', static::class);
        }
    }
