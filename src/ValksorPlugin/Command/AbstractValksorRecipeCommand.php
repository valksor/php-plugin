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
use Composer\Package\PackageInterface;
use Symfony\Component\Console\Command\Command;
use ValksorPlugin\Contracts\RecipeCommandInterface;
use ValksorPlugin\RecipeHandler;

use function sprintf;

/**
 * Abstract base class for Valksor recipe commands.
 *
 * Provides common functionality for lock file validation, package lookup,
 * and Composer/IO setup to reduce code duplication between commands.
 */
abstract class AbstractValksorRecipeCommand extends BaseCommand implements RecipeCommandInterface
{
    protected ?RecipeHandler $handler = null;

    public function getHandler(): RecipeHandler
    {
        return $this->handler ??= new RecipeHandler($this->requireComposer(), $this->getIO());
    }

    /**
     * Returns a message indicating that no local recipe was found for the package.
     * This message is displayed when the package exists but has no recipe/manifest.json
     * in its installation directory.
     */
    public function getNoRecipeMessage(
        string $packageName,
    ): string {
        return sprintf('<comment>No local recipe found for %s.</comment>', $packageName);
    }

    /**
     * Returns an error message indicating that the requested package is not installed.
     * This message is displayed when the package name provided by the user does not
     * exist in the composer.lock file.
     */
    public function getNotFoundMessage(
        string $packageName,
    ): string {
        return sprintf('<error>Package %s is not installed.</error>', $packageName);
    }

    /**
     * Find a package by name in the locked repository.
     *
     * @param string $packageName The package name to search for
     *
     * @return PackageInterface|null The found package, or null if not found
     */
    protected function findPackageByName(
        string $packageName,
    ): ?PackageInterface {
        foreach ($this->requireComposer()->getLocker()->getLockedRepository()->getPackages() as $package) {
            if ($package->getName() === $packageName) {
                return $package;
            }
        }

        return null;
    }

    /**
     * Process a specific package.
     *
     * @param string $packageName The package name to process
     *
     * @return int Command exit code
     */
    protected function processSpecificPackage(
        string $packageName,
    ): int {
        $this->getIO()->writeError(sprintf('<info>Searching for local recipe to apply for %s...</info>', $packageName));

        $package = $this->findPackageByName($packageName);

        if (!$package) {
            $this->getIO()->writeError($this->getNotFoundMessage($packageName));

            return Command::SUCCESS;
        }

        if ($this->processPackage($package)) {
            $this->getIO()->writeError($this->getSuccessMessage($packageName));

            return Command::SUCCESS;
        }

        $this->getIO()->writeError($this->getNoRecipeMessage($packageName));

        return Command::SUCCESS;
    }

    /**
     * Validate that composer.lock file exists.
     *
     * @return int 0 if lock file exists, 1 otherwise
     */
    protected function validateLockFile(): int
    {
        if (!$this->requireComposer()->getLocker()->isLocked()) {
            $this->getIO()->writeError('<error>No lock file found. Run `composer install` first.</error>');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
