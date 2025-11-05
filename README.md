# ValksorPlugin

[![BSD-3-Clause](https://img.shields.io/badge/BSD--3--Clause-green?style=flat)](https://github.com/valksor/php-plugin/blob/master/LICENSE)
[![Coverage Status](https://coveralls.io/repos/github/valksor/php-plugin/badge.svg?branch=master)](https://coveralls.io/github/valksor/php-plugin?branch=master)

⚠️ **IMPORTANT: Composer Plugin Security Requirement**

Starting with Composer 2.2+, you **must explicitly allow** this plugin in your `composer.json` for it to work. Without this configuration, the plugin will be blocked and recipes will not be processed.

```bash
# Allow this plugin using CLI command
composer config allow-plugins.valksor/php-plugin true

# OR manually add to composer.json:
{
    "config": {
        "allow-plugins": {
            "valksor/php-plugin": true
        }
    }
}
```

---

ValksorPlugin is a Composer plugin that provides automatic recipe processing for PHP packages, similar to [Symfony Flex](https://symfony.com/doc/current/components/flex.html). It automatically discovers and applies local recipes from package directories when packages are installed, updated, or uninstalled.

## Installation

### Step 1: Install the Plugin

```bash
composer require valksor/php-plugin
```

### Step 2: Allow the Plugin (Required for Composer 2.2+)

⚠️ **This step is mandatory** - the plugin will not work without it.

#### Option A: Using CLI Commands (Recommended)

```bash
# Allow the plugin
composer config allow-plugins.valksor/php-plugin true

# Verify the plugin is allowed
composer config allow-plugins
```

#### Option B: Manual Configuration

Edit your `composer.json` and add the plugin to the `allow-plugins` section:

```json
{
    "config": {
        "allow-plugins": {
            "valksor/php-plugin": true
        }
    }
}
```

### Step 3: Configure Plugin Behavior (Optional)

Add plugin configuration to your `composer.json`:

```json
{
    "extra": {
        "valksor": {
            "allow": "*"
        }
    }
}
```

### Step 4: Verify Installation

```bash
# Check that the plugin is properly loaded
composer show valksor/php-plugin

# Verify plugin permissions are set
composer config allow-plugins | grep valksor
```

The plugin is now ready to automatically process recipes when you install packages!

## Plugin Management CLI Commands

### Composer Plugin Commands

These commands help you manage the plugin permissions and status:

```bash
# Allow the plugin (most common command)
composer config allow-plugins.valksor/php-plugin true

# Check current plugin permissions
composer config allow-plugins

# Allow all plugins (use with caution)
composer config allow-plugins true

# Remove plugin permission
composer config allow-plugins.valksor/php-plugin --unset
```

### Plugin Status and Debugging

```bash
# Show plugin details
composer show valksor/php-plugin

# Check if plugin is installed and allowed
composer show | grep valksor
composer config allow-plugins | grep valksor

# List all available composer commands (helps verify plugin commands are loaded)
composer list | grep valksor
```

### Batch Plugin Management

```bash
# Allow multiple common plugins at once
composer config allow-plugins.symfony/flex true
composer config allow-plugins.valksor/php-plugin true

# Check all currently allowed plugins
composer config allow-plugins
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

Complete plugin configuration requires both security permissions and behavior settings:

### Complete Configuration Example

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

### Security Configuration (Required)

```json
{
    "config": {
        "allow-plugins": {
            "valksor/php-plugin": true
        }
    }
}
```

**⚠️ Important**: The `config.allow-plugins` section is **required** for Composer 2.2+. Without this, the plugin will be blocked.

### Plugin Behavior Configuration

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

#### Security Settings (`config.allow-plugins`)

- **`"valksor/php-plugin": true`** - Allows this specific plugin to run
- **`"*": true`** - Allows all plugins (use with caution)
- **`"symfony/flex": true`** - Also allow Symfony Flex (recommended for compatibility)

#### Plugin Behavior (`extra.valksor.allow`)

- **`"*": true`** - Allow all packages to have recipes processed (wildcard)
- **`"vendor/package": {}`** - Allow only specific package recipes
- **`"vendor/package": {"allow_override": true}`** - Allow recipe overrides for specific package

### Configuration Examples

#### Minimal (Permissive) Configuration
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

#### Restricted Configuration
```json
{
    "config": {
        "allow-plugins": {
            "valksor/php-plugin": true
        }
    },
    "extra": {
        "valksor": {
            "allow": {
                "my-vendor/my-package": {},
                "trusted-vendor/another-package": {
                    "allow_override": true
                }
            }
        }
    }
}
```

#### Development Environment
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

## Troubleshooting

### Plugin Permission Issues

**Problem**: Plugin is blocked or recipes are not being processed.

**Solution**: Ensure the plugin is properly allowed:

```bash
# Check if plugin is allowed
composer config allow-plugins | grep valksor

# If not found, allow the plugin
composer config allow-plugins.valksor/php-plugin true

# Verify plugin is installed
composer show valksor/php-plugin
```

**Common Error Messages**:
- `"valksor/php-plugin" has been blocked from running` - Plugin needs to be allowed
- `Plugin "valksor/php-plugin" could not be found` - Plugin not properly installed

### Recipes Not Processing

**Problem**: Packages with recipes are installed but recipes are not applied.

**Solutions**:

1. **Check Plugin Permissions**:
   ```bash
   composer config allow-plugins
   ```

2. **Verify Plugin Configuration**:
   ```bash
   # Check valksor configuration in composer.json
   cat composer.json | jq '.extra.valksor'
   ```

3. **Manually Process Recipes**:
   ```bash
   composer valksor:install vendor/package
   ```

4. **Check for Recipe Directory**:
   ```bash
   # Verify package has recipe directory
   find vendor/package -name "recipe" -type d
   find vendor/package/recipe -name "manifest.json"
   ```

### Debug Commands

```bash
# Check plugin status
composer show valksor/php-plugin

# Verify plugin commands are available
composer list | grep valksor

# Check symfony.lock for recipe tracking
ls -la symfony.lock
cat symfony.lock | jq '."vendor/package"'

# Test plugin manually
composer valksor:install --help
```

### Common Issues and Solutions

| Issue                         | Cause                                 | Solution                                                                     |
|-------------------------------|---------------------------------------|------------------------------------------------------------------------------|
| Plugin blocked by Composer    | Security feature in Composer 2.2+     | `composer config allow-plugins.valksor/php-plugin true`                      |
| Recipes not found             | Package doesn't have recipe directory | Contact package maintainer or create custom recipe                           |
| Manual recipe install fails   | Package not installed or wrong name   | Use correct vendor/package format: `composer valksor:install vendor/package` |
| Recipe uninstall doesn't work | Recipe not tracked in symfony.lock    | Ensure recipe was properly installed first                                   |

### Getting Help

```bash
# Show plugin version and info
composer show valksor/php-plugin

# Check available commands
composer list | grep valksor

# Get help for specific commands
composer valksor:install --help
composer valksor:uninstall --help
```

## Integration with Symfony Flex

ValksorPlugin is built on top of Symfony Flex and:

- Uses Symfony Flex's configurator system for recipe processing
- Maintains compatibility with existing Symfony recipes
- Uses the same `symfony.lock` file format
- Supports all Symfony Flex recipe features

## Requirements

- PHP 8.4 or higher
- Composer 2.0 or higher
- Symfony Flex (as dependency)

## License

This package is part of the Valksor package. See the LICENSE file for copyright information.
