# Configuration

Configure the Valksor PHP Plugin for your project.

## Basic Configuration

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

## Configuration Options

### allow: "*"

Allow all packages to have recipes processed:

```json
{
    "extra": {
        "valksor": {
            "allow": "*"
        }
    }
}
```

### allow: { "vendor/package": {} }

Allow only specific packages:

```json
{
    "extra": {
        "valksor": {
            "allow": {
                "my-vendor/core-package": {},
                "my-vendor/optional-package": {}
            }
        }
    }
}
```

### allow: { "vendor/package": { "allow_override": true } }

Allow recipe overrides for development:

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

## Complete Example

```json
{
    "name": "my-vendor/my-project",
    "require": {
        "valksor/php-plugin": "^1.0"
    },
    "config": {
        "allow-plugins": {
            "valksor/php-plugin": true,
            "symfony/flex": true
        }
    },
    "extra": {
        "valksor": {
            "allow": {
                "valksor/valksor-bundle": {},
                "my-vendor/custom-package": {
                    "allow_override": true
                }
            }
        }
    }
}
```

## Environment Variables

The plugin respects the following environment variables:

| Variable | Purpose |
|----------|---------|
| `COMPOSER_ALLOW_SUPERUSER` | Allow running as root (Docker) |
| `COMPOSER_HOME` | Custom Composer home directory |

## Next Steps

- [API Reference](../api/valksor-flex.md) - API documentation
- [Creating Recipes](../guides/creating-recipes.md) - Build your own recipes
