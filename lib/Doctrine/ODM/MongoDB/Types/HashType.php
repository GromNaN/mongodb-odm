<?php

declare(strict_types=1);

namespace Doctrine\ODM\MongoDB\Types;

use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\MongoDBException;
use MongoDB\BSON\Document;

use function is_array;

/**
 * The Hash type.
 */
class HashType extends Type
{
    public function convertToDatabaseValue($value)
    {
        if ($value instanceof Document) {
            // Check the Document could be returned directly, it is serialized in the UoW
            return (object) $value->toPHP(DocumentManager::CLIENT_TYPEMAP);
        }

        if ($value !== null && ! is_array($value)) {
            throw MongoDBException::invalidValueForType('Hash', ['array', 'null'], $value);
        }

        return $value !== null ? (object) $value : null;
    }

    public function convertToPHPValue($value)
    {
        return $value !== null ? (array) $value : null;
    }
}
