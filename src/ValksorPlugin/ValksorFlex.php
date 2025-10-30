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

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\IO\IOInterface;
use Composer\Plugin\Capability\CommandProvider;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;
use JsonException;
use ValksorPlugin\Command\ValksorRecipesInstallCommand;

class ValksorFlex implements PluginInterface, EventSubscriberInterface, CommandProvider, Capable
{
    private ?RecipeHandler $handler = null;

    /**
     * This method is called by Composer when the plugin is activated.
     * It's the perfect place to register event listeners and custom commands.
     */
    public function activate(
        Composer $composer,
        IOInterface $io,
    ): void {
        // 1. Register this plugin as an event subscriber to handle package installs/updates.
        $composer->getEventDispatcher()->addSubscriber($this);
    }

    public function deactivate(
        Composer $composer,
        IOInterface $io,
    ): void {
    }

    public function getCapabilities(): array
    {
        return [
            CommandProvider::class => self::class,
        ];
    }

    public function getCommands(): array
    {
        return [new ValksorRecipesInstallCommand()];
    }

    /**
     * @throws JsonException
     */
    public function onPostPackageInstall(
        PackageEvent $event,
    ): void {
        $this->getHandler($event->getComposer(), $event->getIO())
            ->processPackage($event->getOperation()->getPackage(), 'install');
    }

    /**
     * @throws JsonException
     */
    public function onPostPackageUpdate(
        PackageEvent $event,
    ): void {
        $this->getHandler($event->getComposer(), $event->getIO())
            ->processPackage($event->getOperation()->getTargetPackage(), 'update');
    }

    public function uninstall(
        Composer $composer,
        IOInterface $io,
    ): void {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'post-package-install' => 'onPostPackageInstall',
            'post-package-update' => 'onPostPackageUpdate',
        ];
    }

    private function getHandler(
        Composer $composer,
        IOInterface $io,
    ): RecipeHandler {
        if (null === $this->handler) {
            $this->handler = new RecipeHandler($composer, $io);
        }

        return $this->handler;
    }
}
