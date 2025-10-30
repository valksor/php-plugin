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

class ValksorRecipesUninstallCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('valksor:uninstall')
            ->setDescription('Removes a local recipe for a specific package.')
            ->addArgument('package', InputArgument::REQUIRED, 'Package name to uninstall (e.g., valksor/php-plugin)');
    }

    /**
     * @throws JsonException
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $composer = $this->requireComposer();
        $io = $this->getIO();
        $handler = new RecipeHandler($composer, $io);

        $packageName = $input->getArgument('package');
        $io->writeError(sprintf('<info>Searching for local recipe to remove for %s...</info>', $packageName));

        $locker = $composer->getLocker();

        if (!$locker->isLocked()) {
            $io->writeError('<error>No lock file found. Run `composer install` first.</error>');

            return 1;
        }

        foreach ($locker->getLockedRepository()->getPackages() as $package) {
            if ($package->getName() === $packageName) {
                if ($handler->uninstallPackage($package)) {
                    $io->writeError(sprintf('<info>Successfully removed local recipe for %s.</info>', $packageName));

                    return 0;
                }

                $io->writeError(sprintf('<comment>No local recipe found for %s.</comment>', $packageName));

                return 1;
            }
        }

        $io->writeError(sprintf('<error>Package %s is not installed.</error>', $packageName));

        return 1;
    }
}
