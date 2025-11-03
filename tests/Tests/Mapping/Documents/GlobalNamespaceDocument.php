<?php

declare(strict_types=1);

use Doctrine\Common\Collections\Collection;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

#[ODM\Document]
class DoctrineGlobal_Article
{
    #[ODM\Id]
    protected string $id;

    #[ODM\Field]
    protected ?string $headline;

    #[ODM\Field]
    protected ?string $text;

    #[ODM\ReferenceOne(targetDocument: DoctrineGlobal_User::class)]
    protected ?DoctrineGlobal_User $author;

    /**
     * @var Collection<int, DoctrineGlobal_User>
     */
    #[ODM\ReferenceMany(targetDocument: DoctrineGlobal_User::class)]
    protected Collection $editor;
}

#[ODM\Document]
class DoctrineGlobal_User
{
    #[ODM\Id]
    private string $id;

    #[ODM\Field]
    private string $username;

    #[ODM\Field]
    private string $email;
}
