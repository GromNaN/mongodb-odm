<?php

declare(strict_types=1);

namespace Doctrine\ODM\MongoDB;

use Doctrine\Common\Collections\AbstractLazyCollection;
use Doctrine\Common\Collections\Collection as BaseCollection;
use Doctrine\ODM\MongoDB\PersistentCollection\PersistentCollectionInterface;
use Doctrine\ODM\MongoDB\Utility\CollectionHelper;

use function array_combine;
use function array_diff_key;
use function array_map;
use function array_udiff_assoc;
use function array_values;
use function count;
use function is_object;

/**
 * A PersistentCollection represents a collection of elements that have persistent state.
 *
 * @template TKey of array-key
 * @template T of object
 * @template-implements PersistentCollectionInterface<TKey,T>
 */
final class PersistentCollection extends AbstractLazyCollection implements PersistentCollectionInterface
{
    /**
     * A snapshot of the collection at the moment it was fetched from the database.
     * This is used to create a diff of the collection at commit time.
     *
     * @var array<TKey, T>
     */
    private array $snapshot = [];

    /**
     * Collection's owning document
     */
    private ?object $owner = null;

    /**
     * @var array<string, mixed>|null
     * @phpstan-var FieldMapping|null
     */
    private ?array $mapping = null;

    /**
     * Whether the collection is dirty and needs to be synchronized with the database
     * when the UnitOfWork that manages its persistent state commits.
     */
    private bool $isDirty = false;

    /**
     * The DocumentManager that manages the persistence of the collection.
     */
    private DocumentManager $dm;

    /**
     * The UnitOfWork that manages the persistence of the collection.
     */
    private UnitOfWork $uow;

    /**
     * The raw mongo data that will be used to initialize this collection.
     *
     * @var mixed[]
     */
    private array $mongoData = [];

    /**
     * Any hints to account for during reconstitution/lookup of the documents.
     *
     * @var array<int, mixed>
     * @phpstan-var Hints
     */
    private array $hints = [];

    /** @param BaseCollection<TKey, T> $collection */
    public function __construct(BaseCollection $collection, DocumentManager $dm, UnitOfWork $uow)
    {
        $this->collection = $collection;
        $this->dm         = $dm;
        $this->uow        = $uow;
    }

    protected function doInitialize()
    {
        if (! $this->mapping) {
            return;
        }

        /** @var array<TKey, T> $newObjects */
        $newObjects = [];

        if ($this->isDirty) {
            // Remember any NEW objects added through add()
            $newObjects = $this->coll->toArray();
        }

        $this->collection->clear();
        $this->uow->loadCollection($this);
        $this->takeSnapshot();

        $this->mongoData = [];

        // Reattach any NEW objects added through add()
        if (! $newObjects) {
            return;
        }

        foreach ($newObjects as $key => $obj) {
            if (CollectionHelper::isHash($this->mapping['strategy'])) {
                $this->collection->set($key, $obj);
            } else {
                $this->collection->add($obj);
            }
        }

        $this->isDirty = true;
    }

    public function setDocumentManager(DocumentManager $dm)
    {
        $this->dm  = $dm;
        $this->uow = $dm->getUnitOfWork();
    }

    public function setMongoData(array $mongoData)
    {
        $this->mongoData = $mongoData;
    }

    public function getMongoData()
    {
        return $this->mongoData;
    }

    public function setHints(array $hints)
    {
        $this->hints = $hints;
    }

    public function getHints()
    {
        return $this->hints;
    }

    public function isDirty()
    {
        if ($this->isDirty) {
            return true;
        }

        if (! $this->initialized && count($this->collection)) {
            // not initialized collection with added elements
            return true;
        }

        if ($this->initialized) {
            // if initialized let's check with last known snapshot
            return $this->collection->toArray() !== $this->snapshot;
        }

        return false;
    }

    public function setDirty(bool $dirty)
    {
        $this->isDirty = $dirty;
    }

    public function setOwner(object $document, array $mapping)
    {
        $this->owner   = $document;
        $this->mapping = $mapping;
    }

    public function takeSnapshot()
    {
        if ($this->mapping !== null && CollectionHelper::isList($this->mapping['strategy'])) {
            $array = $this->collection->toArray();
            $this->collection->clear();
            foreach ($array as $document) {
                $this->collection->add($document);
            }
        }

        $this->snapshot = $this->collection->toArray();
        $this->isDirty  = false;
    }

    public function clearSnapshot()
    {
        $this->snapshot = [];
        $this->isDirty  = $this->collection->count() !== 0;
    }

    public function getSnapshot()
    {
        return $this->snapshot;
    }

    public function getDeleteDiff()
    {
        return array_udiff_assoc(
            $this->snapshot,
            $this->coll->toArray(),
            static fn ($a, $b) => $a === $b ? 0 : 1,
        );
    }

    public function getDeletedDocuments()
    {
        $collection         = $this->collection->toArray();
        $loadedObjectsByOid = array_combine(array_map('spl_object_id', $this->snapshot), $this->snapshot);
        $newObjectsByOid    = array_combine(array_map('spl_object_id', $collection), $collection);

        return array_values(array_diff_key($loadedObjectsByOid, $newObjectsByOid));
    }

    public function getInsertDiff()
    {
        return array_udiff_assoc(
            $this->collection->toArray(),
            $this->snapshot,
            static fn ($a, $b) => $a === $b ? 0 : 1,
        );
    }

    public function getInsertedDocuments()
    {
        $collection         = $this->collection->toArray();
        $newObjectsByOid    = array_combine(array_map('spl_object_id', $collection), $collection);
        $loadedObjectsByOid = array_combine(array_map('spl_object_id', $this->snapshot), $this->snapshot);

        return array_values(array_diff_key($newObjectsByOid, $loadedObjectsByOid));
    }

    public function getOwner(): ?object
    {
        return $this->owner;
    }

    public function getMapping()
    {
        return $this->mapping;
    }

    public function getTypeClass()
    {
        if (! isset($this->dm)) {
            throw new MongoDBException('No DocumentManager is associated with this PersistentCollection, please set one using setDocumentManager method.');
        }

        if (empty($this->mapping)) {
            throw new MongoDBException('No mapping is associated with this PersistentCollection, please set one using setOwner method.');
        }

        if (empty($this->mapping['targetDocument'])) {
            throw new MongoDBException('Specifying targetDocument is required for the ClassMetadata to be obtained.');
        }

        return $this->dm->getClassMetadata($this->mapping['targetDocument']);
    }

    public function setInitialized($bool)
    {
        $this->initialized = $bool;
    }

    public function unwrap()
    {
        return $this->collection;
    }

    /**
     * Cleanup internal state of cloned persistent collection.
     *
     * The following problems have to be prevented:
     * 1. Added documents are added to old PersistentCollection
     * 2. New collection is not dirty, if reused on other document nothing
     * changes.
     * 3. Snapshot leads to invalid diffs being generated.
     * 4. Lazy loading grabs documents from old owner object.
     * 5. New collection is connected to old owner and leads to duplicate keys.
     */
    public function __clone()
    {
        if (is_object($this->collection)) {
            $this->collection = clone $this->collection;
        }

        $this->initialize();

        $this->owner    = null;
        $this->snapshot = [];

        $this->changed();
    }
}
