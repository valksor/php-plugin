# Event Flow

The Valksor PHP Plugin integrates with Composer's event system to automatically process recipes during package lifecycle operations.

## Event Subscription

The plugin subscribes to three Composer events:

```php
public static function getSubscribedEvents(): array
{
    return [
        'post-package-install' => 'onPostPackageInstall',
        'post-package-update' => 'onPostPackageUpdate',
        'pre-package-uninstall' => 'onPrePackageUninstall',
    ];
}
```

## Install Event Flow

```
composer require vendor/package
        │
        ▼
┌─────────────────────────────────────────────────────────────┐
│ 1. Composer downloads and installs the package               │
└─────────────────────────────────────────────────────────────┘
        │
        ▼
┌─────────────────────────────────────────────────────────────┐
│ 2. post-package-install event fires                         │
└─────────────────────────────────────────────────────────────┘
        │
        ▼
┌─────────────────────────────────────────────────────────────┐
│ 3. ValksorFlex.onPostPackageInstall()                       │
│     - Extracts package from InstallOperation                │
└─────────────────────────────────────────────────────────────┘
        │
        ▼
┌─────────────────────────────────────────────────────────────┐
│ 4. Check if package already processed (duplicate guard)     │
│     - Uses $processedPackages array to track                │
└─────────────────────────────────────────────────────────────┘
        │
        ▼ No
┌─────────────────────────────────────────────────────────────┐
│ 5. RecipeHandler.processPackage(package, 'install')         │
│     - Check if package allowed in config                    │
│     - Search for recipe/manifest.json                       │
│     - Parse manifest and load files                         │
└─────────────────────────────────────────────────────────────┘
        │
        ▼ Recipe found
┌─────────────────────────────────────────────────────────────┐
│ 6. Initialize Symfony Flex objects                         │
│     - Create Lock instance                                  │
│     - Build Options with directory structure                │
│     - Create Configurator                                   │
└─────────────────────────────────────────────────────────────┘
        │
        ▼
┌─────────────────────────────────────────────────────────────┐
│ 7. Apply recipe via Symfony Flex Configurator               │
│     - Register bundles in bundles.php                       │
│     - Copy files from recipe directory                      │
│     - Add environment variables to .env                     │
│     - Update .gitignore                                     │
│     - Display post-install output                          │
└─────────────────────────────────────────────────────────────┘
        │
        ▼
┌─────────────────────────────────────────────────────────────┐
│ 8. Write symfony.lock with recipe record                   │
└─────────────────────────────────────────────────────────────┘
```

## Update Event Flow

```
composer update vendor/package
        │
        ▼
┌─────────────────────────────────────────────────────────────┐
│ 1. Composer downloads and installs the new version          │
└─────────────────────────────────────────────────────────────┘
        │
        ▼
┌─────────────────────────────────────────────────────────────┐
│ 2. post-package-update event fires                          │
└─────────────────────────────────────────────────────────────┘
        │
        ▼
┌─────────────────────────────────────────────────────────────┐
│ 3. ValksorFlex.onPostPackageUpdate()                        │
│     - Extracts target package from UpdateOperation          │
└─────────────────────────────────────────────────────────────┘
        │
        ▼
┌─────────────────────────────────────────────────────────────┐
│ 4. Same process as install, but with 'update' operation     │
│     - Allows recipes to handle updates differently          │
│     - Re-applies recipe to ensure configuration sync        │
└─────────────────────────────────────────────────────────────┘
```

## Uninstall Event Flow

```
composer remove vendor/package
        │
        ▼
┌─────────────────────────────────────────────────────────────┐
│ 1. pre-package-uninstall event fires (before removal)        │
└─────────────────────────────────────────────────────────────┘
        │
        ▼
┌─────────────────────────────────────────────────────────────┐
│ 2. ValksorFlex.onPrePackageUninstall()                      │
│     - Extracts package from UninstallOperation              │
└─────────────────────────────────────────────────────────────┘
        │
        ▼
┌─────────────────────────────────────────────────────────────┐
│ 3. Check if package already processed (duplicate guard)     │
└─────────────────────────────────────────────────────────────┘
        │
        ▼ No
┌─────────────────────────────────────────────────────────────┐
│ 4. RecipeHandler.uninstallPackage(package)                  │
│     - Check if package allowed in config                    │
│     - Search for recipe/manifest.json                       │
└─────────────────────────────────────────────────────────────┘
        │
        ▼ Recipe found
┌─────────────────────────────────────────────────────────────┐
│ 5. Remove recipe from symfony.lock FIRST                    │
│     - Critical: must be before unconfigure                  │
│     - Allows Options::getRemovableFiles() to work           │
└─────────────────────────────────────────────────────────────┘
        │
        ▼
┌─────────────────────────────────────────────────────────────┐
│ 6. Unconfigure via Symfony Flex Configurator                │
│     - Remove bundles from bundles.php                       │
│     - Remove files copied from recipe                       │
│     - Remove environment variables from .env                │
│     - Clean .gitignore entries                              │
└─────────────────────────────────────────────────────────────┘
        │
        ▼
┌─────────────────────────────────────────────────────────────┐
│ 7. Write updated symfony.lock                              │
└─────────────────────────────────────────────────────────────┘
        │
        ▼
┌─────────────────────────────────────────────────────────────┐
│ 8. Composer completes package removal                      │
└─────────────────────────────────────────────────────────────┘
```

## Duplicate Prevention

The plugin tracks processed packages to avoid handling aliases:

```php
private array $processedPackages = [];

// In event handlers:
if (isset($this->processedPackages[$packageName])) {
    return; // Skip duplicate
}
$this->processedPackages[$packageName] = true;
```

**Why this matters:**
- Composer creates package aliases (e.g., `dev-master`, `9999999-dev`)
- Without tracking, the same recipe could be applied multiple times
- This check ensures each recipe is processed exactly once

## Error Handling

The plugin handles errors gracefully:

| Error Type | Handling |
|------------|----------|
| Package not allowed | Silently skip |
| No recipe found | Silently skip |
| Invalid manifest.json | Warning message, skip |
| Recipe application error | Pass through Symfony Flex error |

## See Also

- [Overview](overview.md) - System architecture
- [Recipe System](recipe-system.md) - Recipe format and processing
