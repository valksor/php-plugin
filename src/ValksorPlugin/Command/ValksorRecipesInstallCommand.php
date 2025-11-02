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

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Flex\Recipe;
use ValksorPlugin\RecipeHandler;

use function sprintf;

/**
 * Composer command to manually install local recipes.
 *
 * This command allows users to manually trigger the installation of local
 * recipes for all packages or a specific package. It's useful when recipes
 * weren't applied during automatic composer operations or when re-applying
 * recipes after configuration changes.
 */
class ValksorRecipesInstallCommand extends AbstractValksorRecipeCommand
{
    /**
     * Configure the command definition.
     *
     * Sets up the command name, description, and arguments for the
     * valksor:install command.
     */
    protected function configure(): void
    {
        $this
            ->setName('valksor:install')
            ->setDescription('Applies local recipes from package directories for all or a specific installed package.')
            ->addArgument('package', InputArgument::OPTIONAL, 'Package name to install (e.g., valksor/php-plugin). If not specified, all packages will be processed.');
    }

    /**
     * Execute the command to install local recipes.
     *
     * Processes either all installed packages or a specific package to
     * discover and apply local recipes. Validates that a lock file exists
     * and provides appropriate feedback on the operation results.
     *
     * @param InputInterface  $input  The command input interface
     * @param OutputInterface $output The command output interface
     *
     * @return int Command exit code (0 for success, 1 for error)
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        [$composer, $io] = $this->setupComposerAndIO();

        // Validate lock file
        $lockValidation = $this->validateLockFile($composer, $io);

        if (1 === $lockValidation) {
            return 1;
        }

        $packageName = $input->getArgument('package');

        // If a specific package is requested
        if ($packageName) {
            return $this->processSpecificPackage($composer, $io, $packageName);
        }

        // No specific package - process all packages (original behavior)
        return $this->processAllPackages($composer, $io);
    }

    /**
     * {@inheritdoc}
     *
     * Returns a message indicating that no local recipe was found for the package.
     * This message is displayed when the package exists but has no recipe/manifest.json
     * in its installation directory.
     */
    protected function getNoRecipeMessage(
        string $packageName,
    ): string {
        return sprintf('<comment>No local recipe found for %s.</comment>', $packageName);
    }

    /**
     * {@inheritdoc}
     *
     * Returns an error message indicating that the requested package is not installed.
     * This message is displayed when the package name provided by the user does not
     * exist in the composer.lock file.
     */
    protected function getNotFoundMessage(
        string $packageName,
    ): string {
        return sprintf('<error>Package %s is not installed.</error>', $packageName);
    }

    /**
     * {@inheritdoc}
     *
     * Returns a success message indicating that the local recipe was successfully applied.
     * This message is displayed when the RecipeHandler successfully processes and
     * installs the package's local recipe.
     */
    protected function getSuccessMessage(
        string $packageName,
    ): string {
        return sprintf('<info>Successfully applied local recipe for %s.</info>', $packageName);
    }

    /**
     * {@inheritdoc}
     *
     * Processes a package for recipe installation using the 'update' operation.
     *
     * We use 'update' instead of 'install' as the operation type because:
     * 1. It's safer for re-applying recipes (handles existing configurations gracefully)
     * 2. It allows the recipe to update files that may already exist
     * 3. It provides better error handling for partially applied recipes
     *
     * This approach ensures that manual recipe application behaves consistently
     * with automatic recipe updates during composer operations.
     *
     * @param RecipeHandler    $handler The RecipeHandler instance to use
     * @param PackageInterface $package The package to process the recipe for
     *
     * @return Recipe|null The applied recipe, or null if no recipe was found/processed
     */
    protected function processPackage(
        RecipeHandler $handler,
        PackageInterface $package,
    ): ?Recipe {
        // We use 'update' as the operation, as it's a safe default for re-applying a recipe.
        return $handler->processPackage($package, 'update');
    }

    /**
     * Process all packages.
     *
     * @param Composer    $composer The Composer instance
     * @param IOInterface $io       The I/O interface
     *
     * @return int Command exit code (always 0 for batch processing)
     */
    private function processAllPackages(
        Composer $composer,
        IOInterface $io,
    ): int {
        $io->writeError('<info>Searching for local recipes to apply...</info>');

        $packages = $composer->getLocker()->getLockedRepository()->getPackages();
        $handler = $this->createRecipeHandler($composer, $io);
        $processedCount = 0;

        foreach ($packages as $package) {
            if ($this->processPackage($handler, $package)) {
                $processedCount++;
            }
        }

        if (0 === $processedCount) {
            $io->writeError('<info>No local recipes found to apply.</info>');
        } else {
            $io->writeError(sprintf('<info>Successfully applied %d local recipe(s).</info>', $processedCount));
        }

        return 0;
    }
}
