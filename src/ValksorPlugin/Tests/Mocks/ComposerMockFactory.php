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
use Mockery;

/**
 * Factory for creating Composer-related mocks.
 *
 * This factory provides standardized mocks for Composer components
 * used throughout the test suite.
 */
class ComposerMockFactory
{
    /**
     * Create a mock complete package.
     */
    public static function createCompletePackage(
        string $name = 'test/package',
        string $version = '1.0.0',
    ): CompletePackage {
        $package = new CompletePackage($name, $version, $version);
        $package->setType('library');

        return $package;
    }

    /**
     * Create a mock Composer instance.
     *
     * @param array<string, mixed> $extra Extra configuration for root package
     */
    public static function createComposer(
        array $extra = [],
    ): Mockery\MockInterface {
        $composer = Mockery::mock(Composer::class);
        $rootPackage = self::createRootPackage($extra);
        $eventDispatcher = self::createEventDispatcher();
        $installationManager = self::createInstallationManager();

        $composer->shouldReceive('getPackage')->andReturn($rootPackage);
        $composer->shouldReceive('getEventDispatcher')->andReturn($eventDispatcher);
        $composer->shouldReceive('getInstallationManager')->andReturn($installationManager);

        return $composer;
    }

    /**
     * Create a mock event dispatcher.
     */
    public static function createEventDispatcher(): Mockery\MockInterface
    {
        $eventDispatcher = Mockery::mock(EventDispatcher::class);
        $eventDispatcher->shouldReceive('addSubscriber')->andReturn(null);

        return $eventDispatcher;
    }

    /**
     * Create a mock IO interface.
     *
     * @param bool $verbose Whether IO should be verbose
     */
    public static function createIO(
        bool $verbose = false,
    ): Mockery\MockInterface {
        $io = Mockery::mock(IOInterface::class);
        $io->shouldReceive('writeError')->andReturn(null);
        $io->shouldReceive('write')->andReturn(null);
        $io->shouldReceive('isVerbose')->andReturn($verbose);
        $io->shouldReceive('isDebug')->andReturn($verbose);

        return $io;
    }

    /**
     * Create a mock installation manager.
     *
     * @param string|null $installPath The install path to return
     */
    public static function createInstallationManager(
        ?string $installPath = null,
    ): Mockery\MockInterface {
        $installationManager = Mockery::mock(InstallationManager::class);
        $installationManager->shouldReceive('getInstallPath')->andReturn($installPath);

        return $installationManager;
    }

    /**
     * Create a mock locker.
     *
     * @param bool                            $isLocked Whether the locker is locked
     * @param array<string, PackageInterface> $packages Array of locked packages
     */
    public static function createLocker(
        bool $isLocked = true,
        array $packages = [],
    ): Mockery\MockInterface {
        $locker = Mockery::mock(Locker::class);
        $locker->shouldReceive('isLocked')->andReturn($isLocked);

        $repository = Mockery::mock(LockArrayRepository::class);
        $repository->shouldReceive('getPackages')->andReturn($packages);

        $locker->shouldReceive('getLockedRepository')->andReturn($repository);

        return $locker;
    }

    /**
     * Create a mock package.
     *
     * @param string $name    Package name
     * @param string $version Package version
     * @param string $type    Package type (library, composer-plugin, etc.)
     */
    public static function createPackage(
        string $name = 'test/package',
        string $version = '1.0.0',
        string $type = 'library',
    ): Mockery\MockInterface {
        $package = Mockery::mock(PackageInterface::class);
        $package->shouldReceive('getName')->andReturn($name);
        $package->shouldReceive('getPrettyVersion')->andReturn($version);
        $package->shouldReceive('getType')->andReturn($type);
        $package->shouldReceive('getExtra')->andReturn([]);

        return $package;
    }

    /**
     * Create a mock package event for installation.
     *
     * @param PackageInterface $package  The package being installed
     * @param Composer         $composer The composer instance
     * @param IOInterface      $io       The IO interface
     */
    public static function createPackageInstallEvent(
        PackageInterface $package,
        Composer $composer,
        IOInterface $io,
    ): PackageEvent {
        $operation = new InstallOperation($package);
        $localRepo = Mockery::mock(RepositoryInterface::class);

        return new PackageEvent(PackageEvents::POST_PACKAGE_INSTALL, $composer, $io, false, $localRepo, [], $operation);
    }

    /**
     * Create a mock package event for uninstallation.
     *
     * @param PackageInterface $package  The package being uninstalled
     * @param Composer         $composer The composer instance
     * @param IOInterface      $io       The IO interface
     */
    public static function createPackageUninstallEvent(
        PackageInterface $package,
        Composer $composer,
        IOInterface $io,
    ): PackageEvent {
        $operation = new UninstallOperation($package);
        $localRepo = Mockery::mock(RepositoryInterface::class);

        return new PackageEvent(PackageEvents::PRE_PACKAGE_UNINSTALL, $composer, $io, false, $localRepo, [], $operation);
    }

    /**
     * Create a mock package event for update.
     *
     * @param PackageInterface $initialPackage The initial package
     * @param PackageInterface $targetPackage  The target package
     * @param Composer         $composer       The composer instance
     * @param IOInterface      $io             The IO interface
     */
    public static function createPackageUpdateEvent(
        PackageInterface $initialPackage,
        PackageInterface $targetPackage,
        Composer $composer,
        IOInterface $io,
    ): PackageEvent {
        $operation = new UpdateOperation($initialPackage, $targetPackage);
        $localRepo = Mockery::mock(RepositoryInterface::class);

        return new PackageEvent(PackageEvents::POST_PACKAGE_UPDATE, $composer, $io, false, $localRepo, [], $operation);
    }

    /**
     * Create a mock root package.
     *
     * @param array<string, mixed> $extra Extra configuration
     */
    public static function createRootPackage(
        array $extra = [],
    ): Mockery\MockInterface {
        $rootPackage = Mockery::mock(RootPackageInterface::class);
        $rootPackage->shouldReceive('getExtra')->andReturn($extra);

        return $rootPackage;
    }
}
