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

namespace ValksorPlugin\Command;

use Composer\Command\BaseCommand;
use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Symfony\Flex\Recipe;
use ValksorPlugin\RecipeHandler;

use function sprintf;

/**
 * Abstract base class for Valksor recipe commands.
 *
 * Provides common functionality for lock file validation, package lookup,
 * and Composer/IO setup to reduce code duplication between commands.
 */
abstract class AbstractValksorRecipeCommand extends BaseCommand
{
    /**
     * Get the failure message for when no recipe is found.
     *
     * @param string $packageName The package name
     *
     * @return string The failure message
     */
    abstract protected function getNoRecipeMessage(
        string $packageName,
    ): string;

    /**
     * Get the not found message for when a package is not installed.
     *
     * @param string $packageName The package name
     *
     * @return string The not found message
     */
    abstract protected function getNotFoundMessage(
        string $packageName,
    ): string;

    /**
     * Get the success message for when a package is processed successfully.
     *
     * @param string $packageName The package name
     *
     * @return string The success message
     */
    abstract protected function getSuccessMessage(
        string $packageName,
    ): string;

    /**
     * Process a package using the RecipeHandler.
     *
     * This is a template method that subclasses should override to provide
     * their specific processing logic (install, uninstall, etc.).
     *
     * @param RecipeHandler    $handler The RecipeHandler instance
     * @param PackageInterface $package The package to process
     *
     * @return Recipe|null The result of the processing
     */
    abstract protected function processPackage(
        RecipeHandler $handler,
        PackageInterface $package,
    ): ?Recipe;

    /**
     * Create a RecipeHandler instance.
     *
     * @param Composer    $composer The Composer instance
     * @param IOInterface $io       The I/O interface for user interaction
     *
     * @return RecipeHandler The configured RecipeHandler
     */
    protected function createRecipeHandler(
        Composer $composer,
        IOInterface $io,
    ): RecipeHandler {
        return new RecipeHandler($composer, $io);
    }

    /**
     * Find a package by name in the locked repository.
     *
     * @param Composer $composer    The Composer instance
     * @param string   $packageName The package name to search for
     *
     * @return PackageInterface|null The found package, or null if not found
     */
    protected function findPackageByName(
        Composer $composer,
        string $packageName,
    ): ?PackageInterface {
        foreach ($composer->getLocker()->getLockedRepository()->getPackages() as $package) {
            if ($package->getName() === $packageName) {
                return $package;
            }
        }

        return null;
    }

    /**
     * Process a specific package.
     *
     * @param Composer    $composer    The Composer instance
     * @param IOInterface $io          The I/O interface
     * @param string      $packageName The package name to process
     *
     * @return int Command exit code
     */
    protected function processSpecificPackage(
        Composer $composer,
        IOInterface $io,
        string $packageName,
    ): int {
        $io->writeError(sprintf('<info>Searching for local recipe to apply for %s...</info>', $packageName));

        $package = $this->findPackageByName($composer, $packageName);

        if (!$package) {
            $io->writeError($this->getNotFoundMessage($packageName));

            return 1;
        }

        $handler = $this->createRecipeHandler($composer, $io);

        if ($this->processPackage($handler, $package)) {
            $io->writeError($this->getSuccessMessage($packageName));

            return 0;
        }

        $io->writeError($this->getNoRecipeMessage($packageName));

        return 1;
    }

    /**
     * Setup Composer and IO interfaces.
     *
     * @return array{Composer, IOInterface} Tuple of Composer and IO instances
     */
    protected function setupComposerAndIO(): array
    {
        $composer = $this->requireComposer();
        $io = $this->getIO();

        return [$composer, $io];
    }

    /**
     * Validate that composer.lock file exists.
     *
     * @param Composer    $composer The Composer instance
     * @param IOInterface $io       The I/O interface for user interaction
     *
     * @return int 0 if lock file exists, 1 otherwise
     */
    protected function validateLockFile(
        Composer $composer,
        IOInterface $io,
    ): int {
        if (!$composer->getLocker()->isLocked()) {
            $io->writeError('<error>No lock file found. Run `composer install` first.</error>');

            return 1;
        }

        return 0;
    }
}
