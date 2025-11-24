# Valksor PHP Plugin

[![valksor](https://badgen.net/static/org/valksor/green)](https://github.com/valksor) 
[![BSD-3-Clause](https://img.shields.io/badge/BSD--3--Clause-green?style=flat)](https://github.com/valksor/php-plugin/blob/master/LICENSE)
[![Coverage Status](https://coveralls.io/repos/github/valksor/php-plugin/badge.svg?branch=master)](https://coveralls.io/github/valksor/php-plugin?branch=master) 
[![php](https://badgen.net/static/php/>=8.4/purple)](https://www.php.net/releases/8.4/en.php)

A Composer plugin that provides automatic recipe processing for PHP packages, similar to [Symfony Flex](https://symfony.com/doc/current/components/flex.html). It automatically discovers and applies local recipes from package directories when packages are installed, updated, or uninstalled. This plugin is part of the Valksor ecosystem and enables seamless package configuration management for PHP applications.

⚠️ **Composer 2.2+ Security Requirement**: You must explicitly allow this plugin in your `composer.json` for it to work.

## Installation

```bash
composer require valksor/php-plugin
composer config allow-plugins.valksor/php-plugin true
```

Add to `composer.json`:

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

## Features

- **Automatic Recipe Discovery**: Automatically finds and processes local recipes in package directories during Composer operations
- **Symfony Flex Compatibility**: Built on top of Symfony Flex's configurator system with full recipe format compatibility
- **Event-Driven Processing**: Hooks into Composer's package lifecycle events (install, update, uninstall) for seamless automation
- **Local Recipe Focus**: Specifically designed for local recipe discovery within package directories
- **Manual Recipe Management**: Provides commands for manual recipe installation and removal when needed
- **Configuration Flexibility**: Supports both wildcard and package-specific recipe permissions
- **Lock File Integration**: Uses Symfony Flex's symfony.lock file for proper recipe tracking and cleanup
- **Duplicate Prevention**: Intelligent handling of package aliases to prevent duplicate processing
- **Override Support**: Configurable recipe override capabilities for development environments

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

## Configuration

### Complete Configuration

```json
{
    "config": {
        "allow-plugins": {
            "valksor/php-plugin": true,
            "symfony/flex": true
        }
    },
    "extra": {
        "valksor": {
            "allow": "*"
        }
    }
}
```

### Configuration Options

- **`config.allow-plugins.valksor/php-plugin: true`** - Required for Composer 2.2+
- **`extra.valksor.allow: "*"`** - Allow all packages to have recipes processed
- **`extra.valksor.allow: {"vendor/package": {}}`** - Allow only specific packages
- **`extra.valksor.allow: {"vendor/package": {"allow_override": true}}`** - Allow recipe overrides

## Recipe Format

Uses the same recipe format as Symfony Flex. See [Symfony Flex Recipe Documentation](https://symfony.com/doc/current/components/flex/recipes.html).

### Basic Structure

```
vendor/package/
    recipe/
        manifest.json
        config/
            packages.yaml
        public/
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

## Advanced Usage

### Selective Recipe Processing

Configure which packages are allowed to have recipes processed:

```json
{
    "extra": {
        "valksor": {
            "allow": {
                "my-vendor/core-package": {},
                "my-vendor/optional-package": {
                    "allow_override": true
                },
                "external-vendor/package": {}
            }
        }
    }
}
```

### Development Environment with Overrides

Enable recipe overrides for frequent development updates:

```json
{
    "extra": {
        "valksor": {
            "allow": {
                "my-vendor/dev-package": {
                    "allow_override": true
                }
            }
        }
    }
}
```

### Custom Recipe Development

Create recipes for your packages following this structure:

```
your-package/
    composer.json
    src/
        YourCode.php
    recipe/
        manifest.json
        config/
            packages.yaml
        templates/
            some_template.php.twig
```

**manifest.json with multiple features**:

```json
{
    "bundles": {
        "YourVendor\\YourBundle\\YourBundle": ["all"]
    },
    "copy-from-recipe": {
        "config/": "%CONFIG_DIR%/",
        "templates/": "%TEMPLATES_DIR%/"
    },
    "env": {
        "YOUR_SERVICE_URL": "https://api.example.com",
        "YOUR_API_KEY": "your-api-key-here"
    },
    "post-install-output": [
        "  <info>✓ Your package has been configured</info>",
        "  <info>  Update your .env file with the API key</info>"
    ]
}
```

## Troubleshooting

| Issue                  | Cause                                | Solution                                                           |
| ---------------------- | ------------------------------------ | ------------------------------------------------------------------ |
| Plugin blocked         | Composer 2.2+ security feature       | `composer config allow-plugins.valksor/php-plugin true`            |
| Recipes not processing | Plugin not allowed or not configured | Check `composer config allow-plugins` and `extra.valksor` settings |
| Recipe not found       | Package has no recipe directory      | Contact package maintainer or create custom recipe                 |

### Debug Commands

```bash
# Check plugin status
composer show valksor/php-plugin
composer config allow-plugins | grep valksor
composer list | grep valksor

# Manual recipe processing
composer valksor:install vendor/package
```

## Contributing

Contributions are welcome! Please follow these guidelines:

### Development Setup

1. **Clone the repository**:

    ```bash
    git clone https://github.com/valksor/php-plugin.git
    cd php-plugin
    ```

2. **Install dependencies**:

    ```bash
    composer install
    ```

3. **Run tests**:
    ```bash
    vendor/bin/phpunit
    ```

### Pull Request Guidelines

- **PSR-12 Coding Standards**: Ensure code follows PSR-12 standards
- **Tests**: Include tests for new features
- **Documentation**: Update README and docblocks as needed
- **Commits**: Use clear, descriptive commit messages
- **Branching**: Create feature branches from `master`

### Code Quality

All code must pass:

- **PHPUnit tests** with 100% coverage where possible
- **PHP-CS-Fixer** code style checks (config file can be found in [valksor-dev](https://github.com/valksor/php-dev))

### Reporting Issues

Please use [GitHub Issues](https://github.com/valksor/php-plugin/issues) to report bugs or request features. Include:

- PHP and Composer versions
- Steps to reproduce
- Expected vs actual behavior
- Example configuration if applicable

## Security

If you discover a security vulnerability, please send an email to packages@valksor.com instead of using the issue tracker. All security vulnerabilities will be promptly addressed.

## Support & Community

- **GitHub Issues**: [Bug reports and feature requests](https://github.com/valksor/php-plugin/issues)
- **GitHub Discussions**: [Community discussions and Q&A](https://github.com/valksor/discussions)
- **Symfony Flex Documentation**: [Official Recipe Documentation](https://symfony.com/doc/current/components/flex/recipes.html)

## Credits

- **Original Author**: [Davis Zalitis (k0d3r1s)](https://github.com/k0d3r1s)
- **Maintainer**: [SIA Valksor](https://valksor.com)
- **All Contributors**: [Contributors list](https://github.com/valksor/php-plugin/graphs/contributors)

This plugin is inspired by and built upon [Symfony Flex](https://symfony.com/doc/current/components/flex.html), providing enhanced local recipe discovery capabilities for the Valksor ecosystem.

## Requirements

- **PHP 8.4 or higher**
- **Composer 2.0 or higher**
- **Symfony Flex** (as dependency)

## License

This package is part of the Valksor package. See the [LICENSE](LICENSE) file for copyright information.
