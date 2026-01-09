# Advanced Usage

Advanced configuration and usage patterns for the Valksor PHP Plugin.

## Selective Recipe Processing

Control which packages are allowed to have recipes processed:

### Allow All Packages

```json
{
    "extra": {
        "valksor": {
            "allow": "*"
        }
    }
}
```

### Allow Specific Packages

```json
{
    "extra": {
        "valksor": {
            "allow": {
                "valksor/valksor-bundle": {},
                "my-vendor/core-package": {}
            }
        }
    }
}
```

## Recipe Overrides

Enable recipe overrides for development:

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

**When to use:**
- Active development on a package
- Testing recipe changes
- Frequent updates to recipe files

**Effect:**
- Forces re-application of recipe files
- Overwrites existing configuration
- Useful for iterative development

## Directory Customization

Customize Symfony directory structure:

```json
{
    "extra": {
        "symfony": {
            "root-dir": "src"
        },
        "flex": {
            "src-dir": "src",
            "var-dir": "var",
            "public-dir": "public"
        }
    }
}
```

**Available options:**
| Option | Default | Description |
|--------|---------|-------------|
| `root-dir` | `.` | Project root directory |
| `src-dir` | `src` | Source code directory |
| `var-dir` | `var` | Cache and logs directory |
| `public-dir` | `public` | Web root directory |

## Manual Recipe Commands

### Force Re-apply All Recipes

```bash
composer valksor:install
```

**Use cases:**
- After configuration changes
- After merging conflicts in `symfony.lock`
- When troubleshooting recipe issues

### Install Specific Package Recipe

```bash
composer valksor:install vendor/package
```

**Use cases:**
- Testing a specific recipe
- Re-applying after manual changes
- Debugging recipe issues

### Remove Recipe

```bash
composer valksor:uninstall vendor/package
```

**What it does:**
- Removes configuration files
- Removes environment variables
- Removes bundles from `bundles.php`
- Updates `symfony.lock`

## Working with symfony.lock

### View Locked Recipes

```bash
cat symfony.lock | jq
```

### Manually Edit Lock File

```bash
# Edit the lock file
nano symfony.lock

# Re-apply all recipes
composer valksor:install
```

**Caution:** Manual edits to `symfony.lock` should be done carefully.

### Remove Stale Lock Entry

```bash
# Remove specific package from lock
jq 'del."vendor/package"' symfony.lock > symfony.lock.tmp
mv symfony.lock.tmp symfony.lock
```

## Debugging

### Enable Verbose Output

```bash
composer valksor:install -vvv
```

### Check Plugin Status

```bash
# Check if plugin is installed
composer show valksor/php-plugin

# Check if plugin is allowed
composer config allow-plugins | grep valksor

# Check plugin configuration
composer config extra.valksor
```

### View Recipe Processing

```bash
# Enable composer debug
COMPOSER_DEBUG_EVENTS=1 composer require vendor/package
```

## Multi-Project Setup

### Monorepo Configuration

```json
{
    "extra": {
        "valksor": {
            "allow": {
                "acme/core": {},
                "acme/user-bundle": {},
                "acme/admin-bundle": {}
            }
        }
    }
}
```

### Shared Recipe Repository

Create a shared recipe package:

```json
{
    "name": "acme/recipes",
    "require": {
        "valksor/php-plugin": "^1.0"
    },
    "extra": {
        "valksor": {
            "allow": "*"
        }
    }
}
```

## Integration with CI/CD

### GitHub Actions Example

```yaml
name: Update Recipes

on: [push]

jobs:
  update-recipes:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'

      - name: Install dependencies
        run: composer install

      - name: Re-apply recipes
        run: composer valksor:install

      - name: Verify changes
        run: |
          git diff --exit-code symfony.lock || {
            echo "Recipes updated, committing changes"
            git config user.name "CI Bot"
            git config user.email "ci@example.com"
            git add symfony.lock
            git commit -m "Update recipes"
          }
```

## Performance Optimization

### Speed Up Recipe Processing

1. **Use specific package allow list:**
   ```json
   {
       "extra": {
           "valksor": {
               "allow": {
                   "essential/package": {}
               }
           }
       }
   }
   ```

2. **Disable for CI (if needed):**
   ```bash
   composer config extra.valksor.allow null
   ```

### Reduce Recipe File Size

- Minify CSS/JS files in recipes
- Use separate assets package
- Avoid copying unnecessary files

## Troubleshooting

See [Troubleshooting](troubleshooting.md) for common issues and solutions.

## See Also

- [Configuration](../getting-started/configuration.md) - Configuration options
- [Creating Recipes](creating-recipes.md) - Recipe creation guide
