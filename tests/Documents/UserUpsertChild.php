<?php

declare(strict_types=1);

namespace Documents;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

/** @ODM\Document(collection="users_upsert") */
class UserUpsertChild extends UserUpsert
{
}
