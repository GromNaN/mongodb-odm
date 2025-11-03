<?php

declare(strict_types=1);

namespace Documents;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

#[ODM\File]
class FileWithoutMetadata
{
    #[ODM\Id]
    public string $id;

    #[ODM\File\Filename]
    public ?string $filename;
}
