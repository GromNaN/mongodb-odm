<?php

declare(strict_types=1);

namespace Documentation\ArrayAccess;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ArrayAccessTest extends TestCase
{
    public function testPropertyAccess(): void
    {
        $object = new class extends PropertyAccessObject {
            protected string $foo = 'bar';
        };

        $this->assertTrue($object->offsetExists('foo'));
        $this->assertSame('bar', $object->offsetGet('foo'));

        $object->offsetUnset('foo');
        $this->assertFalse($object->offsetExists('foo'));

        $object->offsetSet('foo', 'baz');
        $this->assertTrue($object->offsetExists('foo'));
        $this->assertSame('baz', $object->offsetGet('foo'));

        $this->expectExceptionMessage('The property "bar" is not defined.');
        $this->expectException(InvalidArgumentException::class);
        $object->offsetGet('bar');
    }

    public function testGetterSetterAccess(): void
    {
        $object = new class extends GetterSetterAccessObject {
            private ?string $foo = 'bar';

            public function getFoo(): ?string
            {
                return $this->foo;
            }

            public function setFoo(?string $value): void
            {
                $this->foo = $value;
            }
        };

        $this->assertTrue($object->offsetExists('foo'));
        $this->assertSame('bar', $object->offsetGet('foo'));

        $object->offsetUnset('foo');
        $this->assertFalse($object->offsetExists('foo'));

        $object->offsetSet('foo', 'baz');
        $this->assertTrue($object->offsetExists('foo'));
        $this->assertSame('baz', $object->offsetGet('foo'));

        $this->expectExceptionMessage('The property "bar" is not readable.');
        $this->expectException(InvalidArgumentException::class);
        $object->offsetGet('bar');
    }
}
