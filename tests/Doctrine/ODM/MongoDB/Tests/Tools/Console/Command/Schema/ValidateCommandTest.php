<?php

declare(strict_types=1);

namespace Doctrine\ODM\MongoDB\Tests\Tools\Console\Command\Schema;

use Doctrine\ODM\MongoDB\Tests\Tools\Console\Command\AbstractCommandTestCase;
use Doctrine\ODM\MongoDB\Tools\Console\Command\Schema\ValidateCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class ValidateCommandTest extends AbstractCommandTestCase
{
    protected ?Command $command;

    protected ?CommandTester $commandTester;

    public function setUp(): void
    {
        parent::setUp();

        $this->application->addCommands(
            [
                new ValidateCommand(),
            ],
        );
        $command       = $this->application->find('odm:schema:validate');
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

    public function testItReportsNoErrorsForValidMapping(): void
    {
        $this->commandTester->execute([]);
        $output = $this->commandTester->getDisplay();

        self::assertStringContainsString('[OK] All documents are mapped correctly.', $output);
    }
}
