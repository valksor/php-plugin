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
    public function getSuccessMessage(
        string $packageName,
    ): string {
        return sprintf('<info>Successfully removed local recipe for %s.</info>', $packageName);
    }

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

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $packageName = $input->getArgument('package');
        $this->getIO()->writeError(sprintf('<info>Searching for local recipe to remove for %s...</info>', $packageName));

        $lockValidation = $this->validateLockFile();

        if (Command::FAILURE === $lockValidation) {
            return Command::FAILURE;
        }

        return $this->processSpecificPackage($packageName);
    }
}
