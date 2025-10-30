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
use ValksorPlugin\Command\ValksorRecipesUninstallCommand;

/**
 * Unit tests for ValksorRecipesUninstallCommand class.
 *
 * Tests the uninstall command configuration and basic functionality.
 */
#[CoversClass(ValksorRecipesUninstallCommand::class)]
class ValksorRecipesUninstallCommandTest extends TestCase
{
    private ValksorRecipesUninstallCommand $command;

    public function testCommandNameAndDescription(): void
    {
        $this->assertSame('valksor:uninstall', $this->command->getName());
        $this->assertStringContainsString('Removes a local recipe', $this->command->getDescription());
        $this->assertStringContainsString('specific package', $this->command->getDescription());
    }

    public function testConfigure(): void
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasArgument('package'));
        $this->assertSame('valksor:uninstall', $this->command->getName());
        $this->assertStringContainsString('Removes a local recipe', $this->command->getDescription());

        $argument = $definition->getArgument('package');
        $this->assertTrue($argument->isRequired(), 'package argument should be required');
        $this->assertStringContainsString('Package name to uninstall', $argument->getDescription());
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

    public function testPackageArgumentExample(): void
    {
        $argument = $this->command->getDefinition()->getArgument('package');

        $this->assertStringContainsString('valksor/php-plugin', $argument->getDescription());
    }

    protected function setUp(): void
    {
        $this->command = new ValksorRecipesUninstallCommand();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
