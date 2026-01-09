# Running Tests

How to run and write tests for the Valksor PHP Plugin.

## Running Tests

### Run All Tests

```bash
vendor/bin/phpunit
```

### Run with Coverage

```bash
vendor/bin/phpunit --coverage-html=coverage
```

View the coverage report:
```bash
open coverage/index.html
```

### Run Specific Test

```bash
# Run a specific test class
vendor/bin/phpunit src/ValksorPlugin/Tests/Unit/ValksorFlexTest.php

# Run a specific test method
vendor/bin/phpunit --filter testActivate src/ValksorPlugin/Tests/Unit/ValksorFlexTest.php
```

### Run with Verbose Output

```bash
vendor/bin/phpunit --verbose
```

## Test Structure

```
src/ValksorPlugin/Tests/
├── Unit/
│   ├── ValksorFlexTest.php
│   ├── RecipeHandlerTest.php
│   └── Command/
│       ├── ValksorRecipesInstallCommandTest.php
│       └── ValksorRecipesUninstallCommandTest.php
├── bootstrap.php
└── phpunit.xml
```

## Writing Tests

### Test Example

```php
<?php declare(strict_types = 1);

namespace ValksorPlugin\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ValksorPlugin\ValksorFlex;

class ValksorFlexTest extends TestCase
{
    public function testActivateRegistersEventSubscriber(): void
    {
        $composer = $this->createMock(Composer::class);
        $io = $this->createMock(IOInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $composer->expects($this->once())
            ->method('getEventDispatcher')
            ->willReturn($dispatcher);

        $dispatcher->expects($this->once())
            ->method('addSubscriber')
            ->with($this->isInstanceOf(ValksorFlex::class));

        $plugin = new ValksorFlex();
        $plugin->activate($composer, $io);
    }
}
```

### Test Naming Conventions

- Test class: `{ClassName}Test.php`
- Test methods: `test{MethodName}` or `test{MethodName}_{scenario}`

## Test Categories

### Unit Tests

Test individual classes and methods in isolation:

```php
public function testProcessPackageReturnsNullWhenNotAllowed(): void
{
    // Arrange
    $package = $this->createMock(PackageInterface::class);
    $package->method('getName')->willReturn('test/package');

    // Act
    $result = $this->handler->processPackage($package, 'install');

    // Assert
    self::assertNull($result);
}
```

### Integration Tests

Test interactions between components:

```php
public function testRecipeIsAppliedDuringInstall(): void
{
    // Create a full integration test
}
```

## Mocking

### Mocking Composer Objects

```php
$composer = $this->createMock(Composer::class);
$io = $this->createMock(IOInterface::class);
$package = $this->createMock(PackageInterface::class);
```

### Setting Expectations

```php
$io->expects($this->once())
    ->method('writeError')
    ->with($this->stringContains('Applying local recipe'));
```

## Test Fixtures

Fixtures are stored in `Tests/Fixtures/`:

```
Tests/Fixtures/
├── recipes/
│   └── test-package/
│       └── manifest.json
└── composer/
    └── composer.json
```

## Coverage Requirements

- **Target:** 50%+ coverage for valksor-plugin code
- **Critical paths:** 80%+ coverage for core classes
- **New features:** Tests required before merge

## Debugging Tests

### Run with Debug Output

```bash
vendor/bin/phpunit --verbose --debug
```

### Stop on First Failure

```bash
vendor/bin/phpunit --stop-on-failure
```

### Filter Tests

```bash
# Run tests matching pattern
vendor/bin/phpunit --filter 'RecipeHandler'

# Exclude tests
vendor/bin/phpunit --exclude-group 'integration'
```

## CI/CD

Tests run automatically on:
- Pull requests
- Push to master branch

See `.github/workflows/` for CI configuration.

## See Also

- [Contributing](contributing.md) - Contribution guidelines
