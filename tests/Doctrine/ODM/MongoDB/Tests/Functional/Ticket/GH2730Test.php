<?php

declare(strict_types=1);

namespace Doctrine\ODM\MongoDB\Tests\Functional\Ticket;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use Doctrine\ODM\MongoDB\Tests\BaseTestCase;

class GH2730Test extends BaseTestCase
{
    public function testUniqueObjectIdentifier(): void
    {
        $document = new GH2730Document();
        $this->dm->persist($document);
        $this->dm->flush();

        // Remove the document
        $this->dm->remove($document);
        $this->dm->flush();
        // Remove the last reference to the document
        unset($document);

        // Create a new document
        $document = new GH2730Document();
        $this->dm->persist($document);
        $this->dm->flush();

        self::assertSame(1, $this->dm->getDocumentCollection(GH2730Document::class)->countDocuments());
    }
}

#[ODM\Document]
class GH2730Document
{
    #[ODM\Id]
    public ?string $id = null;
}
