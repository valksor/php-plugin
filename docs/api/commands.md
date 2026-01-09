# Commands

The Valksor PHP Plugin provides two Composer commands for manual recipe management.

## valksor:install

Applies local recipes from package directories for all or a specific installed package.

### Usage

```bash
# Apply recipes for all packages
composer valksor:install

# Apply recipe for a specific package
composer valksor:install vendor/package
```

### Arguments

| Argument | Required | Description |
|----------|----------|-------------|
| `package` | No | Package name (e.g., `valksor/php-plugin`). If not specified, all packages will be processed. |

### Examples

```bash
# Re-apply all recipes
composer valksor:install

# Apply recipe for specific package
composer valksor:install valksor/valksor-bundle
```

### Exit Codes

| Code | Meaning |
|------|---------|
| 0 | Success |
| 1 | Error (missing lock file, package not found, etc.) |

### Output

```
Searching for local recipes to apply...
  - Applying local recipe for valksor/valksor-bundle
Successfully applied 1 local recipe(s).
```

## valksor:uninstall

Removes a local recipe for a specific package.

### Usage

```bash
composer valksor:uninstall vendor/package
```

### Arguments

| Argument | Required | Description |
|----------|----------|-------------|
| `package` | Yes | Package name to uninstall (e.g., `valksor/php-plugin`) |

### Examples

```bash
# Remove recipe for specific package
composer valksor:uninstall valksor/valksor-bundle
```

### What It Does

1. Removes configuration files copied by the recipe
2. Removes environment variables from `.env` files
3. Updates `symfony.lock` file
4. Removes bundles from `bundles.php`

### Output

```
Searching for local recipe to remove for valksor/valksor-bundle...
  - Removing local recipe for valksor/valksor-bundle
Successfully removed local recipe for valksor/valksor-bundle.
```

## AbstractValksorRecipeCommand

Base class for Valksor recipe commands. Provides common functionality:

### Protected Methods

| Method | Description |
|--------|-------------|
| `getHandler(): RecipeHandler` | Get or create the RecipeHandler instance |
| `getIO(): IOInterface` | Get the I/O interface |
| `requireComposer(): Composer` | Get the Composer instance |
| `validateLockFile(): int` | Validate symfony.lock exists |
| `processSpecificPackage(string $name): int` | Process a specific package |

### Public Methods (must be implemented by subclasses)

| Method | Description |
|--------|-------------|
| `processPackage(PackageInterface $package): ?Recipe` | Process a single package |
| `getSuccessMessage(string $packageName): string` | Get success message |

## See Also

- [ValksorFlex](valksor-flex.md) - Main plugin class
- [RecipeHandler](recipe-handler.md) - Recipe processing logic
- [Contracts](contracts.md) - Command interfaces
