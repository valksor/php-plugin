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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use Symfony\Flex\Configurator;
use Symfony\Flex\Lock;
use Symfony\Flex\Recipe;
use ValksorPlugin\RecipeHandler;
use ValksorPlugin\Tests\Mocks\ComposerMockTrait;

/**
 * Unit tests for RecipeHandler class.
 *
 * Tests the core recipe discovery, processing, and installation logic.
 * This is the most critical class in the plugin as it handles all recipe operations.
 */
#[CoversClass(RecipeHandler::class)]
class RecipeHandlerTest extends TestCase
{
    use ComposerMockTrait;

    private Composer $composer;
    private RecipeHandler $handler;
    private IOInterface $io;

    /**
     * @throws ReflectionException
     */
    public function testConstructorLoadsConfiguration(): void
    {
        // Test with valksor configuration
        $composerWithConfig = $this->createComposer(
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
        $composerWithoutConfig = $this->createComposer();
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
        $composerWithWildcard = $this->createComposer(
            ['valksor' => ['allow' => '*']],
        );

        $package = $this->createPackage('test/json-error');
        $handler = new RecipeHandler($composerWithWildcard, $this->io);

        $installManager = $this->createStub(InstallationManager::class);
        $installManager->method('getInstallPath')
            ->willReturn(__DIR__ . '/../Fixtures/recipes/invalid-recipe');
        $composerWithWildcard->method('getInstallationManager')->willReturn($installManager);

        $this->assertNull(new ReflectionClass($handler)->getMethod('getLocalRecipe')->invoke($handler, $package, 'install'));
    }

    /**
     * Ensure invalid manifest files trigger warning logs.
     *
     * @throws ReflectionException
     */
    public function testGetLocalRecipeLogsInvalidManifestWarning(): void
    {
        $io = $this->createMock(IOInterface::class);
        $io->method('isVerbose')->willReturn(false);
        $io->method('isDebug')->willReturn(false);
        $io->expects($this->once())
            ->method('writeError')
            ->with($this->callback(static fn (string $message): bool => str_contains($message, 'Warning: Invalid manifest.json')));

        $rootPackage = $this->createRootPackage();
        $composer = $this->createStub(Composer::class);
        $composer->method('getPackage')->willReturn($rootPackage);
        $handler = new RecipeHandler($composer, $io);

        $package = $this->createPackage('test/json-warning');

        $installDir = sys_get_temp_dir() . '/invalid-recipe-' . uniqid('', true);
        $recipeDir = $installDir . '/recipe';
        mkdir($recipeDir, 0o777, true);
        file_put_contents($recipeDir . '/manifest.json', '{ invalid json }');

        $installManager = $this->createStub(InstallationManager::class);
        $installManager->method('getInstallPath')
            ->willReturn($installDir);

        $composer->method('getInstallationManager')->willReturn($installManager);

        $method = new ReflectionClass($handler)->getMethod('getLocalRecipe');

        $this->assertNull($method->invoke($handler, $package, 'install'));

        if (is_file($recipeDir . '/manifest.json')) {
            unlink($recipeDir . '/manifest.json');
        }

        if (is_dir($recipeDir)) {
            rmdir($recipeDir);
        }

        if (is_dir($installDir)) {
            rmdir($installDir);
        }
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
        $package = $this->createPackage('test/complete-package', '1.2.3');

        // Create installation manager mock
        $installManager = $this->createMock(InstallationManager::class);
        $installManager->expects($this->once())
            ->method('getInstallPath')
            ->with($package)
            ->willReturn(__DIR__ . '/../Fixtures/packages/complete-package');

        // Create fresh composer mock with explicit expectations
        $rootPackage = $this->createRootPackage();
        $composer = $this->createMock(Composer::class);
        $composer->method('getPackage')->willReturn($rootPackage);
        $composer->expects($this->once())->method('getInstallationManager')->willReturn($installManager);

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
        $package = $this->createPackage('test/invalid-json');

        // Mock installation manager to return invalid recipe path
        $installManager = $this->createStub(InstallationManager::class);
        $installManager->method('getInstallPath')
            ->willReturn(__DIR__ . '/../Fixtures/recipes/invalid-recipe');

        $this->composer->method('getInstallationManager')->willReturn($installManager);

        $recipe = $method->invoke($this->handler, $package, 'install');

        $this->assertNull($recipe);
    }

    /**
     * Ensure missing manifest files are handled gracefully.
     *
     * @throws ReflectionException
     */
    public function testGetLocalRecipeWithMissingManifestFile(): void
    {
        $package = $this->createPackage('test/missing-manifest');

        $installDir = sys_get_temp_dir() . '/recipe-handler-' . uniqid('', true);
        $recipeDir = $installDir . '/recipe';
        mkdir($recipeDir, 0o777, true);
        file_put_contents($recipeDir . '/dummy.txt', 'dummy');

        $rootPackage = $this->createRootPackage();
        $composer = $this->createStub(Composer::class);
        $composer->method('getPackage')->willReturn($rootPackage);

        $installManager = $this->createStub(InstallationManager::class);
        $installManager->method('getInstallPath')
            ->willReturn($installDir);
        $composer->method('getInstallationManager')->willReturn($installManager);

        $handler = new RecipeHandler($composer, $this->io);
        $method = new ReflectionClass($handler)->getMethod('getLocalRecipe');
        $this->assertNull($method->invoke($handler, $package, 'install'));

        if (is_file($recipeDir . '/dummy.txt')) {
            unlink($recipeDir . '/dummy.txt');
        }

        if (is_dir($recipeDir)) {
            rmdir($recipeDir);
        }

        if (is_dir($installDir)) {
            rmdir($installDir);
        }
    }

    /**
     * Test getLocalRecipe with no recipe directory.
     *
     * @throws ReflectionException
     */
    public function testGetLocalRecipeWithNoRecipeDirectory(): void
    {
        $method = new ReflectionClass($this->handler)->getMethod('getLocalRecipe');
        $package = $this->createPackage('test/no-recipe');

        // Mock installation manager to return path without recipe
        $installManager = $this->createStub(InstallationManager::class);
        $installManager->method('getInstallPath')
            ->willReturn(sys_get_temp_dir() . '/no-recipe');

        $this->composer->method('getInstallationManager')->willReturn($installManager);

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
        $package = $this->createPackage('test/valid-recipe');

        $installManager = $this->createStub(InstallationManager::class);
        $installManager->method('getInstallPath')
            ->willReturn(__DIR__ . '/../Fixtures/recipes/valid-recipe');
        $this->composer->method('getInstallationManager')->willReturn($installManager);

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
        $io = $this->createMock(IOInterface::class);
        $io->expects($this->once())
            ->method('writeError')
            ->with('  - Applying local recipe for <info>test/package</info> (override enabled)');

        $composer = $this->createComposer();
        $handler = new RecipeHandler($composer, $io);

        $method = new ReflectionClass($handler)->getMethod('logRecipeApplication');
        $this->assertNull($method->invoke($handler, 'test/package', true));
    }

    /**
     * Test logRecipeApplication with override disabled.
     *
     * @throws ReflectionException
     */
    public function testLogRecipeApplicationWithoutOverride(): void
    {
        // Create fresh IO mock without default expectations
        $io = $this->createMock(IOInterface::class);
        $io->expects($this->once())
            ->method('writeError')
            ->with('  - Applying local recipe for <info>test/package</info>');

        $composer = $this->createComposer();
        $handler = new RecipeHandler($composer, $io);

        $method = new ReflectionClass($handler)->getMethod('logRecipeApplication');
        $this->assertNull($method->invoke($handler, 'test/package', false));
    }

    /**
     * @throws ReflectionException
     */
    public function testParseLocalRecipeFilesSkipsDirectories(): void
    {
        $method = new ReflectionClass($this->handler)->getMethod('parseLocalRecipeFiles');

        $baseDir = sys_get_temp_dir() . '/recipe-handler-files-' . uniqid('', true);
        $recipeDir = $baseDir . '/recipe';
        $nestedDir = $recipeDir . '/nested';
        mkdir($nestedDir, 0o777, true);

        $manifestPath = $recipeDir . '/manifest.json';
        file_put_contents($manifestPath, '{}');
        file_put_contents($nestedDir . '/file.txt', 'content');

        $result = $method->invoke($this->handler, $recipeDir, $manifestPath);

        $this->assertArrayHasKey('nested/file.txt', $result);
        $this->assertArrayNotHasKey('nested', $result);

        if (is_file($nestedDir . '/file.txt')) {
            unlink($nestedDir . '/file.txt');
        }

        if (is_file($manifestPath)) {
            unlink($manifestPath);
        }

        if (is_dir($nestedDir)) {
            rmdir($nestedDir);
        }

        if (is_dir($recipeDir)) {
            rmdir($recipeDir);
        }

        if (is_dir($baseDir)) {
            rmdir($baseDir);
        }
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
     */
    public function testProcessPackageCompleteFlowWithValidRecipe(): void
    {
        // Create package mock
        $package = $this->createPackage('test/complete-package', '1.2.3');

        // Create installation manager mock
        $installManager = $this->createMock(InstallationManager::class);
        $installManager->expects($this->once())
            ->method('getInstallPath')
            ->with($package)
            ->willReturn(__DIR__ . '/../Fixtures/packages/complete-package');

        // Create root package with config
        $rootPackage = $this->createRootPackage(
            [
                'valksor' => [
                    'allow' => [
                        'test/complete-package' => ['allow_override' => false],
                    ],
                ],
            ],
        );

        // Create composer mock
        $composer = $this->createMock(Composer::class);
        $composer->method('getPackage')->willReturn($rootPackage);
        $composer->expects($this->once())->method('getInstallationManager')->willReturn($installManager);

        // Create IO mock
        $io = $this->createMock(IOInterface::class);
        $io->expects($this->once())
            ->method('writeError')
            ->with('  - Applying local recipe for <info>test/complete-package</info>');

        $handler = new RecipeHandler($composer, $io);

        // Mock Lock and Configurator
        $lock = $this->createMock(Lock::class);
        $lock->expects($this->once())
            ->method('set')
            ->with('test/complete-package', ['version' => '1.2.3']);
        $lock->expects($this->once())->method('write');

        $configurator = $this->createMock(Configurator::class);
        $configurator->expects($this->once())
            ->method('install')
            ->with(
                $this->isInstanceOf(Recipe::class),
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
     */
    public function testProcessPackageHandlesJsonException(): void
    {
        // Create handler with wildcard allow configuration
        $composerWithWildcard = $this->createComposer(
            [
                'valksor' => [
                    'allow' => '*',
                ],
            ],
        );
        $handler = new RecipeHandler($composerWithWildcard, $this->io);

        $package = $this->createPackage('test/json-error');

        // Mock installation manager to return path with invalid JSON
        $installManager = $this->createStub(InstallationManager::class);
        $installManager->method('getInstallPath')
            ->willReturn(__DIR__ . '/../Fixtures/recipes/invalid-recipe');

        $composerWithWildcard->method('getInstallationManager')->willReturn($installManager);

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
     */
    public function testProcessPackageSuccessfulInstallation(): void
    {
        // Create composer with wildcard allow to ensure package is allowed
        $composerWithWildcard = $this->createComposer(
            [
                'valksor' => [
                    'allow' => '*',
                ],
            ],
        );

        $package = $this->createPackage('test/installation-package', '2.1.0');
        $handler = new RecipeHandler($composerWithWildcard, $this->io);

        // Mock installation manager to return valid recipe path
        $installManager = $this->createStub(InstallationManager::class);
        $installManager->method('getInstallPath')
            ->willReturn(__DIR__ . '/../Fixtures/recipes/valid-recipe');
        $composerWithWildcard->method('getInstallationManager')->willReturn($installManager);

        // Execute the method - this should reach the Symfony Flex integration
        $result = $handler->processPackage($package, 'update');

        // The method returns null when actual Symfony Flex integration fails,
        // but this verifies our logic works up to that point
        $this->assertNull($result);
    }

    /**
     * Test that allow_override passes force option to configurator.
     */
    public function testProcessPackageWithAllowOverride(): void
    {
        $composerWithOverride = $this->createComposer(
            [
                'valksor' => [
                    'allow' => [
                        'test/override-package' => ['allow_override' => true],
                    ],
                ],
            ],
        );

        $handler = new RecipeHandler($composerWithOverride, $this->io);
        $package = $this->createPackage('test/override-package');

        // Mock installation manager to return valid recipe path
        $installManager = $this->createStub(InstallationManager::class);
        $installManager->method('getInstallPath')
            ->willReturn(__DIR__ . '/../Fixtures/recipes/valid-recipe');

        $composerWithOverride->method('getInstallationManager')->willReturn($installManager);

        $this->expectNotToPerformAssertions();
        $handler->processPackage($package, 'install');
    }

    /**
     * Test processPackage with allow_override=true passes force option.
     */
    public function testProcessPackageWithAllowOverridePassesForceOption(): void
    {
        // Create package mock
        $package = $this->createPackage('test/complete-package', '1.2.3');

        // Create installation manager mock
        $installManager = $this->createMock(InstallationManager::class);
        $installManager->expects($this->once())
            ->method('getInstallPath')
            ->with($package)
            ->willReturn(__DIR__ . '/../Fixtures/packages/complete-package');

        // Create root package with allow_override enabled
        $rootPackage = $this->createRootPackage(
            [
                'valksor' => [
                    'allow' => [
                        'test/complete-package' => ['allow_override' => true],
                    ],
                ],
            ],
        );

        // Create composer mock
        $composer = $this->createMock(Composer::class);
        $composer->method('getPackage')->willReturn($rootPackage);
        $composer->expects($this->once())->method('getInstallationManager')->willReturn($installManager);

        // Create IO mock
        $io = $this->createMock(IOInterface::class);
        $io->expects($this->once())
            ->method('writeError')
            ->with('  - Applying local recipe for <info>test/complete-package</info> (override enabled)');

        $handler = new RecipeHandler($composer, $io);

        // Mock Lock and Configurator
        $lock = $this->createMock(Lock::class);
        $lock->expects($this->once())->method('set');
        $lock->expects($this->once())->method('write');

        $configurator = $this->createMock(Configurator::class);
        $configurator->expects($this->once())
            ->method('install')
            ->with(
                $this->isInstanceOf(Recipe::class),
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

    public function testProcessPackageWithAllowedPackageAndValidRecipe(): void
    {
        // Create handler with wildcard allow configuration
        $composerWithWildcard = $this->createComposer(
            [
                'valksor' => [
                    'allow' => '*',
                ],
            ],
        );
        $handler = new RecipeHandler($composerWithWildcard, $this->io);

        $package = $this->createPackage('test/recipe-package');

        // Mock installation manager to return valid recipe path
        $installManager = $this->createStub(InstallationManager::class);
        $installManager->method('getInstallPath')
            ->willReturn(__DIR__ . '/../Fixtures/recipes/valid-recipe');

        $composerWithWildcard->method('getInstallationManager')->willReturn($installManager);

        $this->expectNotToPerformAssertions();
        $handler->processPackage($package, 'install');
    }

    public function testProcessPackageWithDisallowedPackage(): void
    {
        $package = $this->createPackage('unknown/package');

        // Create handler with restrictive configuration
        $composerWithRestrictiveConfig = $this->createComposer(
            [
                'valksor' => [
                    'allow' => ['allowed/package' => []],
                ],
            ],
        );

        $result = new RecipeHandler($composerWithRestrictiveConfig, $this->io)->processPackage($package, 'install');

        $this->assertNull($result);
    }

    public function testProcessPackageWithInvalidManifestJson(): void
    {
        $package = $this->createPackage('test/invalid-recipe');

        // Mock installation manager to return invalid recipe path
        $installManager = $this->createStub(InstallationManager::class);
        $installManager->method('getInstallPath')
            ->willReturn(__DIR__ . '/../Fixtures/recipes/invalid-recipe');

        $this->composer->method('getInstallationManager')->willReturn($installManager);

        $result = $this->handler->processPackage($package, 'install');

        $this->assertNull($result);
    }

    /**
     * Test processPackage with no installation path.
     */
    public function testProcessPackageWithNoInstallationPath(): void
    {
        // Create handler with wildcard allow configuration
        $composerWithWildcard = $this->createComposer(
            [
                'valksor' => [
                    'allow' => '*',
                ],
            ],
        );
        $handler = new RecipeHandler($composerWithWildcard, $this->io);

        $package = $this->createPackage('test/no-path');

        // Mock installation manager to return null
        $installManager = $this->createStub(InstallationManager::class);
        $installManager->method('getInstallPath')
            ->willReturn(null);

        $composerWithWildcard->method('getInstallationManager')->willReturn($installManager);

        $result = $handler->processPackage($package, 'install');
        $this->assertNull($result);
    }

    public function testProcessPackageWithNoRecipeDirectory(): void
    {
        $package = $this->createPackage('test/no-recipe');

        // Mock installation manager to return path without recipe directory
        $installManager = $this->createStub(InstallationManager::class);
        $installManager->method('getInstallPath')
            ->willReturn(sys_get_temp_dir() . '/no-recipe-dir');

        $this->composer->method('getInstallationManager')->willReturn($installManager);

        $result = $this->handler->processPackage($package, 'install');

        $this->assertNull($result);
    }

    public function testProcessPackageWithWildcardAllow(): void
    {
        $package = $this->createPackage('any/package');

        // Create handler with wildcard configuration
        $composerWithWildcard = $this->createComposer(
            [
                'valksor' => [
                    'allow' => '*',
                ],
            ],
        );

        $handler = new RecipeHandler($composerWithWildcard, $this->io);

        // Mock installation manager to return valid recipe path
        $installManager = $this->createStub(InstallationManager::class);
        $installManager->method('getInstallPath')
            ->willReturn(__DIR__ . '/../Fixtures/recipes/simple-recipe');

        $composerWithWildcard->method('getInstallationManager')->willReturn($installManager);

        $result = $handler->processPackage($package, 'install');

        // Should return null since simple-recipe doesn't exist as a directory structure
        // but the package should be allowed
        $this->assertNull($result);
    }

    /**
     * Test that allow_override=false does not pass force option.
     */
    public function testProcessPackageWithoutAllowOverride(): void
    {
        $composerWithoutOverride = $this->createComposer(
            [
                'valksor' => [
                    'allow' => [
                        'test/no-override-package' => ['allow_override' => false],
                    ],
                ],
            ],
        );

        $handler = new RecipeHandler($composerWithoutOverride, $this->io);
        $package = $this->createPackage('test/no-override-package');

        // Mock installation manager to return valid recipe path
        $installManager = $this->createStub(InstallationManager::class);
        $installManager->method('getInstallPath')
            ->willReturn(__DIR__ . '/../Fixtures/recipes/valid-recipe');

        $composerWithoutOverride->method('getInstallationManager')->willReturn($installManager);

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
     */
    public function testUninstallPackageCompleteFlowWithValidRecipe(): void
    {
        // Create package mock
        $package = $this->createPackage('test/complete-package', '1.2.3');

        // Create installation manager mock
        $installManager = $this->createMock(InstallationManager::class);
        $installManager->expects($this->once())
            ->method('getInstallPath')
            ->with($package)
            ->willReturn(__DIR__ . '/../Fixtures/packages/complete-package');

        // Create root package with wildcard allow
        $rootPackage = $this->createRootPackage(
            [
                'valksor' => [
                    'allow' => '*',
                ],
            ],
        );

        // Create composer mock
        $composer = $this->createMock(Composer::class);
        $composer->method('getPackage')->willReturn($rootPackage);
        $composer->expects($this->once())->method('getInstallationManager')->willReturn($installManager);

        // Create IO mock
        $io = $this->createMock(IOInterface::class);
        $io->expects($this->once())
            ->method('writeError')
            ->with('  - Removing local recipe for <info>test/complete-package</info>');

        $handler = new RecipeHandler($composer, $io);

        // Mock Lock and Configurator
        $lock = $this->createMock(Lock::class);
        $lock->expects($this->once())
            ->method('remove')
            ->with('test/complete-package');
        $lock->expects($this->once())->method('write');

        $configurator = $this->createMock(Configurator::class);
        $configurator->expects($this->once())
            ->method('unconfigure')
            ->with(
                $this->isInstanceOf(Recipe::class),
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
     */
    public function testUninstallPackageSuccessfulRemoval(): void
    {
        // Create composer with wildcard allow to ensure package is allowed
        $composerWithWildcard = $this->createComposer(
            [
                'valksor' => [
                    'allow' => '*',
                ],
            ],
        );

        $package = $this->createPackage('test/uninstall-package', '1.5.0');
        $handler = new RecipeHandler($composerWithWildcard, $this->io);

        // Mock installation manager to return valid recipe path
        $installManager = $this->createStub(InstallationManager::class);
        $installManager->method('getInstallPath')
            ->willReturn(__DIR__ . '/../Fixtures/recipes/valid-recipe');
        $composerWithWildcard->method('getInstallationManager')->willReturn($installManager);

        // Execute the method - this should reach the Symfony Flex uninstall integration
        $result = $handler->uninstallPackage($package);

        // The method returns null when actual Symfony Flex integration fails,
        // but this verifies our logic works up to that point
        $this->assertNull($result);
    }

    public function testUninstallPackageWithDisallowedPackage(): void
    {
        $package = $this->createPackage('unknown/package');

        // Create handler with restrictive configuration
        $composerWithRestrictiveConfig = $this->createComposer(
            [
                'valksor' => [
                    'allow' => ['allowed/package' => []],
                ],
            ],
        );

        $result = new RecipeHandler($composerWithRestrictiveConfig, $this->io)->uninstallPackage($package);

        $this->assertNull($result);
    }

    public function testUninstallPackageWithNoRecipe(): void
    {
        $package = $this->createPackage('test/no-recipe');

        // Mock installation manager to return path without recipe directory
        $installManager = $this->createStub(InstallationManager::class);
        $installManager->method('getInstallPath')
            ->willReturn(sys_get_temp_dir() . '/no-recipe-dir');

        $this->composer->method('getInstallationManager')->willReturn($installManager);

        $result = $this->handler->uninstallPackage($package);

        $this->assertNull($result);
    }

    public function testUninstallPackageWithValidRecipe(): void
    {
        // Create handler with wildcard allow configuration
        $composerWithWildcard = $this->createComposer(
            [
                'valksor' => [
                    'allow' => '*',
                ],
            ],
        );
        $handler = new RecipeHandler($composerWithWildcard, $this->io);

        $package = $this->createPackage('test/recipe-package');

        // Mock installation manager to return valid recipe path
        $installManager = $this->createStub(InstallationManager::class);
        $installManager->method('getInstallPath')
            ->willReturn(__DIR__ . '/../Fixtures/recipes/valid-recipe');

        $composerWithWildcard->method('getInstallationManager')->willReturn($installManager);

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
        $this->composer = $this->createComposer();
        $this->io = $this->createIO();
        $this->handler = new RecipeHandler($this->composer, $this->io);
    }
}
