# Troubleshooting

Common issues and solutions when using the Valksor PHP Plugin.

## Plugin Not Allowed

**Symptom:**
```
Plugin valksor/php-plugin is blocked by composer.json config allow-plugins
```

**Solution:**
```bash
composer config allow-plugins.valksor/php-plugin true
```

**Explanation:** Composer 2.2+ requires explicit permission for plugins.

---

## Recipes Not Processing

**Symptom:**
Recipes are not being applied during package installation.

**Possible Causes:**

### 1. Plugin Not Configured

**Check:**
```bash
composer config extra.valksor
```

**Solution:**
```json
{
    "extra": {
        "valksor": {
            "allow": "*"
        }
    }
}
```

### 2. Package Not in Allow List

**Check:**
```bash
composer config extra.valksor.allow
```

**Solution:** Add your package to the allow list or use `"*"` for all packages.

### 3. No Recipe Directory

**Check:**
```bash
ls vendor/your-package/recipe/
```

**Solution:** Create the `recipe/` directory with a `manifest.json` file.

---

## Invalid manifest.json

**Symptom:**
```
Warning: Invalid manifest.json in vendor/your-package/recipe/
```

**Solution:**

Validate your JSON:
```bash
cat vendor/your-package/recipe/manifest.json | jq
```

Common issues:
- Trailing commas
- Missing quotes around keys
- Single quotes instead of double quotes
- Missing closing braces

---

## Files Not Copied

**Symptom:**
Recipe is applied but files are not copied to the project.

**Possible Causes:**

### 1. Incorrect Path in manifest.json

**Check:**
```json
{
    "copy-from-recipe": {
        "config/": "%CONFIG_DIR%/"  // Note trailing slashes
    }
}
```

**Solution:** Ensure both source and destination paths have trailing slashes.

### 2. Files Not in Recipe Directory

**Check:**
```bash
ls -la vendor/your-package/recipe/
```

**Solution:** Files must be in the `recipe/` directory, not the package root.

### 3. Permission Issues

**Check:**
```bash
ls -la config/ templates/
```

**Solution:** Ensure you have write permissions to target directories.

---

## Bundle Not Registered

**Symptom:**
Bundle is listed in manifest.json but not in `bundles.php`.

**Possible Causes:**

### 1. Incorrect Namespace

**Check:** Verify the bundle class namespace matches the actual class:
```json
{
    "bundles": {
        "YourVendor\\\\YourBundle\\\\YourBundle": ["all"]
    }
}
```

**Note:** Double backslashes are required in JSON.

### 2. Bundle Class Not Found

**Check:**
```bash
composer dump-autoload
```

**Solution:** Ensure the bundle class is properly autoloaded.

### 3. Environment Mismatch

**Check:** What environment are you running?
```bash
php bin/console about | grep env
```

**Solution:** Ensure the bundle is registered for your environment (`all`, `dev`, `prod`, `test`).

---

## Environment Variables Not Added

**Symptom:**
Variables listed in `manifest.json` are not in `.env` files.

**Possible Causes:**

### 1. Variable Already Exists

**Behavior:** The plugin never overwrites existing environment variables.

**Solution:** Manually edit `.env` or `.env.local` to change the value.

### 2. Invalid Variable Name

**Check:** Ensure variable names follow environment variable conventions:
```json
{
    "env": {
        "VALID_VARIABLE_NAME": "value"
    }
}
```

---

## symfony.lock Issues

### Corrupted Lock File

**Symptom:**
```bash
Error parsing symfony.lock
```

**Solution:**
```bash
# Backup current lock
cp symfony.lock symfony.lock.backup

# Re-create lock
composer valksor:install
```

### Stale Lock Entry

**Symptom:** Recipe shows as installed but package was removed.

**Solution:**
```bash
# Manually remove the entry
jq 'del."vendor/package"' symfony.lock > symfony.lock.tmp
mv symfony.lock.tmp symfony.lock
```

---

## Debug Commands

### Enable Verbose Output

```bash
composer valksor:install -vvv
```

### Check Plugin Status

```bash
# All-in-one status check
echo "=== Plugin Installed ===" && \
composer show valksor/php-plugin && \
echo -e "\n=== Plugin Allowed ===" && \
composer config allow-plugins | grep valksor && \
echo -e "\n=== Plugin Config ===" && \
composer config extra.valksor && \
echo -e "\n=== Available Commands ===" && \
composer list | grep valksor
```

### View Recipe Processing

```bash
# Enable composer event debugging
COMPOSER_DEBUG_EVENTS=1 composer require vendor/package
```

---

## Getting Help

If you're still experiencing issues:

1. **Check existing issues:** [GitHub Issues](https://github.com/valksor/php-plugin/issues)
2. **Create a minimal reproduction:** Isolate the problem
3. **Gather information:**
   - PHP version: `php -v`
   - Composer version: `composer --version`
   - Plugin version: `composer show valksor/php-plugin`
   - Error messages
4. **Report:** Create a new issue with all gathered information

## See Also

- [Configuration](../getting-started/configuration.md) - Configuration options
- [Creating Recipes](creating-recipes.md) - Recipe creation guide
