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
use JsonException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
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
class ValksorRecipesInstallCommand extends BaseCommand
{
    /**
     * Configure the command definition.
     *
     * Sets up the command name, description, and arguments for the
     * valksor:install command.
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('valksor:install')
            ->setDescription('Applies local recipes from package directories for all or a specific installed package.')
            ->addArgument('package', InputArgument::OPTIONAL, 'Package name to install (e.g., ozo2003/test-composer-package). If not specified, all packages will be processed.');
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
     *
     * @throws JsonException When recipe manifest cannot be parsed
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $composer = $this->requireComposer();
        $io = $this->getIO();
        $handler = new RecipeHandler($composer, $io);

        $packageName = $input->getArgument('package');
        $locker = $composer->getLocker();

        if (!$locker->isLocked()) {
            $io->writeError('<error>No lock file found. Run `composer install` first.</error>');

            return 1;
        }

        $packages = $locker->getLockedRepository()->getPackages();

        // If a specific package is requested
        if ($packageName) {
            $io->writeError(sprintf('<info>Searching for local recipe to apply for %s...</info>', $packageName));

            foreach ($packages as $package) {
                if ($package->getName() === $packageName) {
                    if ($handler->processPackage($package, 'update')) {
                        $io->writeError(sprintf('<info>Successfully applied local recipe for %s.</info>', $packageName));

                        return 0;
                    }

                    $io->writeError(sprintf('<comment>No local recipe found for %s.</comment>', $packageName));

                    return 1;
                }
            }

            $io->writeError(sprintf('<error>Package %s is not installed.</error>', $packageName));

            return 1;
        }

        // No specific package - process all packages (original behavior)
        $io->writeError('<info>Searching for local recipes to apply...</info>');
        $processedCount = 0;

        foreach ($packages as $package) {
            // We use 'update' as the operation, as it's a safe default for re-applying a recipe.
            if ($handler->processPackage($package, 'update')) {
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
