<?php

namespace DH\DoctrineAuditBundle\Tests\Command;

use DH\DoctrineAuditBundle\Command\CleanAuditLogsCommand;
use DH\DoctrineAuditBundle\Reader\AuditReader;
use DH\DoctrineAuditBundle\Tests\CoreTest;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @covers \DH\DoctrineAuditBundle\AuditConfiguration
 * @covers \DH\DoctrineAuditBundle\AuditManager
 * @covers \DH\DoctrineAuditBundle\Command\CleanAuditLogsCommand
 * @covers \DH\DoctrineAuditBundle\DBAL\AuditLogger
 * @covers \DH\DoctrineAuditBundle\EventSubscriber\AuditSubscriber
 * @covers \DH\DoctrineAuditBundle\EventSubscriber\CreateSchemaListener
 * @covers \DH\DoctrineAuditBundle\Helper\AuditHelper
 * @covers \DH\DoctrineAuditBundle\Helper\DoctrineHelper
 * @covers \DH\DoctrineAuditBundle\Helper\UpdateHelper
 * @covers \DH\DoctrineAuditBundle\Reader\AuditReader
 * @covers \DH\DoctrineAuditBundle\User\TokenStorageUserProvider
 * @covers \DH\DoctrineAuditBundle\User\User
 */
class CleanAuditLogsCommandTest extends CoreTest
{
    use LockableTrait;

    public function testExecuteFailsWithKeepNegative(): void
    {
        $command = $this->createCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--no-confirm' => true,
            'keep' => -1,
        ]);
        $command->unlock();

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString("'keep' argument must be a positive number.", $output);
    }

    /**
     * @depends testExecuteFailsWithKeepNegative
     */
    public function testExecuteFailsWithKeepNull(): void
    {
        $command = $this->createCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--no-confirm' => true,
            'keep' => 0,
        ]);
        $command->unlock();

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString("'keep' argument must be a positive number.", $output);
    }

    /**
     * @depends testExecuteFailsWithKeepNull
     */
    public function testExecute(): void
    {
        $command = $this->createCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--no-confirm' => true,
        ]);
        $command->unlock();

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('[OK] Success', $output);
    }

    /**
     * @depends testExecute
     */
    public function testExecuteFailsWhileLocked(): void
    {
        $this->lock('audit:clean');

        $command = $this->createCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--no-confirm' => true,
        ]);
        $command->unlock();

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('The command is already running in another process.', $output);
    }

    protected function createCommand(): Command
    {
        $this->fixturesPath = __DIR__.'/../Fixtures';

        $container = new ContainerBuilder();
        $em = $this->getEntityManager();

        $container->set('entity_manager', $em);
        $container->setAlias('doctrine.orm.default_entity_manager', 'entity_manager');

        $registry = new Registry(
            $container,
            [],
            ['default' => 'entity_manager'],
            'default',
            'default'
        );

        $container->set('doctrine', $registry);

        $reader = new AuditReader($this->getAuditConfiguration(), $em);
        $container->set('dh_doctrine_audit.reader', $reader);

        $command = new CleanAuditLogsCommand();
        $command->setContainer($container);
        $command->unlock();

        return $command;
    }
}
