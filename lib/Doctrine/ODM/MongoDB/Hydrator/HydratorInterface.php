<?php

declare(strict_types=1);

namespace Doctrine\ODM\MongoDB\Hydrator;

use Doctrine\ODM\MongoDB\UnitOfWork;
use MongoDB\BSON\Document;

/**
 * The HydratorInterface defines methods all hydrator need to implement
 *
 * @phpstan-import-type Hints from UnitOfWork
 */
interface HydratorInterface
{
    /**
     * Hydrate array of MongoDB document data into the given document object.
     *
     * @phpstan-param Hints $hints
     */
    public function hydrate(object $document, Document $data, array $hints = []): array;
}
