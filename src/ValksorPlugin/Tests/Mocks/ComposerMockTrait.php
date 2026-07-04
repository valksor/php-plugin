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

namespace ValksorPlugin\Tests\Mocks;

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\EventDispatcher\EventDispatcher;
use Composer\Installer\InstallationManager;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Package\CompletePackage;
use Composer\Package\Locker;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackageInterface;
use Composer\Repository\LockArrayRepository;
use Composer\Repository\RepositoryInterface;

/**
 * Trait for creating Composer-related test doubles.
 *
 * This trait provides standardized stubs for Composer components
 * used throughout the test suite.
 */
trait ComposerMockTrait
{
    /**
     * Create a mock complete package.
     */
    public function createCompletePackage(
        string $name = 'test/package',
        string $version = '1.0.0',
    ): CompletePackage {
        $package = new CompletePackage($name, $version, $version);
        $package->setType('library');

        return $package;
    }

    /**
     * Create a stub Composer instance.
     *
     * @param array<string, mixed> $extra Extra configuration for root package
     */
    public function createComposer(
        array $extra = [],
    ): Composer {
        $composer = $this->createStub(Composer::class);
        $rootPackage = $this->createRootPackage($extra);
        $eventDispatcher = $this->createEventDispatcher();

        $composer->method('getPackage')->willReturn($rootPackage);
        $composer->method('getEventDispatcher')->willReturn($eventDispatcher);

        return $composer;
    }

    /**
     * Create a stub event dispatcher.
     */
    public function createEventDispatcher(): EventDispatcher
    {
        return $this->createStub(EventDispatcher::class);
    }

    /**
     * Create a stub IO interface.
     *
     * @param bool $verbose Whether IO should be verbose
     */
    public function createIO(
        bool $verbose = false,
    ): IOInterface {
        $io = $this->createStub(IOInterface::class);
        $io->method('isVerbose')->willReturn($verbose);
        $io->method('isDebug')->willReturn($verbose);

        return $io;
    }

    /**
     * Create a stub installation manager.
     *
     * @param string|null $installPath The install path to return
     */
    public function createInstallationManager(
        ?string $installPath = null,
    ): InstallationManager {
        $installationManager = $this->createStub(InstallationManager::class);
        $installationManager->method('getInstallPath')->willReturn($installPath);

        return $installationManager;
    }

    /**
     * Create a stub locker.
     *
     * @param bool                            $isLocked Whether the locker is locked
     * @param array<string, PackageInterface> $packages Array of locked packages
     */
    public function createLocker(
        bool $isLocked = true,
        array $packages = [],
    ): Locker {
        $locker = $this->createStub(Locker::class);
        $locker->method('isLocked')->willReturn($isLocked);

        $repository = $this->createStub(LockArrayRepository::class);
        $repository->method('getPackages')->willReturn($packages);

        $locker->method('getLockedRepository')->willReturn($repository);

        return $locker;
    }

    /**
     * Create a stub package.
     *
     * @param string $name    Package name
     * @param string $version Package version
     * @param string $type    Package type (library, composer-plugin, etc.)
     */
    public function createPackage(
        string $name = 'test/package',
        string $version = '1.0.0',
        string $type = 'library',
    ): PackageInterface {
        $package = $this->createStub(PackageInterface::class);
        $package->method('getName')->willReturn($name);
        $package->method('getPrettyVersion')->willReturn($version);
        $package->method('getType')->willReturn($type);
        $package->method('getExtra')->willReturn([]);

        return $package;
    }

    /**
     * Create a package event for installation.
     *
     * @param PackageInterface $package  The package being installed
     * @param Composer         $composer The composer instance
     * @param IOInterface      $io       The IO interface
     */
    public function createPackageInstallEvent(
        PackageInterface $package,
        Composer $composer,
        IOInterface $io,
    ): PackageEvent {
        $operation = new InstallOperation($package);
        $localRepo = $this->createStub(RepositoryInterface::class);

        return new PackageEvent(PackageEvents::POST_PACKAGE_INSTALL, $composer, $io, false, $localRepo, [], $operation);
    }

    /**
     * Create a package event for uninstallation.
     *
     * @param PackageInterface $package  The package being uninstalled
     * @param Composer         $composer The composer instance
     * @param IOInterface      $io       The IO interface
     */
    public function createPackageUninstallEvent(
        PackageInterface $package,
        Composer $composer,
        IOInterface $io,
    ): PackageEvent {
        $operation = new UninstallOperation($package);
        $localRepo = $this->createStub(RepositoryInterface::class);

        return new PackageEvent(PackageEvents::PRE_PACKAGE_UNINSTALL, $composer, $io, false, $localRepo, [], $operation);
    }

    /**
     * Create a package event for update.
     *
     * @param PackageInterface $initialPackage The initial package
     * @param PackageInterface $targetPackage  The target package
     * @param Composer         $composer       The composer instance
     * @param IOInterface      $io             The IO interface
     */
    public function createPackageUpdateEvent(
        PackageInterface $initialPackage,
        PackageInterface $targetPackage,
        Composer $composer,
        IOInterface $io,
    ): PackageEvent {
        $operation = new UpdateOperation($initialPackage, $targetPackage);
        $localRepo = $this->createStub(RepositoryInterface::class);

        return new PackageEvent(PackageEvents::POST_PACKAGE_UPDATE, $composer, $io, false, $localRepo, [], $operation);
    }

    /**
     * Create a stub root package.
     *
     * @param array<string, mixed> $extra Extra configuration
     */
    public function createRootPackage(
        array $extra = [],
    ): RootPackageInterface {
        $rootPackage = $this->createStub(RootPackageInterface::class);
        $rootPackage->method('getExtra')->willReturn($extra);

        return $rootPackage;
    }
}
