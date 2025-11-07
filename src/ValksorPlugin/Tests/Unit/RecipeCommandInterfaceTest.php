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
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ValksorPlugin\Command\ValksorRecipesInstallCommand;

/**
 * Unit tests for RecipeCommandInterface.
 *
 * Tests that the interface contract is properly implemented by the command classes.
 */
class RecipeCommandInterfaceTest extends TestCase
{
    public function testInterfaceMethodSignatures(): void
    {
        $command = new ValksorRecipesInstallCommand();

        // Test getNoRecipeMessage signature
        $reflection = new ReflectionClass($command);
        $method = $reflection->getMethod('getNoRecipeMessage');
        $this->assertSame(1, $method->getNumberOfParameters());
        $this->assertSame('string', $method->getReturnType()?->getName());

        // Test getNotFoundMessage signature
        $method = $reflection->getMethod('getNotFoundMessage');
        $this->assertSame(1, $method->getNumberOfParameters());
        $this->assertSame('string', $method->getReturnType()?->getName());

        // Test getSuccessMessage signature
        $method = $reflection->getMethod('getSuccessMessage');
        $this->assertSame(1, $method->getNumberOfParameters());
        $this->assertSame('string', $method->getReturnType()?->getName());

        // Test processPackage signature
        $method = $reflection->getMethod('processPackage');
        $this->assertSame(1, $method->getNumberOfParameters());
        $this->assertTrue($method->getReturnType()?->allowsNull());
    }

    public function testInterfaceMethodsExist(): void
    {
        $command = new ValksorRecipesInstallCommand();

        // Test that all interface methods are implemented
        $this->assertTrue(method_exists($command, 'getNoRecipeMessage'));
        $this->assertTrue(method_exists($command, 'getNotFoundMessage'));
        $this->assertTrue(method_exists($command, 'getSuccessMessage'));
        $this->assertTrue(method_exists($command, 'processPackage'));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
