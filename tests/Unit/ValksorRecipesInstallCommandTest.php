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

use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use ValksorPlugin\Command\ValksorRecipesInstallCommand;

/**
 * Unit tests for ValksorRecipesInstallCommand class.
 *
 * Tests the install command configuration and basic functionality.
 */
#[CoversClass(ValksorRecipesInstallCommand::class)]
class ValksorRecipesInstallCommandTest extends TestCase
{
    private ValksorRecipesInstallCommand $command;

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
