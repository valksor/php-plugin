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

namespace ValksorPlugin;

use Composer\Command\BaseCommand;
use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Plugin\Capability\CommandProvider;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;
use ValksorPlugin\Command\ValksorRecipesInstallCommand;
use ValksorPlugin\Command\ValksorRecipesUninstallCommand;

/**
 * Main Composer plugin class for Valksor recipe processing.
 *
 * This plugin automatically discovers and applies local recipes from package
 * directories when packages are installed, updated, or uninstalled. It provides
 * a recipe system similar to Symfony Flex but focuses on local recipe discovery
 * within package directories.
 */
class ValksorFlex implements PluginInterface, EventSubscriberInterface, CommandProvider, Capable
{
    /** @var RecipeHandler|null Recipe handler instance */
    private ?RecipeHandler $handler = null;

    /** @var array<string, bool> Track processed packages to avoid duplicates */
    private array $processedPackages = [];

    /**
     * Activate the plugin and register event listeners.
     *
     * This method is called by Composer when the plugin is activated.
     * It registers the plugin as an event subscriber to handle package
     * install/update/uninstall events for automatic recipe processing.
     *
     * @param Composer    $composer The Composer instance
     * @param IOInterface $io       The I/O interface for user interaction
     */
    public function activate(
        Composer $composer,
        IOInterface $io,
    ): void {
        // 1. Register this plugin as an event subscriber to handle package installs/updates.
        $composer->getEventDispatcher()->addSubscriber($this);
    }

    /**
     * Deactivate the plugin.
     *
     * This method is called by Composer when the plugin is deactivated.
     * Currently no cleanup is needed.
     *
     * @param Composer    $composer The Composer instance
     * @param IOInterface $io       The I/O interface for user interaction
     */
    public function deactivate(
        Composer $composer,
        IOInterface $io,
    ): void {
    }

    /**
     * Get plugin capabilities.
     *
     * Declares that this plugin can provide Composer commands.
     *
     * @return array<string, class-string> Array of capability mappings
     */
    public function getCapabilities(): array
    {
        return [
            CommandProvider::class => self::class,
        ];
    }

    /**
     * Get the commands provided by this plugin.
     *
     * Returns the list of Composer commands that this plugin provides.
     *
     * @return array<BaseCommand> Array of command instances
     */
    public function getCommands(): array
    {
        return [
            new ValksorRecipesInstallCommand(),
            new ValksorRecipesUninstallCommand(),
        ];
    }

    /**
     * Handle post-package-install event.
     *
     * Automatically discovers and applies local recipes for newly installed packages.
     * Tracks processed packages to avoid handling duplicates (e.g., dev-master/9999999-dev aliases).
     *
     * @param PackageEvent $event The Composer package event
     */
    public function onPostPackageInstall(
        PackageEvent $event,
    ): void {
        /** @var InstallOperation $operation */
        $operation = $event->getOperation();

        $package = $operation->getPackage();
        $this->onPostPackage($package, $event, 'install');
    }

    /**
     * Handle post-package-update event.
     *
     * Re-applies local recipes for updated packages to ensure configuration
     * stays in sync with the new package version.
     *
     * @param PackageEvent $event The Composer package event
     */
    public function onPostPackageUpdate(
        PackageEvent $event,
    ): void {
        /** @var UpdateOperation $operation */
        $operation = $event->getOperation();

        $package = $operation->getTargetPackage();

        $this->onPostPackage($package, $event, 'update');
    }

    /**
     * Handle pre-package-uninstall event.
     *
     * Removes recipes for packages that are about to be uninstalled.
     * This ensures proper cleanup of configuration files and settings.
     *
     * @param PackageEvent $event The Composer package event
     */
    public function onPrePackageUninstall(
        PackageEvent $event,
    ): void {
        /** @var UninstallOperation $operation */
        $operation = $event->getOperation();

        $package = $operation->getPackage();
        $packageName = $package->getName();

        // Skip if already processed (handles Composer package alias duplicates)
        //
        // This prevents duplicate processing when Composer creates multiple
        // package instances for the same package with different versions:
        // - dev-master and 9999999-dev (common for git packages)
        // - 1.0.x-dev and 1.0.0-dev (branch aliases)
        // - Other version alias scenarios
        //
        // Without this check, the same recipe would be uninstalled multiple times
        // for the same logical package, leading to errors and inconsistent state.
        if (isset($this->processedPackages[$packageName])) {
            return;
        }

        $this->processedPackages[$packageName] = true;
        $this->getHandler($event->getComposer(), $event->getIO())
            ->uninstallPackage($package);
    }

    /**
     * Uninstall the plugin.
     *
     * This method is called by Composer when the plugin is uninstalled.
     * Currently no cleanup is needed.
     *
     * @param Composer    $composer The Composer instance
     * @param IOInterface $io       The I/O interface for user interaction
     */
    public function uninstall(
        Composer $composer,
        IOInterface $io,
    ): void {
    }

    /**
     * Get the list of Composer events this plugin subscribes to.
     *
     * Defines which package lifecycle events this plugin handles.
     *
     * @return array<string, string> Array mapping event names to handler methods
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'post-package-install' => 'onPostPackageInstall',
            'post-package-update' => 'onPostPackageUpdate',
            'pre-package-uninstall' => 'onPrePackageUninstall',
        ];
    }

    /**
     * Get or create the RecipeHandler instance.
     *
     * Uses lazy initialization to create the handler only when needed.
     *
     * @param Composer    $composer The Composer instance
     * @param IOInterface $io       The I/O interface for user interaction
     *
     * @return RecipeHandler The recipe handler instance
     */
    private function getHandler(
        Composer $composer,
        IOInterface $io,
    ): RecipeHandler {
        if (null === $this->handler) {
            $this->handler = new RecipeHandler($composer, $io);
        }

        return $this->handler;
    }

    private function onPostPackage(
        PackageInterface $package,
        PackageEvent $event,
        string $type,
    ): void {
        $packageName = $package->getName();

        // Skip if already processed (handles dev-master/9999999-dev alias duplicates)
        if (isset($this->processedPackages[$packageName])) {
            return;
        }

        $this->processedPackages[$packageName] = true;
        $this->getHandler($event->getComposer(), $event->getIO())
            ->processPackage($package, $type);
    }
}
