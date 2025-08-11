<?php

declare(strict_types=1);

namespace Doctrine\ODM\MongoDB\Tests\Tools\Console\Command\Schema;

use Doctrine\ODM\MongoDB\Tests\Tools\Console\Command\AbstractCommandTestCase;
use Doctrine\ODM\MongoDB\Tools\Console\Command\Schema\CreateCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Documents\SchemaValidated;

class CreateCommandTest extends AbstractCommandTestCase
{
    protected ?Command $command;

    protected ?CommandTester $commandTester;

    public function setUp(): void
    {
        parent::setUp();

        $this->application->addCommands(
            [
                new CreateCommand(),
            ],
        );
        $command       = $this->application->find('odm:schema:create');
        $commandTester = new CommandTester($command);

        $this->command       = $command;
        $this->commandTester = $commandTester;
    }

    public function tearDown(): void
    {
        parent::tearDown();

        $this->command       = null;
        $this->commandTester = null;
    }

    public function testItCreatesAllMappedCollections(): void
    {
        $this->commandTester->execute([]);
        $output = $this->commandTester->getDisplay();

        self::assertStringContainsString('Created collections for all classes', $output);
    }

    public function testItCreatesACollectionForASingleClass(): void
    {
        $this->commandTester->execute(['--class' => SchemaValidated::class]);
        $output = $this->commandTester->getDisplay();

        self::assertStringContainsString('Created collection for Documents\SchemaValidated', $output);
    }
}
