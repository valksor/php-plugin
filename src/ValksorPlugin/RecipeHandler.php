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

namespace ValksorPlugin;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use FilesystemIterator;
use JsonException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Flex\Configurator;
use Symfony\Flex\Lock;
use Symfony\Flex\Options;
use Symfony\Flex\Recipe;

use function array_key_exists;
use function array_merge;
use function file_get_contents;
use function getcwd;
use function is_array;
use function is_dir;
use function is_file;
use function json_decode;
use function json_last_error;
use function sprintf;
use function str_replace;

use const JSON_ERROR_NONE;
use const JSON_THROW_ON_ERROR;

final class RecipeHandler
{
    private const string CONFIG_KEY = 'valksor';
    private const string RECIPE_DIR = 'recipe';

    private array $config;
    private ?Configurator $configurator = null;
    private ?Lock $lock = null;

    public function __construct(
        private readonly Composer $composer,
        private readonly IOInterface $io,
    ) {
        $this->config = $composer->getPackage()->getExtra()[self::CONFIG_KEY] ?? [];
    }

    /**
     * The main method to process a single package.
     *
     * @throws JsonException
     */
    public function processPackage(
        PackageInterface $package,
        string $operation,
    ): ?Recipe {
        $packageName = $package->getName();

        if (!$this->isPackageAllowed($packageName)) {
            return null;
        }

        $localRecipe = $this->getLocalRecipe($package, $operation);

        if (!$localRecipe) {
            return null;
        }

        $allowOverride = $this->getPackageConfig($packageName)['allow_override'] ?? false;

        if (!$allowOverride) {
            // For manual install, we can be more aggressive. Let's just apply it.
            // The user explicitly ran the command.
            $this->io->writeError(sprintf('  - Applying local recipe for <info>%s</info>', $packageName));
        } else {
            $this->io->writeError(sprintf('  - Applying local recipe for <info>%s</info> (override enabled)', $packageName));
        }

        $this->initializeFlexObjects();

        // Add recipe to lock before installing (like Symfony Flex does)
        $this->lock->set($packageName, ['version' => $package->getPrettyVersion()]);

        $this->configurator->install($localRecipe, $this->lock);
        $this->lock->write();

        return $localRecipe;
    }

    /**
     * Uninstalls a local recipe for a package.
     *
     * @throws JsonException
     */
    public function uninstallPackage(
        PackageInterface $package,
    ): ?Recipe {
        $packageName = $package->getName();

        if (!$this->isPackageAllowed($packageName)) {
            return null;
        }

        $localRecipe = $this->getLocalRecipe($package, 'uninstall');

        if (!$localRecipe) {
            return null;
        }

        $this->io->writeError(sprintf('  - Removing local recipe for <info>%s</info>', $packageName));

        $this->initializeFlexObjects();

        // Remove from symfony.lock BEFORE unconfiguring (like Symfony Flex does)
        // This is important for Options::getRemovableFiles() to work correctly
        $this->lock->remove($packageName);

        // Unconfigure the recipe (removes env vars, files, etc.)
        $this->configurator->unconfigure($localRecipe, $this->lock);

        // Write changes
        $this->lock->write();

        return $localRecipe;
    }

    /**
     * @throws JsonException
     */
    private function getLocalRecipe(
        PackageInterface $package,
        string $operation,
    ): ?Recipe {
        $installPath = $this->composer->getInstallationManager()->getInstallPath($package);

        if (!$installPath) {
            return null;
        }

        $recipePath = $installPath . '/' . self::RECIPE_DIR;
        $manifestPath = $recipePath . '/manifest.json';

        if (!is_dir($recipePath) || !is_file($manifestPath)) {
            return null;
        }

        $manifest = json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);

        if (JSON_ERROR_NONE !== json_last_error()) {
            $this->io->writeError(sprintf('  - <error>Warning: Invalid manifest.json in %s</error>', $recipePath));

            return null;
        }

        $files = $this->parseLocalRecipeFiles($recipePath, $manifestPath);
        $origin = $package->getName() . ':' . self::RECIPE_DIR;

        return new Recipe(
            $package,
            $package->getName(),
            $operation,
            [
                'manifest' => $manifest,
                'files' => $files,
                'origin' => $origin,
            ],
        );
    }

    private function getPackageConfig(
        string $packageName,
    ): array {
        $allowConfig = $this->config['allow'] ?? null;

        if (!is_array($allowConfig)) {
            return [];
        }
        $packageConfig = $allowConfig[$packageName] ?? [];

        return is_array($packageConfig) ? $packageConfig : [];
    }

    private function initializeFlexObjects(): void
    {
        if ($this->lock) {
            return;
        }
        $this->lock = new Lock(getcwd() . '/symfony.lock');

        // Build options with defaults (like Symfony Flex does)
        $extra = $this->composer->getPackage()->getExtra();
        $flexOptions = array_merge([
            'src-dir' => 'src',
            'var-dir' => 'var',
            'public-dir' => 'public',
            'root-dir' => $extra['symfony']['root-dir'] ?? '.',
            'runtime' => $extra['runtime'] ?? [],
        ], $extra['flex'] ?? []);

        $options = new Options($flexOptions, $this->io, $this->lock);
        $this->configurator = new Configurator($this->composer, $this->io, $options);
    }

    private function isPackageAllowed(
        string $packageName,
    ): bool {
        $allowConfig = $this->config['allow'] ?? null;

        if ('*' === $allowConfig) {
            return true;
        }

        if (is_array($allowConfig) && array_key_exists($packageName, $allowConfig)) {
            return true;
        }

        return false;
    }

    private function parseLocalRecipeFiles(
        string $recipePath,
        string $manifestPath,
    ): array {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($recipePath, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                continue;
            }
            $realPath = $file->getRealPath();

            if ($realPath === $manifestPath) {
                continue;
            }
            $relativePath = str_replace($recipePath . '/', '', $realPath);
            $files[$relativePath] = [
                'contents' => file_get_contents($realPath),
                'executable' => is_executable($realPath),
            ];
        }

        return $files;
    }
}
