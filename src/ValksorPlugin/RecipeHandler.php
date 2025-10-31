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

/**
 * Handles discovery and processing of local recipes for packages.
 *
 * This class is responsible for finding recipes in package directories,
 * parsing recipe manifests, and applying configurations using Symfony Flex's
 * configurator system. It manages the complete recipe lifecycle including
 * installation, updates, and uninstallation.
 */
class RecipeHandler
{
    /** Configuration key in composer.json */
    private const string CONFIG_KEY = 'valksor';

    /** Directory name within packages that contains recipes */
    private const string RECIPE_DIR = 'recipe';

    /** @var array<string, mixed> Plugin configuration from composer.json */
    private array $config;

    /** @var Configurator|null Symfony Flex configurator instance */
    private ?Configurator $configurator = null;

    /** @var Lock|null Symfony Flex lock file manager */
    private ?Lock $lock = null;

    /**
     * Create a new RecipeHandler instance.
     *
     * Initializes the handler with Composer and IO interfaces, and loads
     * the plugin configuration from composer.json's extra.valksor section.
     *
     * @param Composer    $composer The Composer instance
     * @param IOInterface $io       The I/O interface for user interaction
     */
    public function __construct(
        private readonly Composer $composer,
        private readonly IOInterface $io,
    ) {
        $this->config = $composer->getPackage()->getExtra()[self::CONFIG_KEY] ?? [];
    }

    /**
     * Process a package and apply its local recipe if available.
     *
     * This is the main entry point for recipe processing. It checks if the package
     * is allowed to have recipes, discovers local recipes, and applies them using
     * Symfony Flex's configurator system.
     *
     * @param PackageInterface $package   The package to process
     * @param string           $operation The operation type ('install', 'update')
     *
     * @return Recipe|null The applied recipe, or null if no recipe found/allowed
     *
     * @throws JsonException When recipe manifest cannot be parsed
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

        $this->configurator->install($localRecipe, $this->lock, ['force' => $allowOverride]);
        $this->lock->write();

        return $localRecipe;
    }

    /**
     * Uninstalls a local recipe for a package.
     *
     * Removes configuration files, environment variables, and other changes
     * made by a package's local recipe during uninstallation. This method
     * handles the complete cleanup process including updating the lock file.
     *
     * @param PackageInterface $package The package to uninstall the recipe for
     *
     * @return Recipe|null The uninstalled recipe, or null if no recipe found/allowed
     *
     * @throws JsonException When recipe manifest cannot be parsed
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
     * Discover and load a local recipe from a package directory.
     *
     * Searches for a recipe/manifest.json file within the installed package
     * directory, parses the manifest, and creates a Recipe object with the
     * discovered configuration files and metadata.
     *
     * @param PackageInterface $package   The package to search for recipes in
     * @param string           $operation The operation type ('install', 'update', 'uninstall')
     *
     * @return Recipe|null The discovered recipe, or null if no recipe found
     *
     * @throws JsonException When recipe manifest cannot be parsed
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

    /**
     * Get package-specific configuration from plugin settings.
     *
     * Retrieves the configuration for a specific package from the plugin's
     * allow configuration in composer.json. Returns empty array if no
     * configuration is found for the package.
     *
     * @param string $packageName The name of the package to get configuration for
     *
     * @return array<string, mixed> The package configuration, or empty array if none found
     */
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

    /**
     * Initialize Symfony Flex objects (lock and configurator).
     *
     * Creates the Symfony Flex Lock and Configurator instances if they
     * haven't been initialized yet. Uses lazy initialization to avoid
     * creating these objects until they're actually needed.
     */
    private function initializeFlexObjects(): void
    {
        if ($this->lock) {
            return;
        }
        $this->lock = new Lock(getcwd() . '/symfony.lock');

        // Build options with defaults (like Symfony Flex does)
        $extra = $this->composer->getPackage()->getExtra();
        $flexOptions = array_merge(
            [
                'src-dir' => 'src',
                'var-dir' => 'var',
                'public-dir' => 'public',
                'root-dir' => $extra['symfony']['root-dir'] ?? '.',
                'runtime' => $extra['runtime'] ?? [],
            ],
            $extra['flex'] ?? [],
        );

        $options = new Options($flexOptions, $this->io, $this->lock);
        $this->configurator = new Configurator($this->composer, $this->io, $options);
    }

    /**
     * Check if a package is allowed to have recipes processed.
     *
     * Determines whether a package should be processed based on the plugin's
     * allow configuration. Supports wildcard ('*') to allow all packages or
     * a specific list of allowed packages.
     *
     * @param string $packageName The name of the package to check
     *
     * @return bool True if the package is allowed, false otherwise
     */
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

    /**
     * Parse all files in a local recipe directory.
     *
     * Recursively scans the recipe directory and loads all files except
     * the manifest.json file. Returns an array mapping relative file paths
     * to file contents and executable status.
     *
     * @param string $recipePath   The absolute path to the recipe directory
     * @param string $manifestPath The absolute path to the manifest.json file
     *
     * @return array<string, array{contents: string|false, executable: bool}> Array of recipe files
     */
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
