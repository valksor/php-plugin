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

use Composer\Config;
use Composer\Installer\InstallationManager;
use Composer\IO\IOInterface;
use Composer\Package\Locker;
use Composer\Repository\LockArrayRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Flex\Recipe;
use ValksorPlugin\Command\ValksorRecipesInstallCommand;
use ValksorPlugin\RecipeHandler;
use ValksorPlugin\Tests\Mocks\ComposerMockTrait;

/**
 * Unit tests for ValksorRecipesInstallCommand class.
 *
 * Tests the install command configuration and basic functionality.
 */
#[CoversClass(ValksorRecipesInstallCommand::class)]
#[CoversClass(RecipeHandler::class)]
class ValksorRecipesInstallCommandTest extends TestCase
{
    use ComposerMockTrait;

    private ValksorRecipesInstallCommand $command;
    private InputInterface $input;
    private OutputInterface $output;

    public function testCommandNameAndDescription(): void
    {
        $this->assertSame('valksor:install', $this->command->getName());
        $this->assertStringContainsString('Applies local recipes', $this->command->getDescription());
        $this->assertStringContainsString('all or a specific', $this->command->getDescription());
    }

    public function testConfigure(): void
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasArgument('package'));
        $this->assertSame('valksor:install', $this->command->getName());
        $this->assertStringContainsString('Applies local recipes', $this->command->getDescription());

        $argument = $definition->getArgument('package');
        $this->assertFalse($argument->isRequired(), 'package argument should be optional');
        $this->assertStringContainsString('Package name to install', $argument->getDescription());
    }

    public function testExecuteMethodExists(): void
    {
        $reflection = new ReflectionClass($this->command);
        $this->assertTrue($reflection->hasMethod('execute'));
        $this->assertSame('execute', $reflection->getMethod('execute')->getName());
    }

    /**
     * @throws ReflectionException
     */
    public function testExecuteMethodSignature(): void
    {
        $method = new ReflectionClass($this->command)->getMethod('execute');

        $this->assertSame('execute', $method->getName());
        $this->assertSame(2, $method->getNumberOfParameters());
        $this->assertSame('int', $method->getReturnType()?->getName());
    }

    /**
     * @throws ReflectionException
     */
    public function testExecuteProcessesAllPackagesWithNoRecipesMessage(): void
    {
        $packageOne = $this->createPackage('test/package-one');
        $packageTwo = $this->createPackage('test/package-two');

        $command = new ValksorRecipesInstallCommand();
        $composer = $this->createComposer();

        $locker = $this->createStub(Locker::class);
        $locker->method('isLocked')->willReturn(true);
        $repository = $this->createStub(LockArrayRepository::class);
        $repository->method('getPackages')->willReturn([$packageOne, $packageTwo]);
        $locker->method('getLockedRepository')->willReturn($repository);
        $composer->method('getLocker')->willReturn($locker);
        $composer->method('getConfig')->willReturn($this->createStub(Config::class));

        $application = $this->createStub(Application::class);
        $application->method('getHelperSet')->willReturn($this->createStub(HelperSet::class));

        $io = $this->createMock(IOInterface::class);
        $io->method('isVerbose')->willReturn(false);
        $io->method('isDebug')->willReturn(false);
        $io->expects($this->exactly(2))
            ->method('writeError')
            ->with($this->logicalOr(
                '<info>Searching for local recipes to apply...</info>',
                '<info>No local recipes found to apply.</info>',
            ));

        $input = $this->createStub(InputInterface::class);
        $output = $this->createStub(OutputInterface::class);
        $input->method('getArgument')->willReturn(null);

        $command->setApplication($application);
        $command->setComposer($composer);
        $command->setIO($io);

        $result = new ReflectionClass($command)->getMethod('execute')->invoke($command, $input, $output);

        $this->assertSame(0, $result);
    }

    /**
     * Test execute method with no lock file.
     *
     * @throws ReflectionException
     */
    public function testExecuteWithNoLockFile(): void
    {
        $composer = $this->createComposer();
        $locker = $this->createLocker(false);
        $composer->method('getLocker')->willReturn($locker);
        $composer->method('getConfig')->willReturn($this->createStub(Config::class));

        $application = $this->createStub(Application::class);
        $application->method('getHelperSet')->willReturn($this->createStub(HelperSet::class));
        $this->command->setApplication($application);
        $this->command->setComposer($composer);

        $this->input = $this->createStub(InputInterface::class);
        $this->output = $this->createStub(OutputInterface::class);

        $this->input->method('getArgument')->willReturn(null);

        $io = $this->createIO();
        $this->command->setIO($io);

        $result = new ReflectionClass($this->command)->getMethod('execute')->invoke($this->command, $this->input, $this->output);
        $this->assertSame(1, $result);
    }

    /**
     * Test execute method with no package argument (process all packages).
     *
     * @throws ReflectionException
     */
    public function testExecuteWithNoPackageArgument(): void
    {
        $composer = $this->createComposer();
        $package1 = $this->createPackage('test/package1');
        $package2 = $this->createPackage('test/package2');
        // Create locker mock directly to control repository type
        $locker = $this->createStub(Locker::class);
        $locker->method('isLocked')->willReturn(true);
        $repository = $this->createStub(LockArrayRepository::class);
        $repository->method('getPackages')->willReturn([$package1, $package2]);
        $locker->method('getLockedRepository')->willReturn($repository);
        $composer->method('getLocker')->willReturn($locker);
        $composer->method('getConfig')->willReturn($this->createStub(Config::class));

        $application = $this->createStub(Application::class);
        $application->method('getHelperSet')->willReturn($this->createStub(HelperSet::class));
        $this->command->setApplication($application);
        $this->command->setComposer($composer);

        $this->input = $this->createStub(InputInterface::class);
        $this->output = $this->createStub(OutputInterface::class);

        $this->input->method('getArgument')->willReturn(null);

        $io = $this->createIO();
        $this->command->setIO($io);

        // Mock installation manager to return path without recipes for packages
        $installManager = $this->createStub(InstallationManager::class);
        $installManager->method('getInstallPath')
            ->willReturnMap([
                [$package1, sys_get_temp_dir() . '/package1'],
                [$package2, sys_get_temp_dir() . '/package2'],
            ]);

        $composer->method('getInstallationManager')->willReturn($installManager);

        $result = new ReflectionClass($this->command)->getMethod('execute')->invoke($this->command, $this->input, $this->output);
        $this->assertSame(0, $result);
    }

    /**
     * Test execute method with specific package found but no recipe available.
     *
     * @throws ReflectionException
     */
    public function testExecuteWithSpecificPackageNoRecipe(): void
    {
        $composer = $this->createComposer();
        $package = $this->createPackage('test/no-recipe-package');
        // Create locker mock directly to control repository type
        $locker = $this->createStub(Locker::class);
        $locker->method('isLocked')->willReturn(true);
        $repository = $this->createStub(LockArrayRepository::class);
        $repository->method('getPackages')->willReturn([$package]);
        $locker->method('getLockedRepository')->willReturn($repository);
        $composer->method('getLocker')->willReturn($locker);
        $composer->method('getConfig')->willReturn($this->createStub(Config::class));

        $application = $this->createStub(Application::class);
        $application->method('getHelperSet')->willReturn($this->createStub(HelperSet::class));
        $this->command->setApplication($application);
        $this->command->setComposer($composer);

        $this->input = $this->createStub(InputInterface::class);
        $this->output = $this->createStub(OutputInterface::class);

        $this->input->method('getArgument')->willReturn('test/no-recipe-package');

        $io = $this->createIO();
        $this->command->setIO($io);

        // Mock installation manager to return path without recipe
        $installManager = $this->createInstallationManager(sys_get_temp_dir() . '/no-recipe');
        $composer->method('getInstallationManager')->willReturn($installManager);

        $result = new ReflectionClass($this->command)->getMethod('execute')->invoke($this->command, $this->input, $this->output);
        $this->assertSame(0, $result);
    }

    /**
     * Test execute method with specific package not found.
     *
     * @throws ReflectionException
     */
    public function testExecuteWithSpecificPackageNotFound(): void
    {
        $composer = $this->createComposer();
        $package = $this->createPackage('other/package');
        // Create locker mock directly to control repository type
        $locker = $this->createStub(Locker::class);
        $locker->method('isLocked')->willReturn(true);
        $repository = $this->createStub(LockArrayRepository::class);
        $repository->method('getPackages')->willReturn([$package]);
        $locker->method('getLockedRepository')->willReturn($repository);
        $composer->method('getLocker')->willReturn($locker);
        $composer->method('getConfig')->willReturn($this->createStub(Config::class));

        $application = $this->createStub(Application::class);
        $application->method('getHelperSet')->willReturn($this->createStub(HelperSet::class));
        $this->command->setApplication($application);
        $this->command->setComposer($composer);

        $this->input = $this->createStub(InputInterface::class);
        $this->output = $this->createStub(OutputInterface::class);

        $this->input->method('getArgument')->willReturn('nonexistent/package');

        $io = $this->createIO();
        $this->command->setIO($io);

        $result = new ReflectionClass($this->command)->getMethod('execute')->invoke($this->command, $this->input, $this->output);
        $this->assertSame(0, $result);
    }

    /**
     * @throws ReflectionException
     */
    public function testExecuteWithSpecificPackageReturnsFailureCode(): void
    {
        $package = $this->createPackage('test/successful-package');
        $command = new ValksorRecipesInstallCommand();
        $composer = $this->createComposer();
        $locker = $this->createStub(Locker::class);
        $locker->method('isLocked')->willReturn(true);
        $repository = $this->createStub(LockArrayRepository::class);
        $repository->method('getPackages')->willReturn([$package]);
        $locker->method('getLockedRepository')->willReturn($repository);
        $composer->method('getLocker')->willReturn($locker);
        $composer->method('getConfig')->willReturn($this->createStub(Config::class));

        $application = $this->createStub(Application::class);
        $application->method('getHelperSet')->willReturn($this->createStub(HelperSet::class));

        $io = $this->createMock(IOInterface::class);
        $io->method('isVerbose')->willReturn(false);
        $io->method('isDebug')->willReturn(false);
        $io->expects($this->exactly(2))
            ->method('writeError')
            ->with($this->logicalOr(
                '<info>Searching for local recipe to apply for test/successful-package...</info>',
                '<comment>No local recipe found for test/successful-package.</comment>',
            ));

        $input = $this->createStub(InputInterface::class);
        $output = $this->createStub(OutputInterface::class);
        $input->method('getArgument')->willReturn('test/successful-package');

        $command->setApplication($application);
        $command->setComposer($composer);
        $command->setIO($io);

        $result = new ReflectionClass($command)->getMethod('execute')->invoke($command, $input, $output);

        $this->assertSame(0, $result);
    }

    /**
     * Test execute method with specific package found and recipe applied successfully.
     *
     * @throws ReflectionException
     */
    public function testExecuteWithSpecificPackageSuccess(): void
    {
        $package = $this->createPackage('test/success-package');

        // Mock RecipeHandler to return a Recipe object (success scenario)
        $handler = $this->createMock(RecipeHandler::class);
        $recipeMock = $this->createStub(Recipe::class);
        $handler->expects($this->once())
            ->method('processPackage')
            ->with($package, 'update')
            ->willReturn($recipeMock);

        $command = new class($handler) extends ValksorRecipesInstallCommand {
            public function __construct(
                private readonly RecipeHandler $testHandler,
            ) {
                parent::__construct();
            }

            public function getHandler(): RecipeHandler
            {
                return $this->testHandler;
            }
        };

        $composer = $this->createComposer(
            [
                'valksor' => [
                    'allow' => '*',
                ],
            ],
        );

        $locker = $this->createStub(Locker::class);
        $locker->method('isLocked')->willReturn(true);
        $repository = $this->createStub(LockArrayRepository::class);
        $repository->method('getPackages')->willReturn([$package]);
        $locker->method('getLockedRepository')->willReturn($repository);
        $composer->method('getLocker')->willReturn($locker);
        $composer->method('getConfig')->willReturn($this->createStub(Config::class));

        $application = $this->createStub(Application::class);
        $application->method('getHelperSet')->willReturn($this->createStub(HelperSet::class));

        $io = $this->createMock(IOInterface::class);
        $io->method('isVerbose')->willReturn(false);
        $io->method('isDebug')->willReturn(false);
        $io->expects($this->exactly(2))
            ->method('writeError')
            ->with($this->logicalOr(
                '<info>Searching for local recipe to apply for test/success-package...</info>',
                '<info>Successfully applied local recipe for test/success-package.</info>',
            ));

        $input = $this->createStub(InputInterface::class);
        $output = $this->createStub(OutputInterface::class);
        $input->method('getArgument')->willReturn('test/success-package');

        $command->setApplication($application);
        $command->setComposer($composer);
        $command->setIO($io);

        $result = new ReflectionClass($command)->getMethod('execute')->invoke($command, $input, $output);

        // Should return success when recipe processing succeeds
        $this->assertSame(0, $result);
    }

    /**
     * Test getHandler method directly.
     *
     * @throws ReflectionException
     */
    public function testGetHandler(): void
    {
        $composer = $this->createComposer();
        $command = new ValksorRecipesInstallCommand();
        $command->setComposer($composer);

        $io = $this->createIO();
        $command->setIO($io);

        // Test that getHandler returns a RecipeHandler instance
        $handler = new ReflectionClass($command)->getMethod('getHandler')->invoke($command);
        $this->assertInstanceOf(RecipeHandler::class, $handler);
    }

    /**
     * Test getNoRecipeMessage method directly.
     *
     * @throws ReflectionException
     */
    public function testGetNoRecipeMessage(): void
    {
        $this->assertSame('<comment>No local recipe found for test/package.</comment>', new ReflectionClass($this->command)->getMethod('getNoRecipeMessage')->invoke($this->command, 'test/package'));
    }

    /**
     * Test getNotFoundMessage method directly.
     *
     * @throws ReflectionException
     */
    public function testGetNotFoundMessage(): void
    {
        $this->assertSame('<error>Package test/package is not installed.</error>', new ReflectionClass($this->command)->getMethod('getNotFoundMessage')->invoke($this->command, 'test/package'));
    }

    /**
     * Test getSuccessMessage method directly.
     *
     * @throws ReflectionException
     */
    public function testGetSuccessMessage(): void
    {
        $this->assertSame('<info>Successfully applied local recipe for test/package.</info>', new ReflectionClass($this->command)->getMethod('getSuccessMessage')->invoke($this->command, 'test/package'));
    }

    /**
     * Test processAllPackages private method directly.
     *
     * @throws ReflectionException
     */
    public function testProcessAllPackages(): void
    {
        $packageOne = $this->createPackage('test/package-one');
        $packageTwo = $this->createPackage('test/package-two');

        $command = new ValksorRecipesInstallCommand();
        $composer = $this->createComposer();

        $locker = $this->createStub(Locker::class);
        $locker->method('isLocked')->willReturn(true);
        $repository = $this->createStub(LockArrayRepository::class);
        $repository->method('getPackages')->willReturn([$packageOne, $packageTwo]);
        $locker->method('getLockedRepository')->willReturn($repository);
        $composer->method('getLocker')->willReturn($locker);
        $composer->method('getConfig')->willReturn($this->createStub(Config::class));

        $application = $this->createStub(Application::class);
        $application->method('getHelperSet')->willReturn($this->createStub(HelperSet::class));

        $io = $this->createMock(IOInterface::class);
        $io->method('isVerbose')->willReturn(false);
        $io->method('isDebug')->willReturn(false);
        $io->expects($this->exactly(2))
            ->method('writeError')
            ->with($this->logicalOr(
                '<info>Searching for local recipes to apply...</info>',
                '<info>No local recipes found to apply.</info>',
            ));

        $command->setApplication($application);
        $command->setComposer($composer);
        $command->setIO($io);

        // Use reflection to access private method
        $result = new ReflectionClass($command)->getMethod('processAllPackages')->invoke($command);

        $this->assertSame(0, $result);
    }

    /**
     * Test processAllPackages private method with success scenario.
     *
     * @throws ReflectionException
     */
    public function testProcessAllPackagesWithSuccess(): void
    {
        $packageOne = $this->createPackage('test/package-one');
        $packageTwo = $this->createPackage('test/package-two');

        // Mock RecipeHandler to return Recipe objects for some packages (success scenario)
        $handler = $this->createStub(RecipeHandler::class);
        $recipeMock = $this->createStub(Recipe::class);
        $handler->method('processPackage')
            ->willReturnMap([
                [$packageOne, 'update', $recipeMock],
                [$packageTwo, 'update', null], // No recipe for second package
            ]);

        $command = new class($handler) extends ValksorRecipesInstallCommand {
            public function __construct(
                private readonly RecipeHandler $testHandler,
            ) {
                parent::__construct();
            }

            public function getHandler(): RecipeHandler
            {
                return $this->testHandler;
            }
        };

        $composer = $this->createComposer();

        $locker = $this->createStub(Locker::class);
        $locker->method('isLocked')->willReturn(true);
        $repository = $this->createStub(LockArrayRepository::class);
        $repository->method('getPackages')->willReturn([$packageOne, $packageTwo]);
        $locker->method('getLockedRepository')->willReturn($repository);
        $composer->method('getLocker')->willReturn($locker);
        $composer->method('getConfig')->willReturn($this->createStub(Config::class));

        $application = $this->createStub(Application::class);
        $application->method('getHelperSet')->willReturn($this->createStub(HelperSet::class));

        $io = $this->createMock(IOInterface::class);
        $io->method('isVerbose')->willReturn(false);
        $io->method('isDebug')->willReturn(false);
        $io->expects($this->exactly(2))
            ->method('writeError')
            ->with($this->logicalOr(
                '<info>Searching for local recipes to apply...</info>',
                '<info>Successfully applied 1 local recipe(s).</info>',
            ));

        $input = $this->createStub(InputInterface::class);
        $output = $this->createStub(OutputInterface::class);
        $input->method('getArgument')->willReturn(null);

        $command->setApplication($application);
        $command->setComposer($composer);
        $command->setIO($io);

        $result = new ReflectionClass($command)->getMethod('execute')->invoke($command, $input, $output);

        $this->assertSame(0, $result);
    }

    /**
     * Test validateLockFile method when locked.
     *
     * @throws ReflectionException
     */
    public function testValidateLockFileWhenLocked(): void
    {
        $composer = $this->createComposer();
        $locker = $this->createLocker();
        $composer->method('getLocker')->willReturn($locker);

        $this->command->setComposer($composer);

        $result = new ReflectionClass($this->command)->getMethod('validateLockFile')->invoke($this->command);
        $this->assertSame(0, $result);
    }

    /**
     * Test validateLockFile method when not locked.
     *
     * @throws ReflectionException
     */
    public function testValidateLockFileWhenNotLocked(): void
    {
        $composer = $this->createComposer();
        $locker = $this->createLocker(false);
        $composer->method('getLocker')->willReturn($locker);

        $this->command->setComposer($composer);

        $io = $this->createMock(IOInterface::class);
        $io->expects($this->once())->method('writeError')->with('<error>No lock file found. Run `composer install` first.</error>');
        $this->command->setIO($io);

        $result = new ReflectionClass($this->command)->getMethod('validateLockFile')->invoke($this->command);
        $this->assertSame(1, $result);
    }

    protected function setUp(): void
    {
        $this->command = new ValksorRecipesInstallCommand();
    }
}
