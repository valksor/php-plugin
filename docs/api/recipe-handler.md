# RecipeHandler

`RecipeHandler` is responsible for discovering and processing local recipes for packages. It integrates with Symfony Flex's configurator system to apply recipe configurations.

## Constants

| Constant | Value | Description |
|----------|-------|-------------|
| `CONFIG_KEY` | `'valksor'` | Configuration key in composer.json |
| `RECIPE_DIR` | `'recipe'` | Directory name containing recipes |

## Public Methods

### __construct(Composer $composer, IOInterface $io)

Creates a new RecipeHandler instance.

**Parameters:**
- `$composer` - The Composer instance
- `$io` - The I/O interface for user interaction

### processPackage(PackageInterface $package, string $operation): ?Recipe

Process a package and apply its local recipe if available.

**Parameters:**
- `$package` - The package to process
- `$operation` - The operation type ('install', 'update')

**Returns:** The applied recipe, or null if no recipe found/allowed

**Process:**
1. Check if package is allowed in configuration
2. Discover local recipe in package directory
3. Apply recipe using Symfony Flex configurator
4. Update symfony.lock file

### uninstallPackage(PackageInterface $package): ?Recipe

Uninstall a local recipe for a package.

**Parameters:**
- `$package` - The package to uninstall the recipe for

**Returns:** The uninstalled recipe, or null if no recipe found/allowed

**Process:**
1. Check if package is allowed in configuration
2. Discover local recipe
3. Remove from symfony.lock
4. Unconfigure using Symfony Flex configurator

## Private Methods

### getLocalRecipe(PackageInterface $package, string $operation): ?Recipe

Discover and load a local recipe from a package directory.

**Searches for:**
```
vendor/package/
    recipe/
        manifest.json
        config/
        templates/
```

**Returns:** A `Recipe` object with manifest and files, or null if not found

### isPackageAllowed(string $packageName): bool

Check if a package is allowed to have recipes processed.

**Rules:**
- Returns `true` if `allow: "*"` is configured
- Returns `true` if package is in `allow: {"vendor/package": {}}` array
- Returns `false` otherwise

### initializeFlexObjects(): void

Initialize Symfony Flex objects (Lock and Configurator).

**Creates:**
- `Lock` - Manages symfony.lock file
- `Options` - Directory structure configuration
- `Configurator` - Applies recipe changes

## Usage Example

```php
use ValksorPlugin\RecipeHandler;
use Composer\Composer;
use Composer\IO\IOInterface;

$handler = new RecipeHandler($composer, $io);

// Process a package during install
$recipe = $handler->processPackage($package, 'install');

// Uninstall a package's recipe
$recipe = $handler->uninstallPackage($package);
```

## Recipe Manifest Format

The handler expects a `manifest.json` file in the recipe directory:

```json
{
    "bundles": {
        "Vendor\\Package\\Bundle": ["all"]
    },
    "copy-from-recipe": {
        "config/": "%CONFIG_DIR%/"
    },
    "env": {
        "DATABASE_URL": "mysql://localhost/db"
    }
}
```

## See Also

- [ValksorFlex](valksor-flex.md) - Main plugin class
- [Recipe Format](../guides/creating-recipes.md) - Creating recipes
