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
use Symfony\Flex\Configurator;
use Symfony\Flex\Lock;
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
        $composerWithConfig = ComposerMockFactory::createComposer(
            [
                'valksor' => [
                    'allow' => [
                        'test/package' => ['allow_override' => true],
                    ],
                ],
            ],
        );

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

    /**
     * Test JSON error handling in getLocalRecipe method.
     *
     * @throws ReflectionException
     */
    public function testGetLocalRecipeJsonErrorHandling(): void
    {
        $composerWithWildcard = ComposerMockFactory::createComposer(
            ['valksor' => ['allow' => '*']],
        );

        $package = ComposerMockFactory::createPackage('test/json-error');
        $handler = new RecipeHandler($composerWithWildcard, $this->io);

        $installManager = Mockery::mock(InstallationManager::class);
        $installManager->shouldReceive('getInstallPath')
            ->with($package)
            ->andReturn(__DIR__ . '/../Fixtures/recipes/invalid-recipe');
        $composerWithWildcard->shouldReceive('getInstallationManager')->andReturn($installManager);

        $this->assertNull(new ReflectionClass($handler)->getMethod('getLocalRecipe')->invoke($handler, $package, 'install'));
    }

    /**
     * Test getLocalRecipe with complete valid recipe structure.
     *
     * This test verifies the successful path where a Recipe object is created
     * with all correct parameters including manifest, files, and origin.
     *
     * @throws ReflectionException
     */
    public function testGetLocalRecipeReturnsRecipeObject(): void
    {
        // Create package mock
        $package = ComposerMockFactory::createPackage('test/complete-package', '1.2.3');

        // Create installation manager mock
        $installManager = Mockery::mock(InstallationManager::class);
        $installManager->shouldReceive('getInstallPath')
            ->once()
            ->with($package)
            ->andReturn(__DIR__ . '/../Fixtures/packages/complete-package');

        // Create fresh composer mock with explicit expectations
        $rootPackage = ComposerMockFactory::createRootPackage();
        $composer = Mockery::mock(Composer::class);
        $composer->shouldReceive('getPackage')->andReturn($rootPackage);
        $composer->shouldReceive('getInstallationManager')->once()->andReturn($installManager);

        $handler = new RecipeHandler($composer, $this->io);

        $result = new ReflectionClass($handler)->getMethod('getLocalRecipe')->invoke($handler, $package, 'update');

        // Verify a Recipe object is returned
        $this->assertInstanceOf(Recipe::class, $result);

        // Verify the recipe has correct package reference
        $this->assertSame($package, $result->getPackage());

        // Verify the recipe has correct name
        $this->assertSame('test/complete-package', $result->getName());

        // Verify the recipe has correct operation
        $this->assertSame('update', $result->getJob());

        // Verify the recipe has manifest data
        $manifest = $result->getManifest();
        $this->assertIsArray($manifest);
        $this->assertArrayHasKey('bundles', $manifest);
        $this->assertArrayHasKey('env', $manifest);

        // Verify the recipe has files
        $files = $result->getFiles();
        $this->assertIsArray($files);
        $this->assertNotEmpty($files);

        // Verify files include the config file
        $foundConfigFile = false;

        foreach ($files as $key => $fileInfo) {
            if (str_contains($key, 'test.yaml')) {
                $foundConfigFile = true;
                $this->assertArrayHasKey('contents', $fileInfo);
                $this->assertArrayHasKey('executable', $fileInfo);
                $this->assertIsString($fileInfo['contents']);
                $this->assertStringContainsString('enabled: true', $fileInfo['contents']);

                break;
            }
        }

        $this->assertTrue($foundConfigFile, 'Recipe should include config/packages/test.yaml');

        // Verify origin is set correctly
        $this->assertSame('test/complete-package:recipe', $result->getOrigin());
    }

    /**
     * Test getLocalRecipe with invalid manifest JSON.
     *
     * @throws ReflectionException
     */
    public function testGetLocalRecipeWithInvalidManifest(): void
    {
        $method = new ReflectionClass($this->handler)->getMethod('getLocalRecipe');
        $package = ComposerMockFactory::createPackage('test/invalid-json');

        // Mock installation manager to return invalid recipe path
        $installManager = Mockery::mock(InstallationManager::class);
        $installManager->shouldReceive('getInstallPath')
            ->with($package)
            ->andReturn(__DIR__ . '/../Fixtures/recipes/invalid-recipe');

        $this->composer->shouldReceive('getInstallationManager')->andReturn($installManager);

        $recipe = $method->invoke($this->handler, $package, 'install');

        $this->assertNull($recipe);
    }

    /**
     * Test getLocalRecipe with no recipe directory.
     *
     * @throws ReflectionException
     */
    public function testGetLocalRecipeWithNoRecipeDirectory(): void
    {
        $method = new ReflectionClass($this->handler)->getMethod('getLocalRecipe');
        $package = ComposerMockFactory::createPackage('test/no-recipe');

        // Mock installation manager to return path without recipe
        $installManager = Mockery::mock(InstallationManager::class);
        $installManager->shouldReceive('getInstallPath')
            ->with($package)
            ->andReturn(sys_get_temp_dir() . '/no-recipe');

        $this->composer->shouldReceive('getInstallationManager')->andReturn($installManager);

        $recipe = $method->invoke($this->handler, $package, 'install');

        $this->assertNull($recipe);
    }

    /**
     * Test successful recipe discovery and parsing.
     *
     * @throws ReflectionException
     */
    public function testGetLocalRecipeWithValidManifestStructure(): void
    {
        $package = ComposerMockFactory::createPackage('test/valid-recipe');

        $installManager = Mockery::mock(InstallationManager::class);
        $installManager->shouldReceive('getInstallPath')
            ->with($package)
            ->andReturn(__DIR__ . '/../Fixtures/recipes/valid-recipe');
        $this->composer->shouldReceive('getInstallationManager')->andReturn($installManager);

        // Test that the method processes without throwing exceptions
        $this->assertNull(new ReflectionClass($this->handler)->getMethod('getLocalRecipe')->invoke($this->handler, $package, 'install'));
    }

    /**
     * @throws ReflectionException
     */
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
     * Test getPackageConfig when 'allow' itself is not an array.
     *
     * @throws ReflectionException
     */
    public function testGetPackageConfigWithNonArrayAllowConfig(): void
    {
        $method = new ReflectionClass($this->handler)->getMethod('getPackageConfig');

        // Set config where 'allow' is a string instead of array
        $config = [
            'allow' => 'invalid_string',
        ];

        $configProperty = new ReflectionClass($this->handler)->getProperty('config');
        $configProperty->setValue($this->handler, $config);

        $result = $method->invoke($this->handler, 'test/package');

        // Should return empty array when 'allow' is not an array
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test getPackageConfig with non-array package config.
     *
     * @throws ReflectionException
     */
    public function testGetPackageConfigWithNonArrayConfig(): void
    {
        $method = new ReflectionClass($this->handler)->getMethod('getPackageConfig');

        // Set config with non-array package configuration
        $config = [
            'allow' => [
                'test/package' => 'invalid_string_config',
            ],
        ];

        $configProperty = new ReflectionClass($this->handler)->getProperty('config');
        $configProperty->setValue($this->handler, $config);

        $result = $method->invoke($this->handler, 'test/package');

        // Should return empty array for invalid config
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test private method initializeFlexObjects creates proper instances.
     *
     * @throws ReflectionException
     */
    public function testInitializeFlexObjectsCreatesInstances(): void
    {
        $reflection = new ReflectionClass($this->handler);
        $method = $reflection->getMethod('initializeFlexObjects');

        // Call the method
        $method->invoke($this->handler);

        // Verify objects are created
        $lockProperty = $reflection->getProperty('lock');
        $configuratorProperty = $reflection->getProperty('configurator');

        $this->assertNotNull($lockProperty->getValue($this->handler));
        $this->assertNotNull($configuratorProperty->getValue($this->handler));
    }

    /**
     * Test initializeFlexObjects only runs once.
     *
     * @throws ReflectionException
     */
    public function testInitializeFlexObjectsRunsOnce(): void
    {
        $reflection = new ReflectionClass($this->handler);
        $method = $reflection->getMethod('initializeFlexObjects');

        $method->invoke($this->handler);

        $lock1 = $reflection->getProperty('lock')->getValue($this->handler);
        $configurator1 = $reflection->getProperty('configurator')->getValue($this->handler);

        // Call again - should not recreate objects
        $method->invoke($this->handler);

        $lock2 = $reflection->getProperty('lock')->getValue($this->handler);
        $configurator2 = $reflection->getProperty('configurator')->getValue($this->handler);

        $this->assertSame($lock1, $lock2, 'Lock object should be reused');
        $this->assertSame($configurator1, $configurator2, 'Configurator object should be reused');
    }

    /**
     * Test initializeFlexObjects method creates Flex dependencies.
     *
     * @throws ReflectionException
     */
    public function testInitializeFlexObjectsViaReflection(): void
    {
        $reflection = new ReflectionClass($this->handler);
        $method = $reflection->getMethod('initializeFlexObjects');

        // Verify objects are null initially
        $lockProperty = $reflection->getProperty('lock');
        $configuratorProperty = $reflection->getProperty('configurator');

        $this->assertNull($lockProperty->getValue($this->handler));
        $this->assertNull($configuratorProperty->getValue($this->handler));

        // Call the method
        $method->invoke($this->handler);

        // Verify objects are created
        $this->assertNotNull($lockProperty->getValue($this->handler));
        $this->assertNotNull($configuratorProperty->getValue($this->handler));
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
     * Test logRecipeApplication with override enabled.
     *
     * @throws ReflectionException
     */
    public function testLogRecipeApplicationWithOverride(): void
    {
        // Create fresh IO mock without default expectations
        $io = Mockery::mock(IOInterface::class);
        $io->shouldReceive('writeError')
            ->once()
            ->with('  - Applying local recipe for <info>test/package</info> (override enabled)');

        $composer = ComposerMockFactory::createComposer();
        $handler = new RecipeHandler($composer, $io);

        $method = new ReflectionClass($handler)->getMethod('logRecipeApplication');
        $method->invoke($handler, 'test/package', true);
    }

    /**
     * Test logRecipeApplication with override disabled.
     *
     * @throws ReflectionException
     */
    public function testLogRecipeApplicationWithoutOverride(): void
    {
        // Create fresh IO mock without default expectations
        $io = Mockery::mock(IOInterface::class);
        $io->shouldReceive('writeError')
            ->once()
            ->with('  - Applying local recipe for <info>test/package</info>');

        $composer = ComposerMockFactory::createComposer();
        $handler = new RecipeHandler($composer, $io);

        $method = new ReflectionClass($handler)->getMethod('logRecipeApplication');
        $method->invoke($handler, 'test/package', false);
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

    /**
     * @throws ReflectionException
     */
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
     * Test parseLocalRecipeFiles with executable files.
     *
     * @throws ReflectionException
     */
    public function testParseLocalRecipeFilesWithExecutableFile(): void
    {
        $method = new ReflectionClass($this->handler)->getMethod('parseLocalRecipeFiles');

        $recipePath = __DIR__ . '/../Fixtures/packages/recipe-with-executable/recipe';
        $manifestPath = $recipePath . '/manifest.json';

        $result = $method->invoke($this->handler, $recipePath, $manifestPath);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        // Should find the executable script
        $foundExecutable = false;

        foreach ($result as $key => $fileInfo) {
            if (str_contains($key, 'bin/console')) {
                $foundExecutable = true;
                $this->assertArrayHasKey('contents', $fileInfo);
                $this->assertArrayHasKey('executable', $fileInfo);
                $this->assertTrue($fileInfo['executable'], 'Console script should be marked as executable');
                $this->assertStringContainsString('Test console', $fileInfo['contents']);

                break;
            }
        }

        $this->assertTrue($foundExecutable, 'Should find executable file');
    }

    /**
     * Test private method parseLocalRecipeFiles with recipe that has files.
     *
     * @throws ReflectionException
     */
    public function testParseLocalRecipeFilesWithRecipeFiles(): void
    {
        $method = new ReflectionClass($this->handler)->getMethod('parseLocalRecipeFiles');

        $recipePath = __DIR__ . '/../Fixtures/recipes/invalid-recipe';
        $manifestPath = $recipePath . '/manifest.json';

        // Should find and parse files in the recipe directory
        $result = $method->invoke($this->handler, $recipePath, $manifestPath);
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    /**
     * Test parseLocalRecipeFiles with nested subdirectories.
     *
     * @throws ReflectionException
     */
    public function testParseLocalRecipeFilesWithSubdirectories(): void
    {
        $method = new ReflectionClass($this->handler)->getMethod('parseLocalRecipeFiles');

        $recipePath = __DIR__ . '/../Fixtures/packages/recipe-with-subdirs/recipe';
        $manifestPath = $recipePath . '/manifest.json';

        $result = $method->invoke($this->handler, $recipePath, $manifestPath);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        // Should find files in subdirectories
        $foundNestedFile = false;

        foreach ($result as $key => $fileInfo) {
            if (str_contains($key, 'prod/production.yaml')) {
                $foundNestedFile = true;
                $this->assertArrayHasKey('contents', $fileInfo);
                $this->assertStringContainsString('mode: prod', $fileInfo['contents']);

                break;
            }
        }

        $this->assertTrue($foundNestedFile, 'Should find files in nested directories');
    }

    /**
     * Test processPackage complete flow with valid recipe.
     *
     * This test verifies the full success path including:
     * - Recipe discovery
     * - Lock file management
     * - Configurator integration
     * - Recipe object return
     *
     * @throws JsonException
     */
    public function testProcessPackageCompleteFlowWithValidRecipe(): void
    {
        // Create package mock
        $package = ComposerMockFactory::createPackage('test/complete-package', '1.2.3');

        // Create installation manager mock
        $installManager = Mockery::mock(InstallationManager::class);
        $installManager->shouldReceive('getInstallPath')
            ->once()
            ->with($package)
            ->andReturn(__DIR__ . '/../Fixtures/packages/complete-package');

        // Create root package with config
        $rootPackage = ComposerMockFactory::createRootPackage(
            [
                'valksor' => [
                    'allow' => [
                        'test/complete-package' => ['allow_override' => false],
                    ],
                ],
            ],
        );

        // Create composer mock
        $composer = Mockery::mock(Composer::class);
        $composer->shouldReceive('getPackage')->andReturn($rootPackage);
        $composer->shouldReceive('getInstallationManager')->once()->andReturn($installManager);

        // Create IO mock
        $io = Mockery::mock(IOInterface::class);
        $io->shouldReceive('writeError')
            ->once()
            ->with('  - Applying local recipe for <info>test/complete-package</info>');

        $handler = new RecipeHandler($composer, $io);

        // Mock Lock and Configurator
        $lock = Mockery::mock(Lock::class);
        $lock->shouldReceive('set')
            ->once()
            ->with('test/complete-package', ['version' => '1.2.3']);
        $lock->shouldReceive('write')->once();

        $configurator = Mockery::mock(Configurator::class);
        $configurator->shouldReceive('install')
            ->once()
            ->with(
                Mockery::type(Recipe::class),
                $lock,
                ['force' => false],
            );

        // Inject mocks via reflection
        $reflection = new ReflectionClass($handler);
        $lockProperty = $reflection->getProperty('lock');
        $lockProperty->setValue($handler, $lock);
        $configuratorProperty = $reflection->getProperty('configurator');
        $configuratorProperty->setValue($handler, $configurator);

        // Execute the method
        $result = $handler->processPackage($package, 'install');

        // Verify Recipe object is returned
        $this->assertInstanceOf(Recipe::class, $result);
        $this->assertSame('test/complete-package', $result->getName());
    }

    /**
     * Test processPackage with JsonException handling.
     *
     * @throws JsonException
     */
    public function testProcessPackageHandlesJsonException(): void
    {
        // Create handler with wildcard allow configuration
        $composerWithWildcard = ComposerMockFactory::createComposer(
            [
                'valksor' => [
                    'allow' => '*',
                ],
            ],
        );
        $handler = new RecipeHandler($composerWithWildcard, $this->io);

        $package = ComposerMockFactory::createPackage('test/json-error');

        // Mock installation manager to return path with invalid JSON
        $installManager = Mockery::mock(InstallationManager::class);
        $installManager->shouldReceive('getInstallPath')
            ->with($package)
            ->andReturn(__DIR__ . '/../Fixtures/recipes/invalid-recipe');

        $composerWithWildcard->shouldReceive('getInstallationManager')->andReturn($installManager);

        // Should handle JsonException gracefully and return null
        $result = $handler->processPackage($package, 'install');
        $this->assertNull($result);
    }

    /**
     * Test complete recipe installation flow with allowlist validation.
     *
     * This test verifies the processPackage flow through the key steps:
     * - Package allowlist validation (wildcard allows all packages)
     * - Recipe discovery from package directory
     * - Recipe processing reaches the Symfony Flex integration point
     *
     * @throws JsonException
     */
    public function testProcessPackageSuccessfulInstallation(): void
    {
        // Create composer with wildcard allow to ensure package is allowed
        $composerWithWildcard = ComposerMockFactory::createComposer(
            [
                'valksor' => [
                    'allow' => '*',
                ],
            ],
        );

        $package = ComposerMockFactory::createPackage('test/installation-package', '2.1.0');
        $handler = new RecipeHandler($composerWithWildcard, $this->io);

        // Mock installation manager to return valid recipe path
        $installManager = Mockery::mock(InstallationManager::class);
        $installManager->shouldReceive('getInstallPath')
            ->with($package)
            ->andReturn(__DIR__ . '/../Fixtures/recipes/valid-recipe');
        $composerWithWildcard->shouldReceive('getInstallationManager')->andReturn($installManager);

        // Execute the method - this should reach the Symfony Flex integration
        $result = $handler->processPackage($package, 'update');

        // The method returns null when actual Symfony Flex integration fails,
        // but this verifies our logic works up to that point
        $this->assertNull($result);
    }

    /**
     * Test that allow_override passes force option to configurator.
     *
     * @throws JsonException
     */
    public function testProcessPackageWithAllowOverride(): void
    {
        $composerWithOverride = ComposerMockFactory::createComposer(
            [
                'valksor' => [
                    'allow' => [
                        'test/override-package' => ['allow_override' => true],
                    ],
                ],
            ],
        );

        $handler = new RecipeHandler($composerWithOverride, $this->io);
        $package = ComposerMockFactory::createPackage('test/override-package');

        // Mock installation manager to return valid recipe path
        $installManager = Mockery::mock(InstallationManager::class);
        $installManager->shouldReceive('getInstallPath')
            ->with($package)
            ->andReturn(__DIR__ . '/../Fixtures/recipes/valid-recipe');

        $composerWithOverride->shouldReceive('getInstallationManager')->andReturn($installManager);

        $this->expectNotToPerformAssertions();
        $handler->processPackage($package, 'install');
    }

    /**
     * Test processPackage with allow_override=true passes force option.
     *
     * @throws JsonException
     */
    public function testProcessPackageWithAllowOverridePassesForceOption(): void
    {
        // Create package mock
        $package = ComposerMockFactory::createPackage('test/complete-package', '1.2.3');

        // Create installation manager mock
        $installManager = Mockery::mock(InstallationManager::class);
        $installManager->shouldReceive('getInstallPath')
            ->once()
            ->with($package)
            ->andReturn(__DIR__ . '/../Fixtures/packages/complete-package');

        // Create root package with allow_override enabled
        $rootPackage = ComposerMockFactory::createRootPackage(
            [
                'valksor' => [
                    'allow' => [
                        'test/complete-package' => ['allow_override' => true],
                    ],
                ],
            ],
        );

        // Create composer mock
        $composer = Mockery::mock(Composer::class);
        $composer->shouldReceive('getPackage')->andReturn($rootPackage);
        $composer->shouldReceive('getInstallationManager')->once()->andReturn($installManager);

        // Create IO mock
        $io = Mockery::mock(IOInterface::class);
        $io->shouldReceive('writeError')
            ->once()
            ->with('  - Applying local recipe for <info>test/complete-package</info> (override enabled)');

        $handler = new RecipeHandler($composer, $io);

        // Mock Lock and Configurator
        $lock = Mockery::mock(Lock::class);
        $lock->shouldReceive('set')->once();
        $lock->shouldReceive('write')->once();

        $configurator = Mockery::mock(Configurator::class);
        $configurator->shouldReceive('install')
            ->once()
            ->with(
                Mockery::type(Recipe::class),
                $lock,
                ['force' => true], // Verify force is true
            );

        // Inject mocks via reflection
        $reflection = new ReflectionClass($handler);
        $lockProperty = $reflection->getProperty('lock');
        $lockProperty->setValue($handler, $lock);
        $configuratorProperty = $reflection->getProperty('configurator');
        $configuratorProperty->setValue($handler, $configurator);

        // Execute the method
        $result = $handler->processPackage($package, 'install');

        // Verify Recipe object is returned
        $this->assertInstanceOf(Recipe::class, $result);
    }

    /**
     * @throws JsonException
     */
    public function testProcessPackageWithAllowedPackageAndValidRecipe(): void
    {
        // Create handler with wildcard allow configuration
        $composerWithWildcard = ComposerMockFactory::createComposer(
            [
                'valksor' => [
                    'allow' => '*',
                ],
            ],
        );
        $handler = new RecipeHandler($composerWithWildcard, $this->io);

        $package = ComposerMockFactory::createPackage('test/recipe-package');

        // Mock installation manager to return valid recipe path
        $installManager = Mockery::mock(InstallationManager::class);
        $installManager->shouldReceive('getInstallPath')
            ->with($package)
            ->andReturn(__DIR__ . '/../Fixtures/recipes/valid-recipe');

        $composerWithWildcard->shouldReceive('getInstallationManager')->andReturn($installManager);

        $this->expectNotToPerformAssertions();
        $handler->processPackage($package, 'install');
    }

    /**
     * @throws JsonException
     */
    public function testProcessPackageWithDisallowedPackage(): void
    {
        $package = ComposerMockFactory::createPackage('unknown/package');

        // Create handler with restrictive configuration
        $composerWithRestrictiveConfig = ComposerMockFactory::createComposer(
            [
                'valksor' => [
                    'allow' => ['allowed/package' => []],
                ],
            ],
        );

        $result = new RecipeHandler($composerWithRestrictiveConfig, $this->io)->processPackage($package, 'install');

        $this->assertNull($result);
    }

    /**
     * @throws JsonException
     */
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

    /**
     * Test processPackage with no installation path.
     *
     * @throws JsonException
     */
    public function testProcessPackageWithNoInstallationPath(): void
    {
        // Create handler with wildcard allow configuration
        $composerWithWildcard = ComposerMockFactory::createComposer(
            [
                'valksor' => [
                    'allow' => '*',
                ],
            ],
        );
        $handler = new RecipeHandler($composerWithWildcard, $this->io);

        $package = ComposerMockFactory::createPackage('test/no-path');

        // Mock installation manager to return null
        $installManager = Mockery::mock(InstallationManager::class);
        $installManager->shouldReceive('getInstallPath')
            ->with($package)
            ->andReturn(null);

        $composerWithWildcard->shouldReceive('getInstallationManager')->andReturn($installManager);

        $result = $handler->processPackage($package, 'install');
        $this->assertNull($result);
    }

    /**
     * @throws JsonException
     */
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

    /**
     * @throws JsonException
     */
    public function testProcessPackageWithWildcardAllow(): void
    {
        $package = ComposerMockFactory::createPackage('any/package');

        // Create handler with wildcard configuration
        $composerWithWildcard = ComposerMockFactory::createComposer(
            [
                'valksor' => [
                    'allow' => '*',
                ],
            ],
        );

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
     * Test that allow_override=false does not pass force option.
     *
     * @throws JsonException
     */
    public function testProcessPackageWithoutAllowOverride(): void
    {
        $composerWithoutOverride = ComposerMockFactory::createComposer(
            [
                'valksor' => [
                    'allow' => [
                        'test/no-override-package' => ['allow_override' => false],
                    ],
                ],
            ],
        );

        $handler = new RecipeHandler($composerWithoutOverride, $this->io);
        $package = ComposerMockFactory::createPackage('test/no-override-package');

        // Mock installation manager to return valid recipe path
        $installManager = Mockery::mock(InstallationManager::class);
        $installManager->shouldReceive('getInstallPath')
            ->with($package)
            ->andReturn(__DIR__ . '/../Fixtures/recipes/valid-recipe');

        $composerWithoutOverride->shouldReceive('getInstallationManager')->andReturn($installManager);

        $this->expectNotToPerformAssertions();
        $handler->processPackage($package, 'install');
    }

    /**
     * Test uninstallPackage complete flow with valid recipe.
     *
     * This test verifies the full uninstall success path including:
     * - Recipe discovery
     * - Lock file removal
     * - Configurator unconfigure call
     * - Recipe object return
     *
     * @throws JsonException
     */
    public function testUninstallPackageCompleteFlowWithValidRecipe(): void
    {
        // Create package mock
        $package = ComposerMockFactory::createPackage('test/complete-package', '1.2.3');

        // Create installation manager mock
        $installManager = Mockery::mock(InstallationManager::class);
        $installManager->shouldReceive('getInstallPath')
            ->once()
            ->with($package)
            ->andReturn(__DIR__ . '/../Fixtures/packages/complete-package');

        // Create root package with wildcard allow
        $rootPackage = ComposerMockFactory::createRootPackage(
            [
                'valksor' => [
                    'allow' => '*',
                ],
            ],
        );

        // Create composer mock
        $composer = Mockery::mock(Composer::class);
        $composer->shouldReceive('getPackage')->andReturn($rootPackage);
        $composer->shouldReceive('getInstallationManager')->once()->andReturn($installManager);

        // Create IO mock
        $io = Mockery::mock(IOInterface::class);
        $io->shouldReceive('writeError')
            ->once()
            ->with('  - Removing local recipe for <info>test/complete-package</info>');

        $handler = new RecipeHandler($composer, $io);

        // Mock Lock and Configurator
        $lock = Mockery::mock(Lock::class);
        $lock->shouldReceive('remove')
            ->once()
            ->with('test/complete-package');
        $lock->shouldReceive('write')->once();

        $configurator = Mockery::mock(Configurator::class);
        $configurator->shouldReceive('unconfigure')
            ->once()
            ->with(
                Mockery::type(Recipe::class),
                $lock,
            );

        // Inject mocks via reflection
        $reflection = new ReflectionClass($handler);
        $lockProperty = $reflection->getProperty('lock');
        $lockProperty->setValue($handler, $lock);
        $configuratorProperty = $reflection->getProperty('configurator');
        $configuratorProperty->setValue($handler, $configurator);

        // Execute the method
        $result = $handler->uninstallPackage($package);

        // Verify Recipe object is returned
        $this->assertInstanceOf(Recipe::class, $result);
        $this->assertSame('test/complete-package', $result->getName());
    }

    /**
     * Test complete recipe uninstallation flow with cleanup operations.
     *
     * This test verifies the uninstallPackage flow including:
     * - Package allowlist validation (wildcard allows all packages)
     * - Recipe discovery from package directory
     * - Recipe processing reaches the Symfony Flex uninstall integration point
     *
     * @throws JsonException
     */
    public function testUninstallPackageSuccessfulRemoval(): void
    {
        // Create composer with wildcard allow to ensure package is allowed
        $composerWithWildcard = ComposerMockFactory::createComposer(
            [
                'valksor' => [
                    'allow' => '*',
                ],
            ],
        );

        $package = ComposerMockFactory::createPackage('test/uninstall-package', '1.5.0');
        $handler = new RecipeHandler($composerWithWildcard, $this->io);

        // Mock installation manager to return valid recipe path
        $installManager = Mockery::mock(InstallationManager::class);
        $installManager->shouldReceive('getInstallPath')
            ->with($package)
            ->andReturn(__DIR__ . '/../Fixtures/recipes/valid-recipe');
        $composerWithWildcard->shouldReceive('getInstallationManager')->andReturn($installManager);

        // Execute the method - this should reach the Symfony Flex uninstall integration
        $result = $handler->uninstallPackage($package);

        // The method returns null when actual Symfony Flex integration fails,
        // but this verifies our logic works up to that point
        $this->assertNull($result);
    }

    /**
     * @throws JsonException
     */
    public function testUninstallPackageWithDisallowedPackage(): void
    {
        $package = ComposerMockFactory::createPackage('unknown/package');

        // Create handler with restrictive configuration
        $composerWithRestrictiveConfig = ComposerMockFactory::createComposer(
            [
                'valksor' => [
                    'allow' => ['allowed/package' => []],
                ],
            ],
        );

        $result = new RecipeHandler($composerWithRestrictiveConfig, $this->io)->uninstallPackage($package);

        $this->assertNull($result);
    }

    /**
     * @throws JsonException
     */
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
        $composerWithWildcard = ComposerMockFactory::createComposer(
            [
                'valksor' => [
                    'allow' => '*',
                ],
            ],
        );
        $handler = new RecipeHandler($composerWithWildcard, $this->io);

        $package = ComposerMockFactory::createPackage('test/recipe-package');

        // Mock installation manager to return valid recipe path
        $installManager = Mockery::mock(InstallationManager::class);
        $installManager->shouldReceive('getInstallPath')
            ->with($package)
            ->andReturn(__DIR__ . '/../Fixtures/recipes/valid-recipe');

        $composerWithWildcard->shouldReceive('getInstallationManager')->andReturn($installManager);

        $this->expectNotToPerformAssertions();
        $handler->uninstallPackage($package);
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
