<?php declare(strict_types = 1);

/*
 * This file is part of the Valksor package.
 *
 * (c) Davis Zalitis (k0d3r1s)
 * (c) SIA Valksor <packages@valksor.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ValksorPlugin\Tests\Unit;

use Composer\Composer;
use Composer\EventDispatcher\EventDispatcher;
use Composer\IO\IOInterface;
use Composer\Plugin\Capability\CommandProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use ValksorPlugin\Command\ValksorRecipesInstallCommand;
use ValksorPlugin\Command\ValksorRecipesUninstallCommand;
use ValksorPlugin\RecipeHandler;
use ValksorPlugin\Tests\Mocks\ComposerMockTrait;
use ValksorPlugin\ValksorFlex;

/**
 * Unit tests for ValksorFlex class.
 *
 * Tests the main plugin functionality including activation,
 * event handling, command providing, and integration with Composer.
 */
#[CoversClass(ValksorFlex::class)]
#[CoversClass(RecipeHandler::class)]
#[CoversClass(ValksorRecipesInstallCommand::class)]
#[CoversClass(ValksorRecipesUninstallCommand::class)]
class ValksorFlexTest extends TestCase
{
    use ComposerMockTrait;

    private Composer $composer;
    private IOInterface $io;
    private ValksorFlex $plugin;

    public function testActivateRegistersEventSubscriber(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher->expects($this->once())
            ->method('addSubscriber')
            ->with($this->plugin);

        $composer = $this->createMock(Composer::class);
        $composer->expects($this->once())
            ->method('getEventDispatcher')
            ->willReturn($eventDispatcher);

        $this->plugin->activate($composer, $this->io);
    }

    public function testDeactivateDoesNothing(): void
    {
        // Deactivate should not throw any exceptions
        $this->expectNotToPerformAssertions();
        $this->plugin->deactivate($this->composer, $this->io);
    }

    /**
     * @throws ReflectionException
     */
    public function testDuplicatePackageHandling(): void
    {
        $package = $this->createPackage('test/package', 'dev-master');

        // Create events with the same package to simulate duplicate handling
        $event1 = $this->createPackageInstallEvent($package, $this->composer, $this->io);
        $event2 = $this->createPackageInstallEvent($package, $this->composer, $this->io);

        // Mock the handler to verify it's only called once for duplicates
        $handler = $this->createMock(RecipeHandler::class);
        $handler->expects($this->once()) // Should only be called once for duplicates
            ->method('processPackage')
            ->with($package, 'install')
            ->willReturn(null);

        // Set the handler using reflection
        $handlerProperty = new ReflectionClass($this->plugin)->getProperty('handler');
        $handlerProperty->setValue($this->plugin, $handler);

        // Process both events and capture results
        $this->plugin->onPostPackageInstall($event1);
        $this->plugin->onPostPackageInstall($event2);
    }

    public function testGetCapabilitiesReturnsCommandProvider(): void
    {
        $capabilities = $this->plugin->getCapabilities();

        $this->assertIsArray($capabilities);
        $this->assertArrayHasKey(CommandProvider::class, $capabilities);
        $this->assertSame(ValksorFlex::class, $capabilities[CommandProvider::class]);
    }

    public function testGetCommandsReturnsBothCommands(): void
    {
        $commands = $this->plugin->getCommands();

        $this->assertIsArray($commands);
        $this->assertCount(2, $commands);

        $this->assertInstanceOf(ValksorRecipesInstallCommand::class, $commands[0]);
        $this->assertInstanceOf(ValksorRecipesUninstallCommand::class, $commands[1]);
    }

    /**
     * @throws ReflectionException
     */
    public function testGetHandlerCreatesHandlerLazily(): void
    {
        // First call should create the handler
        $getHandlerMethod = new ReflectionClass($this->plugin)->getMethod('getHandler');

        $handler1 = $getHandlerMethod->invoke($this->plugin, $this->composer, $this->io);
        $handler2 = $getHandlerMethod->invoke($this->plugin, $this->composer, $this->io);

        $this->assertSame($handler1, $handler2, 'Handler should be created once and reused');
        $this->assertInstanceOf(RecipeHandler::class, $handler1);
    }

    public function testGetSubscribedEventsReturnsCorrectEvents(): void
    {
        $events = ValksorFlex::getSubscribedEvents();

        $this->assertIsArray($events);
        $this->assertCount(3, $events);

        $this->assertArrayHasKey('post-package-install', $events);
        $this->assertArrayHasKey('post-package-update', $events);
        $this->assertArrayHasKey('pre-package-uninstall', $events);

        $this->assertSame('onPostPackageInstall', $events['post-package-install']);
        $this->assertSame('onPostPackageUpdate', $events['post-package-update']);
        $this->assertSame('onPrePackageUninstall', $events['pre-package-uninstall']);
    }

    public function testOnPostPackageInstallProcessesAllowedPackage(): void
    {
        $package = $this->createPackage();
        $event = $this->createPackageInstallEvent($package, $this->composer, $this->io);

        // Test that the method executes without throwing exceptions
        // The actual RecipeHandler will process the package
        $this->expectNotToPerformAssertions();
        $this->plugin->onPostPackageInstall($event);
    }

    /**
     * @throws ReflectionException
     */
    public function testOnPostPackageInstallSkipsDuplicatePackage(): void
    {
        $package = $this->createPackage();
        $event1 = $this->createPackageInstallEvent($package, $this->composer, $this->io);
        $event2 = $this->createPackageInstallEvent($package, $this->composer, $this->io);

        // Mock the handler to verify processPackage is only called once for duplicates
        $handler = $this->createMock(RecipeHandler::class);
        $handler->expects($this->once()) // Should only be called once, skip duplicate
            ->method('processPackage')
            ->with($package, 'install')
            ->willReturn(null);

        // Create plugin with private handler mock using reflection
        $handlerProperty = new ReflectionClass($this->plugin)->getProperty('handler');
        $handlerProperty->setValue($this->plugin, $handler);

        // Process both events - PHPUnit will verify processPackage is only called once
        $this->plugin->onPostPackageInstall($event1);
        $this->plugin->onPostPackageInstall($event2);
    }

    /**
     * @throws ReflectionException
     */
    public function testOnPostPackageUpdateProcessesUpdatedPackage(): void
    {
        $initialPackage = $this->createPackage();
        $targetPackage = $this->createPackage(version: '2.0.0');
        $event = $this->createPackageUpdateEvent($initialPackage, $targetPackage, $this->composer, $this->io);

        // Mock the handler to verify it processes the target package with 'update' operation
        $handler = $this->createMock(RecipeHandler::class);
        $handler->expects($this->once())
            ->method('processPackage')
            ->with($targetPackage, 'update')
            ->willReturn(null);

        // Create plugin with private handler mock using reflection
        $handlerProperty = new ReflectionClass($this->plugin)->getProperty('handler');
        $handlerProperty->setValue($this->plugin, $handler);

        $this->plugin->onPostPackageUpdate($event);
    }

    /**
     * Test that duplicate uninstall calls for the same package are prevented.
     *
     * @throws ReflectionException
     */
    public function testOnPrePackageUninstallPreventsDuplicates(): void
    {
        $package = $this->createPackage();
        $event = $this->createPackageUninstallEvent($package, $this->composer, $this->io);

        // Mock the handler to verify uninstallPackage is called only ONCE
        $handler = $this->createMock(RecipeHandler::class);
        $handler->expects($this->once())  // Should be called only once despite two event triggers
            ->method('uninstallPackage')
            ->with($package)
            ->willReturn(null);

        // Create plugin with private handler mock using reflection
        $handlerProperty = new ReflectionClass($this->plugin)->getProperty('handler');
        $handlerProperty->setValue($this->plugin, $handler);

        // Call uninstall twice for the same package
        $this->plugin->onPrePackageUninstall($event);
        $this->plugin->onPrePackageUninstall($event);  // This should be skipped due to duplicate prevention

        // If handler->uninstallPackage is called twice, the test will fail
        // due to the ->once() expectation
    }

    /**
     * @throws ReflectionException
     */
    public function testOnPrePackageUninstallRemovesPackage(): void
    {
        $package = $this->createPackage();
        $event = $this->createPackageUninstallEvent($package, $this->composer, $this->io);

        // Mock the handler to verify uninstallPackage is called with the correct package
        $handler = $this->createMock(RecipeHandler::class);
        $handler->expects($this->once())
            ->method('uninstallPackage')
            ->with($package)
            ->willReturn(null);

        // Create plugin with private handler mock using reflection
        $handlerProperty = new ReflectionClass($this->plugin)->getProperty('handler');
        $handlerProperty->setValue($this->plugin, $handler);

        $this->plugin->onPrePackageUninstall($event);
    }

    public function testUninstallDoesNothing(): void
    {
        // Uninstall should not throw any exceptions
        $this->expectNotToPerformAssertions();
        $this->plugin->uninstall($this->composer, $this->io);
    }

    protected function setUp(): void
    {
        $this->plugin = new ValksorFlex();
        $this->composer = $this->createComposer();
        $this->io = $this->createIO();
    }
}
