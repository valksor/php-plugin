# Installation

Install the Valksor PHP Plugin in your PHP project.

## Requirements

- **PHP 8.4** or higher
- **Composer 2.0** or higher
- **Symfony Flex** (will be installed automatically)

## Standard Installation

```bash
composer require valksor/php-plugin
composer config allow-plugins.valksor/php-plugin true
```

## Composer Configuration

Add to your `composer.json`:

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

### What Each Setting Does

| Setting | Purpose |
|---------|---------|
| `allow-plugins.valksor/php-plugin: true` | Required for Composer 2.2+ security model |
| `allow-plugins.symfony/flex: true` | Required for Symfony Flex integration |
| `extra.valksor.allow: "*"` | Allow all packages to have recipes processed |

## Verification

Verify the plugin is installed and allowed:

```bash
# Check plugin is installed
composer show valksor/php-plugin

# Check plugin is allowed
composer config allow-plugins | grep valksor

# List available commands
composer list | grep valksor
```

## Troubleshooting

### Plugin Not Allowed

**Problem**: Plugin is blocked by Composer 2.2+ security feature.

**Solution**:
```bash
composer config allow-plugins.valksor/php-plugin true
```

### Plugin Not Processing Recipes

**Problem**: Recipes are not being processed during package installation.

**Solution**: Check that `extra.valksor.allow` is configured in your `composer.json`.

### Permission Denied

**Problem**: Cannot modify `composer.json`.

**Solution**: Ensure you have write permissions to the file and run:
```bash
chmod u+w composer.json
```

## Next Steps

- [Configuration](configuration.md) - Configure the plugin
- [Quick Start](../quickstart.md) - Quick start guide
