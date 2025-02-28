<?php

declare(strict_types=1);

namespace Doctrine\ODM\MongoDB\Tests\Events;

use Doctrine\ODM\MongoDB\Event\PreLoadEventArgs;
use Doctrine\ODM\MongoDB\Tests\BaseTestCase;
use Documents\Group;
use MongoDB\BSON\Document;

class PreLoadEventArgsTest extends BaseTestCase
{
    public function testGetData(): void
    {
        $document = new Group('test');
        $dm       = $this->dm;
        $data     = Document::fromPHP(['id' => '1234', 'name' => 'test']);

        $eventArgs     = new PreLoadEventArgs($document, $dm, $data);
        $eventArgsData =& $eventArgs->getData();

        self::assertEquals('test', $eventArgsData['name']);

        $eventArgsData['name'] = 'alt name';
        unset($eventArgs);

        self::assertEquals('alt name', $data->get('name'));
    }
}
