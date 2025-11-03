<?php

declare(strict_types=1);

namespace Documents;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

#[ODM\Document]
class CmsUser
{
    #[ODM\Id]
    public string $id;

    #[ODM\Field]
    public string $status;

    #[ODM\Field]
    public ?string $username;

    #[ODM\Field]
    public ?string $name;

    /** @var Collection<int, CmsPhonenumber> */
    #[ODM\ReferenceMany(targetDocument: CmsPhonenumber::class, mappedBy: 'user', cascade: ['persist', 'remove', 'merge'])]
    public Collection $phonenumbers;

    /** @var Collection<int, CmsArticle> */
    #[ODM\ReferenceMany(targetDocument: CmsArticle::class)]
    public Collection $articles;

    #[ODM\ReferenceOne(targetDocument: CmsAddress::class, cascade: ['persist'])]
    public CmsAddress $address;

    /** @var Collection<int, CmsGroup> */
    #[ODM\ReferenceMany(targetDocument: CmsGroup::class, cascade: ['persist', 'merge'])]
    public Collection $groups;

    public function __construct()
    {
        $this->phonenumbers = new ArrayCollection();
        $this->articles     = new ArrayCollection();
        $this->groups       = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Adds a phonenumber to the user.
     */
    public function addPhonenumber(CmsPhonenumber $phone): void
    {
        $this->phonenumbers[] = $phone;
        $phone->setUser($this);
    }

    /** @return Collection<int, CmsPhonenumber> */
    public function getPhonenumbers(): Collection
    {
        return $this->phonenumbers;
    }

    public function addArticle(CmsArticle $article): void
    {
        $this->articles[] = $article;
        $article->setAuthor($this);
    }

    public function addGroup(CmsGroup $group): void
    {
        $this->groups[] = $group;
        $group->addUser($this);
    }

    /** @return Collection<int, CmsGroup> */
    public function getGroups(): Collection
    {
        return $this->groups;
    }

    public function removePhonenumber(int $index): bool
    {
        if (isset($this->phonenumbers[$index])) {
            $ph = $this->phonenumbers[$index];
            unset($this->phonenumbers[$index]);
            $ph->user = null;

            return true;
        }

        return false;
    }

    public function getAddress(): CmsAddress
    {
        return $this->address;
    }

    public function setAddress(CmsAddress $address): void
    {
        if ($this->address === $address) {
            return;
        }

        $this->address = $address;
        $address->setUser($this);
    }
}
