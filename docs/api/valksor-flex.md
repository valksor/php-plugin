# ValksorFlex

`ValksorFlex` is the main Composer plugin class that orchestrates recipe processing. It implements multiple Composer interfaces to provide automatic recipe discovery and processing.

## Implements

- `PluginInterface` - Core Composer plugin interface
- `EventSubscriberInterface` - Hooks into package lifecycle events
- `CommandProvider` - Provides Composer commands
- `Capable` - Declares plugin capabilities

## Public Methods

### activate(Composer $composer, IOInterface $io): void

Called by Composer when the plugin is activated. Registers the plugin as an event subscriber.

```php
$plugin->activate($composer, $io);
```

**Parameters:**
- `$composer` - The Composer instance
- `$io` - The I/O interface for user interaction

### deactivate(Composer $composer, IOInterface $io): void

Called by Composer when the plugin is deactivated. Currently no cleanup is needed.

### getCommands(): array<BaseCommand>

Returns the list of Composer commands provided by this plugin.

**Returns:** Array of command instances

**Available Commands:**
- `ValksorRecipesInstallCommand` - Install recipes manually
- `ValksorRecipesUninstallCommand` - Remove recipes manually

### getSubscribedEvents(): array<string, string>

Returns the list of Composer events this plugin subscribes to.

**Returns:** Array mapping event names to handler methods

**Events:**
| Event | Handler | Purpose |
|-------|---------|---------|
| `post-package-install` | `onPostPackageInstall` | Apply recipes after install |
| `post-package-update` | `onPostPackageUpdate` | Re-apply recipes after update |
| `pre-package-uninstall` | `onPrePackageUninstall` | Remove recipes before uninstall |

## Event Handlers

### onPostPackageInstall(PackageEvent $event): void

Automatically discovers and applies local recipes for newly installed packages. Tracks processed packages to avoid handling duplicates (e.g., dev-master/9999999-dev aliases).

### onPostPackageUpdate(PackageEvent $event): void

Re-applies local recipes for updated packages to ensure configuration stays in sync with the new package version.

### onPrePackageUninstall(PackageEvent $event): void

Removes recipes for packages that are about to be uninstalled. Ensures proper cleanup of configuration files and settings.

## Usage Example

The plugin works automatically - no direct usage needed. It's activated by Composer and responds to package lifecycle events.

```php
// The plugin is instantiated and activated by Composer
// based on the composer.json plugin declaration.
```

## See Also

- [RecipeHandler](recipe-handler.md) - Recipe processing logic
- [Commands](commands.md) - CLI commands
