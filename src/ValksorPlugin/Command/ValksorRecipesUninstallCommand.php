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
 * Composer command to manually uninstall local recipes.
 *
 * This command allows users to manually remove local recipes that were
 * previously applied to packages. It cleans up configuration files,
 * environment variables, and other changes made by the recipe.
 */
class ValksorRecipesUninstallCommand extends AbstractValksorRecipeCommand
{
    /**
     * {@inheritdoc}
     *
     * Returns a success message indicating that the local recipe was successfully removed.
     * This message is displayed when the RecipeHandler successfully finds and
     * uninstalls the package's local recipe, cleaning up all configuration files
     * and environment variables.
     */
    public function getSuccessMessage(
        string $packageName,
    ): string {
        return sprintf('<info>Successfully removed local recipe for %s.</info>', $packageName);
    }

    /**
     * {@inheritdoc}
     *
     * Processes a package for recipe uninstallation.
     *
     * This method delegates to the RecipeHandler's uninstallPackage method,
     * which handles the complete cleanup of recipe-applied changes including:
     * - Removal of configuration files
     * - Cleanup of environment variables
     * - Restoration of original files (when applicable)
     * - Updates to symfony.lock file
     *
     * @param PackageInterface $package The package to uninstall the recipe for
     *
     * @return Recipe|null The uninstalled recipe, or null if no recipe was found/processed
     */
    public function processPackage(
        PackageInterface $package,
    ): ?Recipe {
        return $this->getHandler()->uninstallPackage($package);
    }

    /**
     * Configure the command definition.
     *
     * Sets up the command name, description, and arguments for the
     * valksor:uninstall command.
     */
    protected function configure(): void
    {
        $this
            ->setName('valksor:uninstall')
            ->setDescription('Removes a local recipe for a specific package.')
            ->addArgument('package', InputArgument::REQUIRED, 'Package name to uninstall (e.g., valksor/php-plugin)');
    }

    /**
     * Execute the command to uninstall local recipes.
     *
     * Removes local recipes for a specific package by cleaning up configuration
     * files, environment variables, and other changes made during installation.
     * Validates that a lock file exists and the package is installed.
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
        $packageName = $input->getArgument('package');
        $this->getIO()->writeError(sprintf('<info>Searching for local recipe to remove for %s...</info>', $packageName));

        // Validate lock file
        $lockValidation = $this->validateLockFile();

        if (Command::FAILURE === $lockValidation) {
            return Command::FAILURE;
        }

        return $this->processSpecificPackage($packageName);
    }
}
