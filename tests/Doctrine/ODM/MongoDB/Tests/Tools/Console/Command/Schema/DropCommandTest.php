<?php

declare(strict_types=1);

namespace Doctrine\ODM\MongoDB\Tests\Tools\Console\Command\Schema;

use Doctrine\ODM\MongoDB\Tests\Tools\Console\Command\AbstractCommandTestCase;
use Doctrine\ODM\MongoDB\Tools\Console\Command\Schema\DropCommand;
use Documents\SchemaValidated;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class DropCommandTest extends AbstractCommandTestCase
{
    protected ?Command $command;

    protected ?CommandTester $commandTester;

    public function setUp(): void
    {
        parent::setUp();

        $this->application->addCommands(
            [
                new DropCommand(),
            ],
        );
        $command       = $this->application->find('odm:schema:drop');
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

    public function testItDropsAllMappedCollections(): void
    {
        $this->commandTester->execute([]);
        $output = $this->commandTester->getDisplay();

        self::assertStringContainsString('Dropped collections for all classes', $output);
        self::assertStringContainsString('Dropped indexes for all classes', $output);
    }

    public function testItDropsACollectionForASingleClass(): void
    {
        $this->commandTester->execute(['--class' => SchemaValidated::class]);
        $output = $this->commandTester->getDisplay();

        self::assertStringContainsString('Dropped collection for Documents\SchemaValidated', $output);
    }
}
