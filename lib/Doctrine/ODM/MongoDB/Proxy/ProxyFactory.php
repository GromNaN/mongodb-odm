<?php

declare(strict_types=1);

namespace Doctrine\ODM\MongoDB\Proxy;

use Closure;
use Doctrine\ODM\MongoDB\Configuration;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\DocumentNotFoundException;
use Doctrine\ODM\MongoDB\Mapping\ClassMetadata;
use Doctrine\ODM\MongoDB\Persisters\DocumentPersister;
use Doctrine\ODM\MongoDB\UnitOfWork;
use Doctrine\Persistence\Proxy;
use InvalidArgumentException;
use ReflectionProperty;
use Symfony\Component\VarExporter\ProxyHelper;

use function array_combine;
use function array_flip;
use function bin2hex;
use function chmod;
use function class_exists;
use function dirname;
use function file_exists;
use function file_put_contents;
use function filemtime;
use function get_debug_type;
use function is_dir;
use function is_int;
use function is_scalar;
use function is_writable;
use function ltrim;
use function mkdir;
use function preg_match_all;
use function random_bytes;
use function rename;
use function rtrim;
use function sprintf;
use function str_replace;
use function strpos;
use function strrpos;
use function strtr;
use function substr;
use function ucfirst;

use const DIRECTORY_SEPARATOR;

/**
 * This factory is used to create proxy objects for entities at runtime.
 */
class ProxyFactory
{
    private const PROXY_CLASS_TEMPLATE = <<<'EOPHP'
<?php

namespace <namespace>;

/**
 * DO NOT EDIT THIS FILE - IT WAS CREATED BY DOCTRINE'S PROXY GENERATOR
 */
class <proxyShortClassName> extends \<className> implements \<baseProxyInterface>
{
    <useLazyGhostTrait>

    public function __isInitialized(): bool
    {
        return isset($this->lazyObjectState) && $this->isLazyObjectInitialized();
    }

    public function __serialize(): array
    {
        <serializeImpl>
    }
}

EOPHP;

    /** The UnitOfWork this factory uses to retrieve persisters */
    private readonly UnitOfWork $uow;

    /** @var Configuration::AUTOGENERATE_* */
    private $autoGenerate;

    /** @var array<class-string, Closure> */
    private array $proxyFactories = [];

    /**
     * Initializes a new instance of the <tt>ProxyFactory</tt> class that is
     * connected to the given <tt>EntityManager</tt>.
     *
     * @param DocumentManager                    $dm           The EntityManager the new factory works for.
     * @param string                             $proxyDir     The directory to use for the proxy classes. It must exist.
     * @param string                             $proxyNs      The namespace to use for the proxy classes.
     * @param bool|Configuration::AUTOGENERATE_* $autoGenerate The strategy for automatically generating proxy classes.
     */
    public function __construct(
        private readonly DocumentManager $dm,
        private readonly string $proxyDir,
        private readonly string $proxyNs,
        bool|int $autoGenerate = Configuration::AUTOGENERATE_NEVER,
    ) {
        if (! $proxyDir) {
            throw new InvalidArgumentException('You must configure a proxy directory. See docs for details');
        }

        if (! $proxyNs) {
            throw new InvalidArgumentException('You must configure a proxy namespace');
        }

        if (is_int($autoGenerate) && ($autoGenerate < 0 || $autoGenerate > 4)) {
            throw new InvalidArgumentException(sprintf('Invalid auto generate mode "%s" given.', is_scalar($autoGenerate) ? (string) $autoGenerate : get_debug_type($autoGenerate)));
        }

        $this->uow          = $dm->getUnitOfWork();
        $this->autoGenerate = (int) $autoGenerate;
    }

    /** @param array<mixed> $identifier */
    public function getProxy(ClassMetadata $metadata, $identifier): InternalProxy
    {
        $className = $metadata->getName();

        $proxyFactory = $this->proxyFactories[$className] ?? $this->getProxyFactory($className);

        return $proxyFactory($identifier);
    }

    /**
     * Generates proxy classes for all given classes.
     *
     * @param ClassMetadata[] $classes  The classes (ClassMetadata instances) for which to generate proxies.
     * @param string|null     $proxyDir The target directory of the proxy classes. If not specified, the
     *                                  directory configured on the Configuration of the EntityManager used
     *                                  by this factory is used.
     *
     * @return int Number of generated proxies.
     */
    public function generateProxyClasses(array $classes, string|null $proxyDir = null): int
    {
        $generated = 0;

        foreach ($classes as $class) {
            if ($this->skipClass($class)) {
                continue;
            }

            $proxyFileName  = $this->getProxyFileName($class->getName(), $proxyDir ?: $this->proxyDir);
            $proxyClassName = self::generateProxyClassName($class->getName(), $this->proxyNs);

            $this->generateProxyClass($class, $proxyFileName, $proxyClassName);

            ++$generated;
        }

        return $generated;
    }

    protected function skipClass(ClassMetadata $metadata): bool
    {
        return $metadata->isMappedSuperclass
            || $metadata->isEmbeddedDocument
            || $metadata->getReflectionClass()->isAbstract();
    }

    /**
     * Creates a closure capable of initializing a proxy
     *
     * @param ClassMetadata<T> $classMetadata
     *
     * @return Closure(InternalProxy&T, array):void
     *
     * @throws DocumentNotFoundException
     *
     * @template T of object
     */
    private function createLazyInitializer(ClassMetadata $classMetadata, DocumentPersister $persister): Closure
    {
        return static function (InternalProxy $proxy, mixed $identifier) use ($persister, $classMetadata): void {
            $original = $persister->load(['_id' => $identifier]);

            if ($original === null) {
                throw DocumentNotFoundException::documentNotFound(
                    $classMetadata->getName(),
                    $identifier,
                );
            }

            if ($proxy === $original) {
                return;
            }

            $class = $persister->getClassMetadata();

            foreach ($class->getReflectionProperties() as $property) {
                if (! $property || isset($identifier[$property->getName()]) || ! $class->hasField($property->getName()) && ! $class->hasAssociation($property->getName())) {
                    continue;
                }

                $property->setValue($proxy, $property->getValue($original));
            }
        };
    }

    private function getProxyFileName(string $className, string $baseDirectory): string
    {
        $baseDirectory = $baseDirectory ?: $this->proxyDir;

        return rtrim($baseDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . InternalProxy::MARKER
            . str_replace('\\', '', $className) . '.php';
    }

    private function getProxyFactory(string $className): Closure
    {
        $skippedProperties = [];
        $class             = $this->dm->getClassMetadata($className);
        $identifiers       = array_flip($class->getIdentifierFieldNames());
        $filter            = ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PRIVATE;
        $reflector         = $class->getReflectionClass();

        while ($reflector) {
            foreach ($reflector->getProperties($filter) as $property) {
                $name = $property->name;

                if ($property->isStatic() || (($class->hasField($name) || $class->hasAssociation($name)))) {
                    continue;
                }

                $prefix = $property->isPrivate() ? "\0" . $property->class . "\0" : ($property->isProtected() ? "\0*\0" : '');

                $skippedProperties[$prefix . $name] = true;
            }

            $filter    = ReflectionProperty::IS_PRIVATE;
            $reflector = $reflector->getParentClass();
        }

        $className       = $class->getName(); // aliases and case sensitivity
        $entityPersister = $this->uow->getDocumentPersister($className);
        $initializer     = $this->createLazyInitializer($class, $entityPersister);
        $proxyClassName  = $this->loadProxyClass($class);

        $proxyFactory = Closure::bind(static function (mixed $identifier) use ($initializer, $skippedProperties, $class): InternalProxy {
            $proxy = self::createLazyGhost(static function (InternalProxy $object) use ($initializer, $identifier): void {
                $initializer($object, $identifier);
            }, $skippedProperties);

            $class->setIdentifierValue($proxy, $identifier);

            return $proxy;
        }, null, $proxyClassName);

        return $this->proxyFactories[$className] = $proxyFactory;
    }

    private function loadProxyClass(ClassMetadata $class): string
    {
        $proxyClassName = self::generateProxyClassName($class->getName(), $this->proxyNs);

        if (class_exists($proxyClassName, false)) {
            return $proxyClassName;
        }

        if ($this->autoGenerate === Configuration::AUTOGENERATE_EVAL) {
            $this->generateProxyClass($class, null, $proxyClassName);

            return $proxyClassName;
        }

        $fileName = $this->getProxyFileName($class->getName(), $this->proxyDir);

        switch ($this->autoGenerate) {
            case Configuration::AUTOGENERATE_FILE_NOT_EXISTS_OR_CHANGED:
                if (file_exists($fileName) && filemtime($fileName) >= filemtime($class->getReflectionClass()->getFileName())) {
                    break;
                }
            // no break
            case Configuration::AUTOGENERATE_FILE_NOT_EXISTS:
                if (file_exists($fileName)) {
                    break;
                }
            // no break
            case Configuration::AUTOGENERATE_ALWAYS:
                $this->generateProxyClass($class, $fileName, $proxyClassName);
                break;
        }

        require $fileName;

        return $proxyClassName;
    }

    private function generateProxyClass(ClassMetadata $class, string|null $fileName, string $proxyClassName): void
    {
        $i            = strrpos($proxyClassName, '\\');
        $placeholders = [
            '<className>' => $class->getName(),
            '<namespace>' => substr($proxyClassName, 0, $i),
            '<proxyShortClassName>' => substr($proxyClassName, 1 + $i),
            '<baseProxyInterface>' => InternalProxy::class,
        ];

        preg_match_all('(<([a-zA-Z]+)>)', self::PROXY_CLASS_TEMPLATE, $placeholderMatches);

        foreach (array_combine($placeholderMatches[0], $placeholderMatches[1]) as $placeholder => $name) {
                $placeholders[$placeholder] ?? $placeholders[$placeholder] = $this->{'generate' . ucfirst($name)}($class);
        }

        $proxyCode = strtr(self::PROXY_CLASS_TEMPLATE, $placeholders);

        if (! $fileName) {
            if (! class_exists($proxyClassName)) {
                eval(substr($proxyCode, 5));
            }

            return;
        }

        $parentDirectory = dirname($fileName);

        if (! is_dir($parentDirectory) && ! @mkdir($parentDirectory, 0775, true) || ! is_writable($parentDirectory)) {
            throw new InvalidArgumentException(sprintf('Your proxy directory "%s" must be writable', $this->proxyDir));
        }

        $tmpFileName = $fileName . '.' . bin2hex(random_bytes(12));

        file_put_contents($tmpFileName, $proxyCode);
        @chmod($tmpFileName, 0664);
        rename($tmpFileName, $fileName);
    }

    private function generateUseLazyGhostTrait(ClassMetadata $class): string
    {
        $code = ProxyHelper::generateLazyGhost($class->getReflectionClass());
        $code = substr($code, 7 + (int) strpos($code, "\n{"));
        $code = substr($code, 0, (int) strpos($code, "\n}"));
        $code = str_replace('LazyGhostTrait;', str_replace("\n    ", "\n", 'LazyGhostTrait {
            initializeLazyObject as private;
            setLazyObjectAsInitialized as public __setInitialized;
            isLazyObjectInitialized as private;
            createLazyGhost as private;
            resetLazyObject as private;
        }

        public function __load(): void
        {
            $this->initializeLazyObject();
        }
        '), $code);

        return $code;
    }

    private function generateSerializeImpl(ClassMetadata $class): string
    {
        $reflector  = $class->getReflectionClass();
        $properties = $reflector->hasMethod('__serialize') ? 'parent::__serialize()' : '(array) $this';

        $code = '$properties = ' . $properties . ';
        unset($properties["\0" . self::class . "\0lazyObjectState"]);

        ';

        if ($reflector->hasMethod('__serialize') || ! $reflector->hasMethod('__sleep')) {
            return $code . 'return $properties;';
        }

        return $code . '$data = [];

        foreach (parent::__sleep() as $name) {
            $value = $properties[$k = $name] ?? $properties[$k = "\0*\0$name"] ?? $properties[$k = "\0' . $reflector->name . '\0$name"] ?? $k = null;

            if (null === $k) {
                trigger_error(sprintf(\'serialize(): "%s" returned as member variable from __sleep() but does not exist\', $name), \E_USER_NOTICE);
            } else {
                $data[$k] = $value;
            }
        }

        return $data;';
    }

    private static function generateProxyClassName(string $className, string $proxyNamespace): string
    {
        return rtrim($proxyNamespace, '\\') . '\\' . Proxy::MARKER . '\\' . ltrim($className, '\\');
    }
}
