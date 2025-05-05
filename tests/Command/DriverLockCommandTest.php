<?php

namespace Tests\Lexik\Bundle\MaintenanceBundle\Command;

use Lexik\Bundle\MaintenanceBundle\src\Command\DriverLockCommand;
use Lexik\Bundle\MaintenanceBundle\src\Drivers\AbstractDriver;
use Lexik\Bundle\MaintenanceBundle\src\Drivers\DriverFactory;
use Lexik\Bundle\MaintenanceBundle\src\Drivers\DriverTtlInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class DriverLockCommandTest extends TestCase
{
    private KernelInterface $kernel;
    private Application $application;

    protected function setUp(): void
    {
        $this->kernel = $this->createMock(KernelInterface::class);
        $this->application = new Application($this->kernel);
        $this->application->setAutoExit(false);
    }

    public function testNonInteractiveWithExplicitTtlAndTtlDriver(): void
    {
        // Arrange
        $driver = $this->getMockBuilder(AbstractDriver::class)
            ->addInterface(DriverTtlInterface::class)
            ->onlyMethods(['lock','getMessageLock','getTtl','setTtl','hasTtl'])
            ->getMockForAbstractClass();
        $driver->method('hasTtl')->willReturn(true);
        $driver->method('getTtl')->willReturn(3600);
        $driver->expects(self::once())
               ->method('setTtl')
               ->with(7200);
        $driver->method('lock')->willReturn('lockId');
        $driver->method('getMessageLock')
               ->with('lockId')
               ->willReturn('Locked with TTL!');

        $factory = $this->createMock(DriverFactory::class);
        $factory->method('getDriver')->willReturn($driver);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')
                  ->with('lexik_maintenance.driver.factory')
                  ->willReturn($factory);

        $command = new DriverLockCommand();
        $command->setContainer($container);
        $this->application->add($command);

        // Act
        $input = new ArrayInput([
            'command' => 'lexik:maintenance:lock',
            'ttl'     => '7200',
        ]);
        $input->setInteractive(false);

        $output = new BufferedOutput();
        $exitCode = $this->application->run($input, $output);

        // Assert
        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Locked with TTL!', $output->fetch());
    }

    public function testNonInteractiveWithDefaultTtlAndTtlDriver(): void
    {
        // Arrange
        $driver = $this->getMockBuilder(AbstractDriver::class)
            ->addInterface(DriverTtlInterface::class)
            ->onlyMethods(['lock','getMessageLock','getTtl','setTtl','hasTtl'])
            ->getMockForAbstractClass();
        $driver->method('hasTtl')->willReturn(true);
        $driver->method('getTtl')->willReturn(500);
        $driver->expects(self::once())
               ->method('setTtl')
               ->with(500);
        $driver->method('lock')->willReturn('lockDefault');
        $driver->method('getMessageLock')
               ->with('lockDefault')
               ->willReturn('Locked with default TTL!');

        $factory = $this->createMock(DriverFactory::class);
        $factory->method('getDriver')->willReturn($driver);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')
                  ->with('lexik_maintenance.driver.factory')
                  ->willReturn($factory);

        $command = new DriverLockCommand();
        $command->setContainer($container);
        $this->application->add($command);

        // Act
        $input = new ArrayInput([
            'command' => 'lexik:maintenance:lock',
            // no ttl argument
        ]);
        $input->setInteractive(false);

        $output = new BufferedOutput();
        $exitCode = $this->application->run($input, $output);

        // Assert
        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Locked with default TTL!', $output->fetch());
    }

    public function testNonInteractiveWithNonTtlDriver(): void
    {
        // Arrange
        $driver = $this->getMockBuilder(AbstractDriver::class)
            ->onlyMethods(['lock','getMessageLock'])
            ->getMockForAbstractClass();
        $driver->expects(self::never())
               ->method('setTtl');
        $driver->method('lock')->willReturn('lockNoTtl');
        $driver->method('getMessageLock')
               ->with('lockNoTtl')
               ->willReturn('Locked without TTL!');

        $factory = $this->createMock(DriverFactory::class);
        $factory->method('getDriver')->willReturn($driver);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')
                  ->with('lexik_maintenance.driver.factory')
                  ->willReturn($factory);

        $command = new DriverLockCommand();
        $command->setContainer($container);
        $this->application->add($command);

        // Act
        $input = new ArrayInput([
            'command' => 'lexik:maintenance:lock',
            'ttl'     => '300',
        ]);
        $input->setInteractive(false);

        $output = new BufferedOutput();
        $exitCode = $this->application->run($input, $output);

        // Assert
        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Locked without TTL!', $output->fetch());
    }

    public function testInteractiveCancelledByUser(): void
    {
        // Arrange driver and factory
        $driver = $this->getMockBuilder(AbstractDriver::class)
            ->onlyMethods(['lock','getMessageLock'])
            ->getMockForAbstractClass();
        $factory = $this->createMock(DriverFactory::class);
        $factory->method('getDriver')->willReturn($driver);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')
                  ->with('lexik_maintenance.driver.factory')
                  ->willReturn($factory);

        // Partial mock command to override confirmation
        $command = $this->getMockBuilder(DriverLockCommand::class)
                        ->onlyMethods(['askConfirmation'])
                        ->getMock();
        $command->method('askConfirmation')->willReturn(false);
        $command->setContainer($container);
        $this->application->add($command);

        // Act
        $input = new ArrayInput([
            'command' => 'lexik:maintenance:lock',
        ]);
        $input->setInteractive(true);

        $output = new BufferedOutput();
        $exitCode = $this->application->run($input, $output);

        // Assert
        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('Maintenance cancelled!', $output->fetch());
    }

    public function testInteractThrowsExceptionOnInvalidTtlArgument(): void
    {
        // Arrange
        $driver = $this->getMockBuilder(AbstractDriver::class)
            ->onlyMethods(['lock','getMessageLock','getOptions'])
            ->getMockForAbstractClass();
        $driver->method('getOptions')->willReturn([]);

        $factory = $this->createMock(DriverFactory::class);
        $factory->method('getDriver')->willReturn($driver);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')
                  ->with('lexik_maintenance.driver.factory')
                  ->willReturn($factory);

        $command = new DriverLockCommand();
        $command->setContainer($container);
        $this->application->add($command);

        // Expect exception from interact()
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Time must be an integer');

        // Act
        $input = new ArrayInput([
            'command' => 'lexik:maintenance:lock',
            'ttl'     => 'notanint',
        ]);
        $input->setInteractive(true);

        $output = new BufferedOutput();
        $this->application->run($input, $output);
    }
}