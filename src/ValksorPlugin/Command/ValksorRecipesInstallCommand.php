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

use Composer\Package\PackageInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Flex\Recipe;

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
     * {@inheritdoc}
     *
     * Returns a success message indicating that the local recipe was successfully applied.
     * This message is displayed when the RecipeHandler successfully processes and
     * installs the package's local recipe.
     */
    public function getSuccessMessage(
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
     * @param PackageInterface $package The package to process the recipe for
     *
     * @return Recipe|null The applied recipe, or null if no recipe was found/processed
     */
    public function processPackage(
        PackageInterface $package,
    ): ?Recipe {
        // We use 'update' as the operation, as it's a safe default for re-applying a recipe.
        return $this->getHandler()->processPackage($package, 'update');
    }

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
        if (Command::FAILURE === $this->validateLockFile()) {
            return Command::FAILURE;
        }

        $packageName = $input->getArgument('package');

        // If a specific package is requested
        if ($packageName) {
            return $this->processSpecificPackage($packageName);
        }

        // No specific package - process all packages (original behavior)
        return $this->processAllPackages();
    }

    /**
     * Process all packages.
     *
     * @return int Command exit code (always 0 for batch processing)
     */
    private function processAllPackages(): int
    {
        $this->getIO()->writeError('<info>Searching for local recipes to apply...</info>');

        $packages = $this->requireComposer()->getLocker()->getLockedRepository()->getPackages();
        $processedCount = 0;

        foreach ($packages as $package) {
            if ($this->processPackage($package)) {
                $processedCount++;
            }
        }

        if (0 === $processedCount) {
            $this->getIO()->writeError('<info>No local recipes found to apply.</info>');
        } else {
            $this->getIO()->writeError(sprintf('<info>Successfully applied %d local recipe(s).</info>', $processedCount));
        }

        return Command::SUCCESS;
    }
}
