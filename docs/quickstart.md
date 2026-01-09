# Quick Start

Get up and running with Valksor PHP Plugin in 5 minutes.

## Prerequisites

- PHP 8.4 or higher
- Composer 2.0 or higher

## Installation

```bash
composer require valksor/php-plugin
composer config allow-plugins.valksor/php-plugin true
```

## Basic Configuration

Add to your `composer.json`:

```json
{
    "config": {
        "allow-plugins": {
            "valksor/php-plugin": true
        }
    },
    "extra": {
        "valksor": {
            "allow": "*"
        }
    }
}
```

## Verify Installation

```bash
composer show valksor/php-plugin
composer list | grep valksor
```

You should see the plugin information and available commands.

## First Recipe

Create a simple recipe in your package:

```
your-package/
    recipe/
        manifest.json
```

**manifest.json:**

```json
{
    "bundles": {
        "YourVendor\\YourBundle\\YourBundle": ["all"]
    },
    "copy-from-recipe": {
        "config/": "%CONFIG_DIR%/"
    }
}
```

Install your package:

```bash
composer require your-vendor/your-package
```

The recipe will be automatically applied!

## Next Steps

- [Installation](getting-started/installation.md) - Detailed installation options
- [Configuration](getting-started/configuration.md) - Advanced configuration
- [Creating Recipes](guides/creating-recipes.md) - Build your own recipes
