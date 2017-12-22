<?php

namespace Doctrine\ODM\MongoDB\Tests;

use Doctrine\ODM\MongoDB\Mapping\ClassMetadataInfo;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use Doctrine\ODM\MongoDB\Tests\Mocks\DocumentManagerMock;
use Documents\CmsPhonenumber;
use MongoDB\Client;

class DocumentManagerTest extends \Doctrine\ODM\MongoDB\Tests\BaseTest
{
    public function testCustomRepository()
    {
        $this->assertInstanceOf('Documents\CustomRepository\Repository', $this->dm->getRepository('Documents\CustomRepository\Document'));
    }

    public function testCustomRepositoryMappedsuperclass()
    {
        $this->assertInstanceOf('Documents\BaseCategoryRepository', $this->dm->getRepository('Documents\BaseCategory'));
    }

    public function testCustomRepositoryMappedsuperclassChild()
    {
        $this->assertInstanceOf('Documents\BaseCategoryRepository', $this->dm->getRepository('Documents\Category'));
    }

    public function testGetConnection()
    {
        $this->assertInstanceOf(Client::class, $this->dm->getClient());
    }

    public function testGetMetadataFactory()
    {
        $this->assertInstanceOf('\Doctrine\ODM\MongoDB\Mapping\ClassMetadataFactory', $this->dm->getMetadataFactory());
    }

    public function testGetConfiguration()
    {
        $this->assertInstanceOf('\Doctrine\ODM\MongoDB\Configuration', $this->dm->getConfiguration());
    }

    public function testGetUnitOfWork()
    {
        $this->assertInstanceOf('\Doctrine\ODM\MongoDB\UnitOfWork', $this->dm->getUnitOfWork());
    }

    public function testGetProxyFactory()
    {
        $this->assertInstanceOf('\Doctrine\ODM\MongoDB\Proxy\ProxyFactory', $this->dm->getProxyFactory());
    }

    public function testGetEventManager()
    {
        $this->assertInstanceOf('\Doctrine\Common\EventManager', $this->dm->getEventManager());
    }

    public function testGetSchemaManager()
    {
        $this->assertInstanceOf('\Doctrine\ODM\MongoDB\SchemaManager', $this->dm->getSchemaManager());
    }

    public function testCreateQueryBuilder()
    {
        $this->assertInstanceOf('\Doctrine\ODM\MongoDB\Query\Builder', $this->dm->createQueryBuilder());
    }

    public function testCreateAggregationBuilder()
    {
        $this->assertInstanceOf('\Doctrine\ODM\MongoDB\Aggregation\Builder', $this->dm->createAggregationBuilder('Documents\BlogPost'));
    }

    public function testGetFilterCollection()
    {
        $this->assertInstanceOf('\Doctrine\ODM\MongoDB\Query\FilterCollection', $this->dm->getFilterCollection());
    }

    public function testGetPartialReference()
    {
        $id = new \MongoDB\BSON\ObjectId();
        $user = $this->dm->getPartialReference('Documents\CmsUser', $id);
        $this->assertTrue($this->dm->contains($user));
        $this->assertEquals($id, $user->id);
        $this->assertNull($user->getName());
    }

    public function testDocumentManagerIsClosedAccessor()
    {
        $this->assertTrue($this->dm->isOpen());
        $this->dm->close();
        $this->assertFalse($this->dm->isOpen());
    }

    public function dataMethodsAffectedByNoObjectArguments()
    {
        return array(
            array('persist'),
            array('remove'),
            array('merge'),
            array('refresh'),
            array('detach')
        );
    }

    /**
     * @dataProvider dataMethodsAffectedByNoObjectArguments
     * @expectedException \InvalidArgumentException
     * @param string $methodName
     */
    public function testThrowsExceptionOnNonObjectValues($methodName)
    {
        $this->dm->$methodName(null);
    }

    public function dataAffectedByErrorIfClosedException()
    {
        return array(
            array('flush'),
            array('persist'),
            array('remove'),
            array('merge'),
            array('refresh'),
        );
    }

    /**
     * @dataProvider dataAffectedByErrorIfClosedException
     * @param string $methodName
     */
    public function testAffectedByErrorIfClosedException($methodName)
    {
        $this->expectException(\Doctrine\ODM\MongoDB\MongoDBException::class);
        $this->expectExceptionMessage('closed');

        $this->dm->close();
        if ($methodName === 'flush') {
            $this->dm->$methodName();
        } else {
            $this->dm->$methodName(new \stdClass());
        }
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Cannot create a DBRef for class Documents\User without an identifier. Have you forgotten to persist/merge the document first?
     */
    public function testCannotCreateDbRefWithoutId()
    {
        $d = new \Documents\User();
        $this->dm->createReference($d, ['storeAs' => ClassMetadataInfo::REFERENCE_STORE_AS_DB_REF]);
    }

    public function testCreateDbRefWithNonNullEmptyId()
    {
        $phonenumber = new CmsPhonenumber();
        $phonenumber->phonenumber = 0;
        $this->dm->persist($phonenumber);

        $dbRef = $this->dm->createReference($phonenumber, ['storeAs' => ClassMetadataInfo::REFERENCE_STORE_AS_DB_REF, 'targetDocument' => CmsPhonenumber::class]);

        $this->assertSame(array('$ref' => 'CmsPhonenumber', '$id' => 0), $dbRef);
    }

    /**
     * @expectedException \Doctrine\ODM\MongoDB\Mapping\MappingException
     * @expectedExceptionMessage Simple reference must not target document using Single Collection Inheritance, Documents\Tournament\Participant targeted.
     */
    public function testDisriminatedSimpleReferenceFails()
    {
        $d = new WrongSimpleRefDocument();
        $r = new \Documents\Tournament\ParticipantSolo('Maciej');
        $this->dm->persist($r);
        $class = $this->dm->getClassMetadata(get_class($d));
        $this->dm->createReference($r, $class->associationMappings['ref']);
    }

    public function testDifferentStoreAsDbReferences()
    {
        $r = new \Documents\User();
        $this->dm->persist($r);
        $d = new ReferenceStoreAsDocument();
        $class = $this->dm->getClassMetadata(get_class($d));

        $dbRef = $this->dm->createReference($r, $class->associationMappings['ref1']);
        $this->assertInstanceOf(\MongoDB\BSON\ObjectId::class, $dbRef);

        $dbRef = $this->dm->createReference($r, $class->associationMappings['ref2']);
        $this->assertCount(2, $dbRef);
        $this->assertArrayHasKey('$ref', $dbRef);
        $this->assertArrayHasKey('$id', $dbRef);

        $dbRef = $this->dm->createReference($r, $class->associationMappings['ref3']);
        $this->assertCount(3, $dbRef);
        $this->assertArrayHasKey('$ref', $dbRef);
        $this->assertArrayHasKey('$id', $dbRef);
        $this->assertArrayHasKey('$db', $dbRef);

        $dbRef = $this->dm->createReference($r, $class->associationMappings['ref4']);
        $this->assertCount(1, $dbRef);
        $this->assertArrayHasKey('id', $dbRef);
    }
}

/** @ODM\Document */
class WrongSimpleRefDocument
{
    /** @ODM\Id */
    public $id;

    /** @ODM\ReferenceOne(targetDocument="Documents\Tournament\Participant", storeAs="id") */
    public $ref;
}

/** @ODM\Document */
class ReferenceStoreAsDocument
{
    /** @ODM\Id */
    public $id;

    /** @ODM\ReferenceOne(targetDocument="Documents\User", storeAs="id") */
    public $ref1;

    /** @ODM\ReferenceOne(targetDocument="Documents\User", storeAs="dbRef") */
    public $ref2;

    /** @ODM\ReferenceOne(targetDocument="Documents\User", storeAs="dbRefWithDb") */
    public $ref3;

    /** @ODM\ReferenceOne(targetDocument="Documents\User", storeAs="ref") */
    public $ref4;
}
