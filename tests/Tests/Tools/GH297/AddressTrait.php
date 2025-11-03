<?php

declare(strict_types=1);

namespace Doctrine\ODM\MongoDB\Tests\Tools\GH297;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

trait AddressTrait
{
    #[ODM\EmbedOne]
    private ?Address $address;

    public function getAddress(): ?Address
    {
        return $this->address;
    }

    public function setAddress(Address $address): void
    {
        $this->address = $address;
    }
}
