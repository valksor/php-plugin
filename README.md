# ValksorPlugin

ValksorPlugin is a Composer plugin that provides automatic recipe processing for PHP packages, similar to [Symfony Flex](https://symfony.com/doc/current/components/flex.html). It automatically discovers and applies local recipes from package directories when packages are installed, updated, or uninstalled.

## Installation

```bash
composer require valksor/php-plugin
```

## How It Works

ValksorPlugin hooks into Composer's event system to automatically process recipes when packages are installed, updated, or removed:

1. **Automatic Discovery**: When a package is installed/updated, the plugin searches for a `recipe/` directory in the package
2. **Recipe Processing**: If `recipe/manifest.json` is found, the recipe is applied using Symfony Flex's configurator system
3. **Lock File Management**: Recipes are tracked in `symfony.lock` for proper uninstallation

### Composer Events Hooked

- `post-package-install` - Applies recipes after package installation
- `post-package-update` - Re-applies recipes after package updates
- `pre-package-uninstall` - Removes recipes before package uninstallation

## Configuration

Add plugin configuration to your `composer.json`:

```json
{
    "extra": {
        "valksor": {
            "allow": {
                "*": true,
                "vendor/package": {
                    "allow_override": true
                }
            }
        }
    }
}
```

### Configuration Options

- **`allow`**: Controls which packages can have recipes processed
  - `"*": true` - Allow all packages (wildcard)
  - `"vendor/package": {}` - Allow specific package
  - `"vendor/package": {"allow_override": true}` - Allow recipe overrides

## Available Commands

### valksor:install

Manually install recipes for all or specific packages:

```bash
# Install recipes for all packages
composer valksor:install

# Install recipe for specific package
composer valksor:install vendor/package
```

### valksor:uninstall

Remove recipes for a specific package:

```bash
composer valksor:uninstall vendor/package
```

## Recipe Format

ValksorPlugin uses the same recipe format as Symfony Flex. For complete recipe documentation, see the [Symfony Flex Recipe Documentation](https://symfony.com/doc/current/components/flex/recipes.html).

### Basic Recipe Structure

Recipes are stored in a `recipe/` directory within packages:

```
vendor/package/
    recipe/
        manifest.json
        config/
            packages.yaml
        public/
            css/
        src/
```

### Example manifest.json

```json
{
    "bundles": {
        "Symfony\\Bundle\\FrameworkBundle\\FrameworkBundle": ["all"]
    },
    "copy-from-recipe": {
        "config/": "%CONFIG_DIR%/"
    },
    "env": {
        "DATABASE_URL": "mysql://user:pass@127.0.0.1:3306/db_name"
    }
}
```

### Recipe Files

- **`manifest.json`**: Recipe configuration defining what to install
- **Configuration files**: YAML, PHP, or other config files
- **Templates**: Files to be copied to the project

## Examples

### Package with Recipe

A package containing a recipe:

```
my-cool-package/
    composer.json
    src/
        Service.php
    recipe/
        manifest.json
            config/
                my_config.yaml
```

**recipe/manifest.json**:
```json
{
    "copy-from-recipe": {
        "config/": "%CONFIG_DIR%/"
    }
}
```

When this package is installed:

```bash
composer require my-cool-package
```

The plugin will automatically:
1. Discover the recipe in `recipe/manifest.json`
2. Copy `config/my_config.yaml` to your project's config directory
3. Update `symfony.lock` with recipe information

### Manual Recipe Installation

If a package was installed before the plugin:

```bash
composer valksor:install my-cool-package
```

### Recipe Removal

```bash
composer valksor:uninstall my-cool-package
```

## Integration with Symfony Flex

ValksorPlugin is built on top of Symfony Flex and:

- Uses Symfony Flex's configurator system for recipe processing
- Maintains compatibility with existing Symfony recipes
- Uses the same `symfony.lock` file format
- Supports all Symfony Flex recipe features

## Requirements

- PHP 8.1 or higher
- Composer 2.0 or higher
- Symfony Flex (as dependency)

## License

This package is part of the Valksor package. See the LICENSE file for copyright information.
