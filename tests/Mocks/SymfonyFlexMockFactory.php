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

namespace ValksorPlugin\Tests\Mocks;

use Composer\Composer;
use Composer\IO\IOInterface;
use Mockery;
use Mockery\MockInterface;
use Symfony\Flex\Configurator;
use Symfony\Flex\Lock;
use Symfony\Flex\Options;
use Symfony\Flex\Recipe;

/**
 * Factory for creating Symfony Flex-related mocks.
 *
 * This factory provides standardized mocks for Symfony Flex components
 * used throughout the test suite.
 */
class SymfonyFlexMockFactory
{
    /**
     * Create a mock Configurator.
     *
     * @param Composer    $composer The composer instance
     * @param IOInterface $io       The IO interface
     * @param Options     $options  The options instance
     */
    public static function createConfigurator(
        Composer $composer,
        IOInterface $io,
        Options $options,
    ): MockInterface {
        $configurator = Mockery::mock(Configurator::class);
        $configurator->shouldReceive('install')->andReturn(null);
        $configurator->shouldReceive('unconfigure')->andReturn(null);

        return $configurator;
    }

    /**
     * Create a mock Lock instance.
     *
     * @param string                              $lockFile Path to the lock file
     * @param array<string, array<string, mixed>> $data     Initial lock data
     */
    public static function createLock(
        string $lockFile = 'symfony.lock',
        array $data = [],
    ): MockInterface {
        $lock = Mockery::mock(Lock::class);
        $lock->shouldReceive('set')->andReturn(null);
        $lock->shouldReceive('remove')->andReturn(null);
        $lock->shouldReceive('write')->andReturn(null);
        $lock->shouldReceive('has')->andReturn(false);
        $lock->shouldReceive('get')->andReturn(null);

        return $lock;
    }

    /**
     * Create a mock Options instance.
     *
     * @param array<string, mixed> $options Flex options
     * @param IOInterface|null     $io      The IO interface
     * @param Lock|null            $lock    The lock instance
     */
    public static function createOptions(
        array $options = [],
        ?IOInterface $io = null,
        ?Lock $lock = null,
    ): MockInterface {
        return Mockery::mock(Options::class);
    }

    /**
     * Create a mock Recipe instance.
     *
     * @param string               $name   Recipe name
     * @param string               $origin Recipe origin
     * @param array<string, mixed> $data   Recipe data
     */
    public static function createRecipe(
        string $name = 'test/recipe',
        string $origin = 'test/recipe:recipe',
        array $data = [],
    ): MockInterface {
        $recipe = Mockery::mock(Recipe::class);
        $recipe->shouldReceive('getName')->andReturn($name);
        $recipe->shouldReceive('getOrigin')->andReturn($origin);
        $recipe->shouldReceive('getData')->andReturn($data);

        return $recipe;
    }
}
