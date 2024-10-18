<?php

declare(strict_types=1);

namespace Doctrine\ODM\MongoDB\Proxy;

use Doctrine\Persistence\Mapping\ProxyClassNameResolver;
use Doctrine\Persistence\Proxy;

use function strrpos;
use function substr;

/** @internal */
final class DefaultProxyClassNameResolver implements ProxyClassNameResolver
{
    public function resolveClassName(string $className): string
    {
        $pos = strrpos($className, '\\' . Proxy::MARKER . '\\');

        if ($pos === false) {
            return $className;
        }

        return substr($className, $pos + Proxy::MARKER_LENGTH + 2);
    }

    /**
     * @deprecated Use dependency injection instead
     *
     * @return class-string
     */
    public static function getClass(object $object): string
    {
        return (new self())->resolveClassName($object::class);
    }
}
