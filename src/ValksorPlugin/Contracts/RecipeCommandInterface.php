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

namespace ValksorPlugin\Contracts;

use Composer\Package\PackageInterface;
use Symfony\Flex\Recipe;

interface RecipeCommandInterface
{
    /**
     * Get the failure message for when no recipe is found.
     *
     * @param string $packageName The package name
     *
     * @return string The failure message
     */
    public function getNoRecipeMessage(
        string $packageName,
    ): string;

    /**
     * Get the not found message for when a package is not installed.
     *
     * @param string $packageName The package name
     *
     * @return string The not found message
     */
    public function getNotFoundMessage(
        string $packageName,
    ): string;

    /**
     * Get the success message for when a package is processed successfully.
     *
     * @param string $packageName The package name
     *
     * @return string The success message
     */
    public function getSuccessMessage(
        string $packageName,
    ): string;

    /**
     * Process a package using the RecipeHandler.
     *
     * This is a template method that subclasses should override to provide
     * their specific processing logic (install, uninstall, etc.).
     *
     * @param PackageInterface $package The package to process
     *
     * @return Recipe|null The result of the processing
     */
    public function processPackage(
        PackageInterface $package,
    ): ?Recipe;
}
