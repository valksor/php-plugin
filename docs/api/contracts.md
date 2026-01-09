# Contracts

The Valksor PHP Plugin defines interfaces that must be implemented by command classes.

## RecipeCommandInterface

Interface for recipe commands. Defines the contract for commands that process packages using the RecipeHandler.

### Namespace

`ValksorPlugin\Contracts\RecipeCommandInterface`

### Methods

#### processPackage(PackageInterface $package): ?Recipe

Process a package using the RecipeHandler.

This is a template method that subclasses should override to provide their specific processing logic (install, uninstall, etc.).

**Parameters:**
- `$package` - The package to process

**Returns:** The result of the processing, or null if no recipe found

#### getNoRecipeMessage(string $packageName): string

Get the failure message for when no recipe is found.

**Parameters:**
- `$packageName` - The package name

**Returns:** The failure message

#### getNotFoundMessage(string $packageName): string

Get the not found message for when a package is not installed.

**Parameters:**
- `$packageName` - The package name

**Returns:** The not found message

#### getSuccessMessage(string $packageName): string

Get the success message for when a package is processed successfully.

**Parameters:**
- `$packageName` - The package name

**Returns:** The success message

### Implementation Example

```php
use ValksorPlugin\Contracts\RecipeCommandInterface;
use Composer\Package\PackageInterface;
use Symfony\Flex\Recipe;

class MyCustomCommand implements RecipeCommandInterface
{
    public function processPackage(PackageInterface $package): ?Recipe
    {
        // Custom processing logic
        return $this->getHandler()->processPackage($package, 'install');
    }

    public function getNoRecipeMessage(string $packageName): string
    {
        return sprintf('No recipe found for %s', $packageName);
    }

    public function getNotFoundMessage(string $packageName): string
    {
        return sprintf('Package %s is not installed', $packageName);
    }

    public function getSuccessMessage(string $packageName): string
    {
        return sprintf('Successfully processed %s', $packageName);
    }
}
```

### Implementations

- `ValksorRecipesInstallCommand` - Installs recipes
- `ValksorRecipesUninstallCommand` - Uninstalls recipes

## See Also

- [Commands](commands.md) - Command implementations
- [ValksorFlex](valksor-flex.md) - Main plugin class
