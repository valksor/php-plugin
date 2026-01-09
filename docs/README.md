# Valksor PHP Plugin

[![valksor](https://badgen.net/static/org/valksor/green)](https://github.com/valksor)
[![BSD-3-Clause](https://img.shields.io/badge/BSD--3--Clause-green?style=flat)](https://github.com/valksor/php-plugin/blob/master/LICENSE)
[![Coverage Status](https://coveralls.io/repos/github/valksor/php-plugin/badge.svg?branch=master)](https://coveralls.io/github/valksor/php-plugin?branch=master)
[![php](https://badgen.net/static/php/>=8.4/purple)](https://www.php.net/releases/8.4/en.php)

A Composer plugin that provides automatic recipe processing for PHP packages, similar to [Symfony Flex](https://symfony.com/doc/current/components/flex.html). It automatically discovers and applies local recipes from package directories when packages are installed, updated, or uninstalled.

## Features

- **Automatic Recipe Discovery** - Automatically finds and processes local recipes in package directories during Composer operations
- **Symfony Flex Compatibility** - Built on top of Symfony Flex's configurator system with full recipe format compatibility
- **Event-Driven Processing** - Hooks into Composer's package lifecycle events (install, update, uninstall) for seamless automation
- **Local Recipe Focus** - Specifically designed for local recipe discovery within package directories
- **Manual Recipe Management** - Provides commands for manual recipe installation and removal when needed
- **Lock File Integration** - Uses Symfony Flex's symfony.lock file for proper recipe tracking and cleanup

## Quick Start

```bash
# Install the plugin
composer require valksor/php-plugin

# Allow the plugin (required for Composer 2.2+)
composer config allow-plugins.valksor/php-plugin true
```

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

That's it! Recipes will now be automatically processed when you install packages.

## Usage

The plugin automatically processes recipes when packages are installed. For manual control:

```bash
# Install recipes for all packages
composer valksor:install

# Install recipe for specific package
composer valksor:install vendor/package

# Remove recipe for specific package
composer valksor:uninstall vendor/package
```

## Documentation

- [Quick Start](quickstart.md) - Get up and running in 5 minutes
- [Installation](getting-started/installation.md) - Detailed installation guide
- [Configuration](getting-started/configuration.md) - Configuration options
- [API Reference](api/valksor-flex.md) - API documentation
- [Architecture](architecture/overview.md) - System architecture
- [Creating Recipes](guides/creating-recipes.md) - Recipe creation guide

## Requirements

- **PHP 8.4** or higher
- **Composer 2.0** or higher
- **Symfony Flex** (installed as a dependency)

## License

This package is part of the Valksor package. See the [LICENSE](https://github.com/valksor/php-plugin/blob/master/LICENSE) file for copyright information.
