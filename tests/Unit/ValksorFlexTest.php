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
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\Capability\CommandProvider;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ValksorPlugin\Command\ValksorRecipesInstallCommand;
use ValksorPlugin\Command\ValksorRecipesUninstallCommand;
use ValksorPlugin\Tests\Mocks\ComposerMockFactory;
use ValksorPlugin\ValksorFlex;

/**
 * Unit tests for ValksorFlex class.
 *
 * Tests the main plugin functionality including activation,
 * event handling, command providing, and integration with Composer.
 */
#[CoversClass(ValksorFlex::class)]
class ValksorFlexTest extends TestCase
{
    private Composer $composer;
    private IOInterface $io;
    private ValksorFlex $plugin;

    public function testActivateRegistersEventSubscriber(): void
    {
        $eventDispatcher = Mockery::mock(EventDispatcher::class);
        $eventDispatcher->shouldReceive('addSubscriber')
            ->once()
            ->with($this->plugin);

        $composer = Mockery::mock(Composer::class);
        $composer->shouldReceive('getEventDispatcher')
            ->once()
            ->andReturn($eventDispatcher);

        // Assert that activate completes without throwing exceptions
        $this->assertNull($this->plugin->activate($composer, $this->io));
    }

    public function testDeactivateDoesNothing(): void
    {
        // Deactivate should not throw any exceptions
        $this->expectNotToPerformAssertions();
        $this->plugin->deactivate($this->composer, $this->io);
    }

    public function testDuplicatePackageHandling(): void
    {
        $package = ComposerMockFactory::createPackage('test/package', 'dev-master');

        // Create events with the same package to simulate duplicate handling
        $event1 = ComposerMockFactory::createPackageInstallEvent($package, $this->composer, $this->io);
        $event2 = ComposerMockFactory::createPackageInstallEvent($package, $this->composer, $this->io);

        // Mock the handler to verify it's only called once for duplicates
        $handler = Mockery::mock('ValksorPlugin\RecipeHandler');
        $handler->shouldReceive('processPackage')
            ->once() // Should only be called once for duplicates
            ->with($package, 'install')
            ->andReturn(null);

        // Set the handler using reflection
        $reflection = new ReflectionClass($this->plugin);
        $handlerProperty = $reflection->getProperty('handler');
        $handlerProperty->setValue($this->plugin, $handler);

        // Process both events and capture results
        $result1 = $this->plugin->onPostPackageInstall($event1);
        $result2 = $this->plugin->onPostPackageInstall($event2);

        // Both calls should return null (no exceptions thrown)
        // Mockery will verify processPackage was only called once
        $this->assertNull($result1);
        $this->assertNull($result2);
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

    public function testGetHandlerCreatesHandlerLazily(): void
    {
        // First call should create the handler
        $reflection = new ReflectionClass($this->plugin);
        $getHandlerMethod = $reflection->getMethod('getHandler');

        $handler1 = $getHandlerMethod->invoke($this->plugin, $this->composer, $this->io);
        $handler2 = $getHandlerMethod->invoke($this->plugin, $this->composer, $this->io);

        $this->assertSame($handler1, $handler2, 'Handler should be created once and reused');
        $this->assertInstanceOf('ValksorPlugin\RecipeHandler', $handler1);
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
        $package = ComposerMockFactory::createPackage('test/package', '1.0.0');
        $event = ComposerMockFactory::createPackageInstallEvent($package, $this->composer, $this->io);

        // Test that the method executes without throwing exceptions
        // The actual RecipeHandler will process the package
        $this->assertNull($this->plugin->onPostPackageInstall($event));
    }

    public function testOnPostPackageInstallSkipsDuplicatePackage(): void
    {
        $package = ComposerMockFactory::createPackage('test/package', '1.0.0');
        $event1 = ComposerMockFactory::createPackageInstallEvent($package, $this->composer, $this->io);
        $event2 = ComposerMockFactory::createPackageInstallEvent($package, $this->composer, $this->io);

        // Mock the handler to verify processPackage is only called once for duplicates
        $handler = Mockery::mock('ValksorPlugin\RecipeHandler');
        $handler->shouldReceive('processPackage')
            ->once() // Should only be called once, skip duplicate
            ->with($package, 'install')
            ->andReturn(null);

        // Create plugin with private handler mock using reflection
        $reflection = new ReflectionClass($this->plugin);
        $handlerProperty = $reflection->getProperty('handler');
        $handlerProperty->setValue($this->plugin, $handler);

        // Process both events - Mockery will verify processPackage is only called once
        $result1 = $this->plugin->onPostPackageInstall($event1);
        $result2 = $this->plugin->onPostPackageInstall($event2);

        // Both calls should return null (no exceptions thrown)
        $this->assertNull($result1);
        $this->assertNull($result2);
    }

    public function testOnPostPackageUpdateProcessesUpdatedPackage(): void
    {
        $initialPackage = ComposerMockFactory::createPackage('test/package', '1.0.0');
        $targetPackage = ComposerMockFactory::createPackage('test/package', '2.0.0');
        $event = ComposerMockFactory::createPackageUpdateEvent($initialPackage, $targetPackage, $this->composer, $this->io);

        // Mock the handler to verify it processes the target package with 'update' operation
        $handler = Mockery::mock('ValksorPlugin\RecipeHandler');
        $handler->shouldReceive('processPackage')
            ->once()
            ->with($targetPackage, 'update')
            ->andReturn(null);

        // Create plugin with private handler mock using reflection
        $reflection = new ReflectionClass($this->plugin);
        $handlerProperty = $reflection->getProperty('handler');
        $handlerProperty->setValue($this->plugin, $handler);

        $result = $this->plugin->onPostPackageUpdate($event);

        // Verify the method completes without exceptions and returns null
        $this->assertNull($result);
    }

    public function testOnPrePackageUninstallRemovesPackage(): void
    {
        $package = ComposerMockFactory::createPackage('test/package', '1.0.0');
        $event = ComposerMockFactory::createPackageUninstallEvent($package, $this->composer, $this->io);

        // Mock the handler to verify uninstallPackage is called with the correct package
        $handler = Mockery::mock('ValksorPlugin\RecipeHandler');
        $handler->shouldReceive('uninstallPackage')
            ->once()
            ->with($package)
            ->andReturn(null);

        // Create plugin with private handler mock using reflection
        $reflection = new ReflectionClass($this->plugin);
        $handlerProperty = $reflection->getProperty('handler');
        $handlerProperty->setValue($this->plugin, $handler);

        $result = $this->plugin->onPrePackageUninstall($event);

        // Verify the method completes without exceptions and returns null
        $this->assertNull($result);
    }

    public function testPluginImplementsRequiredInterfaces(): void
    {
        $this->assertInstanceOf(PluginInterface::class, $this->plugin);
        $this->assertInstanceOf(EventSubscriberInterface::class, $this->plugin);
        $this->assertInstanceOf(CommandProvider::class, $this->plugin);
        $this->assertInstanceOf(Capable::class, $this->plugin);
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
        $this->composer = ComposerMockFactory::createComposer();
        $this->io = ComposerMockFactory::createIO();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
