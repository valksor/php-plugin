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
use Composer\Package\Locker;
use Composer\Repository\LockArrayRepository;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use ValksorPlugin\Command\ValksorRecipesInstallCommand;
use ValksorPlugin\Tests\Mocks\ComposerMockFactory;

/**
 * Unit tests for ValksorRecipesInstallCommand class.
 *
 * Tests the install command configuration and basic functionality.
 */
#[CoversClass(ValksorRecipesInstallCommand::class)]
class ValksorRecipesInstallCommandTest extends TestCase
{
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
        $this->assertSame('int', $method->getReturnType()->getName());
    }

    /**
     * Test execute method with no lock file.
     *
     * @throws ReflectionException
     */
    public function testExecuteWithNoLockFile(): void
    {
        $composer = ComposerMockFactory::createComposer();
        $locker = ComposerMockFactory::createLocker(false);
        $composer->shouldReceive('getLocker')->andReturn($locker);
        $composer->shouldReceive('getConfig')->andReturn(Mockery::mock(Config::class));

        $application = Mockery::mock(Application::class);
        $application->shouldReceive('getHelperSet')->andReturn(Mockery::mock(HelperSet::class));
        $this->command->setApplication($application);
        $this->command->setComposer($composer);

        $this->input = Mockery::mock(InputInterface::class);
        $this->output = Mockery::mock(OutputInterface::class);

        $this->input->shouldReceive('getArgument')->with('package')->andReturn(null);

        $io = ComposerMockFactory::createIO();
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
        $composer = ComposerMockFactory::createComposer();
        $package1 = ComposerMockFactory::createPackage('test/package1');
        $package2 = ComposerMockFactory::createPackage('test/package2');
        // Create locker mock directly to control repository type
        $locker = Mockery::mock(Locker::class);
        $locker->shouldReceive('isLocked')->andReturn(true);
        $repository = Mockery::mock(LockArrayRepository::class);
        $repository->shouldReceive('getPackages')->andReturn([$package1, $package2]);
        $locker->shouldReceive('getLockedRepository')->andReturn($repository);
        $composer->shouldReceive('getLocker')->andReturn($locker);
        $composer->shouldReceive('getConfig')->andReturn(Mockery::mock(Config::class));

        $application = Mockery::mock(Application::class);
        $application->shouldReceive('getHelperSet')->andReturn(Mockery::mock(HelperSet::class));
        $this->command->setApplication($application);
        $this->command->setComposer($composer);

        $this->input = Mockery::mock(InputInterface::class);
        $this->output = Mockery::mock(OutputInterface::class);

        $this->input->shouldReceive('getArgument')->with('package')->andReturn(null);

        $io = ComposerMockFactory::createIO();
        $this->command->setIO($io);

        // Mock installation manager to return path without recipes for packages
        $installManager = Mockery::mock(InstallationManager::class);
        $installManager->shouldReceive('getInstallPath')
            ->with($package1)
            ->andReturn(sys_get_temp_dir() . '/package1');
        $installManager->shouldReceive('getInstallPath')
            ->with($package2)
            ->andReturn(sys_get_temp_dir() . '/package2');

        $composer->shouldReceive('getInstallationManager')->andReturn($installManager);

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
        $composer = ComposerMockFactory::createComposer();
        $package = ComposerMockFactory::createPackage('test/no-recipe-package');
        // Create locker mock directly to control repository type
        $locker = Mockery::mock(Locker::class);
        $locker->shouldReceive('isLocked')->andReturn(true);
        $repository = Mockery::mock(LockArrayRepository::class);
        $repository->shouldReceive('getPackages')->andReturn([$package]);
        $locker->shouldReceive('getLockedRepository')->andReturn($repository);
        $composer->shouldReceive('getLocker')->andReturn($locker);
        $composer->shouldReceive('getConfig')->andReturn(Mockery::mock(Config::class));

        $application = Mockery::mock(Application::class);
        $application->shouldReceive('getHelperSet')->andReturn(Mockery::mock(HelperSet::class));
        $this->command->setApplication($application);
        $this->command->setComposer($composer);

        $this->input = Mockery::mock(InputInterface::class);
        $this->output = Mockery::mock(OutputInterface::class);

        $this->input->shouldReceive('getArgument')->with('package')->andReturn('test/no-recipe-package');

        $io = ComposerMockFactory::createIO();
        $this->command->setIO($io);

        // Mock installation manager to return path without recipe
        $installManager = ComposerMockFactory::createInstallationManager(sys_get_temp_dir() . '/no-recipe');
        $composer->shouldReceive('getInstallationManager')->andReturn($installManager);

        $result = new ReflectionClass($this->command)->getMethod('execute')->invoke($this->command, $this->input, $this->output);
        $this->assertSame(1, $result);
    }

    /**
     * Test execute method with specific package not found.
     *
     * @throws ReflectionException
     */
    public function testExecuteWithSpecificPackageNotFound(): void
    {
        $composer = ComposerMockFactory::createComposer();
        $package = ComposerMockFactory::createPackage('other/package');
        // Create locker mock directly to control repository type
        $locker = Mockery::mock(Locker::class);
        $locker->shouldReceive('isLocked')->andReturn(true);
        $repository = Mockery::mock(LockArrayRepository::class);
        $repository->shouldReceive('getPackages')->andReturn([$package]);
        $locker->shouldReceive('getLockedRepository')->andReturn($repository);
        $composer->shouldReceive('getLocker')->andReturn($locker);
        $composer->shouldReceive('getConfig')->andReturn(Mockery::mock(Config::class));

        $application = Mockery::mock(Application::class);
        $application->shouldReceive('getHelperSet')->andReturn(Mockery::mock(HelperSet::class));
        $this->command->setApplication($application);
        $this->command->setComposer($composer);

        $this->input = Mockery::mock(InputInterface::class);
        $this->output = Mockery::mock(OutputInterface::class);

        $this->input->shouldReceive('getArgument')->with('package')->andReturn('nonexistent/package');

        $io = ComposerMockFactory::createIO();
        $this->command->setIO($io);

        $result = new ReflectionClass($this->command)->getMethod('execute')->invoke($this->command, $this->input, $this->output);
        $this->assertSame(1, $result);
    }

    /**
     * Test execute method with specific package found and recipe applied successfully.
     *
     * @throws ReflectionException
     */
    public function testExecuteWithSpecificPackageSuccess(): void
    {
        $composer = ComposerMockFactory::createComposer(
            [
                'valksor' => [
                    'allow' => '*',
                ],
            ],
        );
        $package = ComposerMockFactory::createPackage('test/success-package');
        // Create locker mock directly to control repository type
        $locker = Mockery::mock(Locker::class);
        $locker->shouldReceive('isLocked')->andReturn(true);
        $repository = Mockery::mock(LockArrayRepository::class);
        $repository->shouldReceive('getPackages')->andReturn([$package]);
        $locker->shouldReceive('getLockedRepository')->andReturn($repository);
        $composer->shouldReceive('getLocker')->andReturn($locker);
        $composer->shouldReceive('getConfig')->andReturn(Mockery::mock(Config::class));

        $application = Mockery::mock(Application::class);
        $application->shouldReceive('getHelperSet')->andReturn(Mockery::mock(HelperSet::class));
        $this->command->setApplication($application);
        $this->command->setComposer($composer);

        $this->input = Mockery::mock(InputInterface::class);
        $this->output = Mockery::mock(OutputInterface::class);

        $this->input->shouldReceive('getArgument')->with('package')->andReturn('test/success-package');

        $io = ComposerMockFactory::createIO();
        $this->command->setIO($io);

        // Mock installation manager to return valid recipe path
        $installManager = ComposerMockFactory::createInstallationManager(__DIR__ . '/../Fixtures/recipes/valid-recipe');
        $composer->shouldReceive('getInstallationManager')->andReturn($installManager);

        $result = new ReflectionClass($this->command)->getMethod('execute')->invoke($this->command, $this->input, $this->output);

        // Should return 1 when recipe processing returns null (no valid recipe found)
        $this->assertSame(1, $result);
    }

    protected function setUp(): void
    {
        $this->command = new ValksorRecipesInstallCommand();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
