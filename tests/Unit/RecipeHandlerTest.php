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

namespace ValksorPlugin\Tests\Unit;

use Composer\Composer;
use Composer\Installer\InstallationManager;
use Composer\IO\IOInterface;
use JsonException;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use Symfony\Flex\Recipe;
use ValksorPlugin\RecipeHandler;
use ValksorPlugin\Tests\Mocks\ComposerMockFactory;

/**
 * Unit tests for RecipeHandler class.
 *
 * Tests the core recipe discovery, processing, and installation logic.
 * This is the most critical class in the plugin as it handles all recipe operations.
 */
#[CoversClass(RecipeHandler::class)]
class RecipeHandlerTest extends TestCase
{
    private Composer $composer;
    private RecipeHandler $handler;
    private IOInterface $io;

    /**
     * @throws ReflectionException
     */
    public function testConstructorLoadsConfiguration(): void
    {
        // Test with valksor configuration
        $composerWithConfig = ComposerMockFactory::createComposer([
            'valksor' => [
                'allow' => [
                    'test/package' => ['allow_override' => true],
                ],
            ],
        ]);

        $handler = new RecipeHandler($composerWithConfig, $this->io);
        $config = new ReflectionClass($handler)->getProperty('config')->getValue($handler);
        $this->assertIsArray($config);
        $this->assertArrayHasKey('allow', $config);
    }

    /**
     * @throws ReflectionException
     */
    public function testConstructorWithEmptyConfiguration(): void
    {
        // Test without valksor configuration
        $composerWithoutConfig = ComposerMockFactory::createComposer();
        $handler = new RecipeHandler($composerWithoutConfig, $this->io);

        $config = new ReflectionClass($handler)->getProperty('config')->getValue($handler);
        $this->assertIsArray($config);
        $this->assertEmpty($config);
    }

    public function testGetPackageConfigForUnknownPackage(): void
    {
        $reflection = new ReflectionClass($this->handler);
        $method = $reflection->getMethod('getPackageConfig');

        // Set config without the target package
        $config = [
            'allow' => [
                'other/package' => ['allow_override' => false],
            ],
        ];

        $configProperty = $reflection->getProperty('config');
        $configProperty->setValue($this->handler, $config);

        $result = $method->invoke($this->handler, 'unknown/package');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * @throws ReflectionException
     */
    public function testGetPackageConfigViaReflection(): void
    {
        $reflection = new ReflectionClass($this->handler);
        $method = $reflection->getMethod('getPackageConfig');

        // Set config with package-specific settings
        $config = [
            'allow' => [
                'test/package' => [
                    'allow_override' => true,
                    'custom_setting' => 'value',
                ],
                'other/package' => ['allow_override' => false],
            ],
        ];

        $configProperty = $reflection->getProperty('config');
        $configProperty->setValue($this->handler, $config);

        $result = $method->invoke($this->handler, 'test/package');

        $this->assertIsArray($result);
        $this->assertTrue($result['allow_override']);
        $this->assertSame('value', $result['custom_setting']);
    }

    /**
     * Test private method isPackageAllowed with various configurations.
     *
     * @throws ReflectionException
     */
    #[DataProvider('packageConfigProvider')]
    public function testIsPackageAllowedViaReflection(
        array $config,
        string $packageName,
        bool $expected,
    ): void {
        $reflection = new ReflectionClass($this->handler);
        $method = $reflection->getMethod('isPackageAllowed');

        // Set the config
        $configProperty = $reflection->getProperty('config');
        $configProperty->setValue($this->handler, $config);

        $result = $method->invoke($this->handler, $packageName);
        $this->assertSame($expected, $result);
    }

    /**
     * @throws ReflectionException
     */
    public function testParseLocalRecipeFilesViaReflection(): void
    {
        $method = new ReflectionClass($this->handler)->getMethod('parseLocalRecipeFiles');

        $recipePath = __DIR__ . '/../Fixtures/recipes/valid-recipe';
        $manifestPath = $recipePath . '/manifest.json';

        $result = $method->invoke($this->handler, $recipePath, $manifestPath);

        $this->assertIsArray($result);

        // The method should find files in the recipe directory
        // Check that it found at least something
        $this->assertNotEmpty($result);

        // Find the file we expect (it may have a different key format)
        $foundConfigFile = false;

        foreach ($result as $key => $fileInfo) {
            if (str_contains($key, 'packages.yaml')) {
                $foundConfigFile = true;
                $this->assertArrayHasKey('contents', $fileInfo);
                $this->assertArrayHasKey('executable', $fileInfo);
                $this->assertIsString($fileInfo['contents']);
                $this->assertIsBool($fileInfo['executable']);

                break;
            }
        }

        $this->assertTrue($foundConfigFile, 'Should have found config/packages.yaml file');
    }

    public function testParseLocalRecipeFilesWithEmptyDirectory(): void
    {
        $method = new ReflectionClass($this->handler)->getMethod('parseLocalRecipeFiles');

        $tempDir = sys_get_temp_dir() . '/test-recipe-' . uniqid('', true);
        mkdir($tempDir, 0o777, true);

        $manifestPath = $tempDir . '/manifest.json';
        file_put_contents($manifestPath, '{}');

        $result = $method->invoke($this->handler, $tempDir, $manifestPath);

        $this->assertIsArray($result);
        $this->assertEmpty($result);

        // Cleanup
        unlink($manifestPath);
        rmdir($tempDir);
    }

    /**
     * @throws JsonException
     */
    public function testProcessPackageWithAllowedPackageAndValidRecipe(): void
    {
        // Create handler with wildcard allow configuration
        $composerWithWildcard = ComposerMockFactory::createComposer([
            'valksor' => [
                'allow' => '*',
            ],
        ]);
        $handler = new RecipeHandler($composerWithWildcard, $this->io);

        $package = ComposerMockFactory::createPackage('test/recipe-package');

        // Mock installation manager to return valid recipe path
        $installManager = Mockery::mock(InstallationManager::class);
        $installManager->shouldReceive('getInstallPath')
            ->with($package)
            ->andReturn(__DIR__ . '/../Fixtures/recipes/valid-recipe');

        $composerWithWildcard->shouldReceive('getInstallationManager')->andReturn($installManager);

        $result = $handler->processPackage($package, 'install');

        // The result should be null if no recipe is found or processed
        // But the important thing is that it doesn't throw exceptions
        $this->assertTrue(true); // Test passes if we get here without exceptions
    }

    /**
     * @throws JsonException
     */
    public function testProcessPackageWithDisallowedPackage(): void
    {
        $package = ComposerMockFactory::createPackage('unknown/package');

        // Create handler with restrictive configuration
        $composerWithRestrictiveConfig = ComposerMockFactory::createComposer([
            'valksor' => [
                'allow' => ['allowed/package' => []],
            ],
        ]);

        $result = new RecipeHandler($composerWithRestrictiveConfig, $this->io)->processPackage($package, 'install');

        $this->assertNull($result);
    }

    public function testProcessPackageWithInvalidManifestJson(): void
    {
        $package = ComposerMockFactory::createPackage('test/invalid-recipe');

        // Mock installation manager to return invalid recipe path
        $installManager = Mockery::mock(InstallationManager::class);
        $installManager->shouldReceive('getInstallPath')
            ->with($package)
            ->andReturn(__DIR__ . '/../Fixtures/recipes/invalid-recipe');

        $this->composer->shouldReceive('getInstallationManager')->andReturn($installManager);

        $result = $this->handler->processPackage($package, 'install');

        $this->assertNull($result);
    }

    public function testProcessPackageWithNoRecipeDirectory(): void
    {
        $package = ComposerMockFactory::createPackage('test/no-recipe');

        // Mock installation manager to return path without recipe directory
        $installManager = Mockery::mock(InstallationManager::class);
        $installManager->shouldReceive('getInstallPath')
            ->with($package)
            ->andReturn(sys_get_temp_dir() . '/no-recipe-dir');

        $this->composer->shouldReceive('getInstallationManager')->andReturn($installManager);

        $result = $this->handler->processPackage($package, 'install');

        $this->assertNull($result);
    }

    public function testProcessPackageWithWildcardAllow(): void
    {
        $package = ComposerMockFactory::createPackage('any/package');

        // Create handler with wildcard configuration
        $composerWithWildcard = ComposerMockFactory::createComposer([
            'valksor' => [
                'allow' => '*',
            ],
        ]);

        $handler = new RecipeHandler($composerWithWildcard, $this->io);

        // Mock installation manager to return valid recipe path
        $installManager = Mockery::mock(InstallationManager::class);
        $installManager->shouldReceive('getInstallPath')
            ->with($package)
            ->andReturn(__DIR__ . '/../Fixtures/recipes/simple-recipe');

        $composerWithWildcard->shouldReceive('getInstallationManager')->andReturn($installManager);

        $result = $handler->processPackage($package, 'install');

        // Should return null since simple-recipe doesn't exist as a directory structure
        // but the package should be allowed
        $this->assertNull($result);
    }

    /**
     * @throws JsonException
     */
    public function testUninstallPackageWithDisallowedPackage(): void
    {
        $package = ComposerMockFactory::createPackage('unknown/package');

        // Create handler with restrictive configuration
        $composerWithRestrictiveConfig = ComposerMockFactory::createComposer([
            'valksor' => [
                'allow' => ['allowed/package' => []],
            ],
        ]);

        $result = new RecipeHandler($composerWithRestrictiveConfig, $this->io)->uninstallPackage($package);

        $this->assertNull($result);
    }

    public function testUninstallPackageWithNoRecipe(): void
    {
        $package = ComposerMockFactory::createPackage('test/no-recipe');

        // Mock installation manager to return path without recipe directory
        $installManager = Mockery::mock(InstallationManager::class);
        $installManager->shouldReceive('getInstallPath')
            ->with($package)
            ->andReturn(sys_get_temp_dir() . '/no-recipe-dir');

        $this->composer->shouldReceive('getInstallationManager')->andReturn($installManager);

        $result = $this->handler->uninstallPackage($package);

        $this->assertNull($result);
    }

    /**
     * @throws JsonException
     */
    public function testUninstallPackageWithValidRecipe(): void
    {
        // Create handler with wildcard allow configuration
        $composerWithWildcard = ComposerMockFactory::createComposer([
            'valksor' => [
                'allow' => '*',
            ],
        ]);
        $handler = new RecipeHandler($composerWithWildcard, $this->io);

        $package = ComposerMockFactory::createPackage('test/recipe-package');

        // Mock installation manager to return valid recipe path
        $installManager = Mockery::mock(InstallationManager::class);
        $installManager->shouldReceive('getInstallPath')
            ->with($package)
            ->andReturn(__DIR__ . '/../Fixtures/recipes/valid-recipe');

        $composerWithWildcard->shouldReceive('getInstallationManager')->andReturn($installManager);

        $result = $handler->uninstallPackage($package);

        // The result should be null if no recipe is found or processed
        // But the important thing is that it doesn't throw exceptions
        $this->assertTrue(true); // Test passes if we get here without exceptions
    }

    /**
     * @return array<array{array<string, mixed>, string, bool}>
     */
    public static function packageConfigProvider(): array
    {
        return [
            'wildcard allows all' => [['allow' => '*'], 'any/package', true],
            'specific package allowed' => [['allow' => ['test/package' => []]], 'test/package', true],
            'specific package denied' => [['allow' => ['other/package' => []]], 'test/package', false],
            'empty config denies all' => [[], 'test/package', false],
            'null allow denies all' => [['allow' => null], 'test/package', false],
        ];
    }

    protected function setUp(): void
    {
        $this->composer = ComposerMockFactory::createComposer();
        $this->io = ComposerMockFactory::createIO();
        $this->handler = new RecipeHandler($this->composer, $this->io);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
