# Architecture Overview

The Valksor PHP Plugin is built as a Composer plugin that hooks into the package lifecycle to automatically process local recipes. This document provides a high-level overview of the system architecture.

## Core Components

```
┌─────────────────────────────────────────────────────────────────┐
│                         Composer                                │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │                   ValksorFlex Plugin                       │ │
│  │  ┌──────────────────────────────────────────────────────┐ │ │
│  │  │              Event Subscriber                         │ │ │
│  │  │  • post-package-install   → onPostPackageInstall    │ │ │
│  │  │  • post-package-update    → onPostPackageUpdate     │ │ │
│  │  │  • pre-package-uninstall  → onPrePackageUninstall   │ │ │
│  │  └──────────────────────────────────────────────────────┘ │ │
│  │                           ↓                               │ │
│  │  ┌──────────────────────────────────────────────────────┐ │ │
│  │  │                 RecipeHandler                        │ │ │
│  │  │  • Discover local recipes                           │ │ │
│  │  │  • Parse manifest.json                              │ │ │
│  │  │  • Apply via Symfony Flex Configurator              │ │ │
│  │  └──────────────────────────────────────────────────────┘ │ │
│  └────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│                      Symfony Flex                              │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │                    Configurator                            │ │
│  │  • Register bundles                                      │ │
│  │  • Copy files from recipe                                │ │
│  │  • Update .env files                                     │ │
│  │  • Run post-install commands                            │ │
│  └────────────────────────────────────────────────────────────┘ │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │                        Lock                               │ │
│  │  • Manage symfony.lock file                              │ │
│  │  • Track installed recipes                              │ │
│  └────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
```

## Component Responsibilities

### ValksorFlex (Main Plugin)

- **Implements:** `PluginInterface`, `EventSubscriberInterface`, `CommandProvider`, `Capable`
- **Responsibilities:**
  - Register as event subscriber on activation
  - Listen to package lifecycle events
  - Provide Composer commands
  - Track processed packages to avoid duplicates

### RecipeHandler (Core Logic)

- **Responsibilities:**
  - Discover local recipes in package directories
  - Parse recipe manifests and file structures
  - Integrate with Symfony Flex's configurator system
  - Manage recipe lifecycle (install/update/uninstall)
  - Handle lock file management

### Commands (CLI Interface)

- **ValksorRecipesInstallCommand** - Manual recipe installation
- **ValksorRecipesUninstallCommand** - Manual recipe removal
- **AbstractValksorRecipeCommand** - Shared command logic

## Data Flow

### Package Installation

```
1. composer require vendor/package
                ↓
2. Composer installs package to vendor/
                ↓
3. post-package-install event fires
                ↓
4. ValksorFlex.onPostPackageInstall() triggered
                ↓
5. RecipeHandler.processPackage() called
                ↓
6. Check if package allowed in config
                ↓
7. Search for recipe/manifest.json
                ↓
8. If found: Apply via Symfony Flex Configurator
                ↓
9. Update symfony.lock
```

### Package Uninstallation

```
1. composer remove vendor/package
                ↓
2. pre-package-uninstall event fires
                ↓
3. ValksorFlex.onPrePackageUninstall() triggered
                ↓
4. RecipeHandler.uninstallPackage() called
                ↓
5. Check if package has recipe in lock
                ↓
6. Remove from symfony.lock
                ↓
7. Unconfigure via Symfony Flex (remove files, env vars, etc.)
```

## Key Design Decisions

### Local Recipe Focus

The plugin specifically focuses on **local recipes**—recipes embedded within package directories. This differs from Symfony Flex's centralized recipe repository approach.

**Benefits:**
- No external recipe server required
- Recipes version-controlled with packages
- Works in offline environments
- Simpler deployment model

### Symfony Flex Compatibility

By building on Symfony Flex's configurator system, the plugin:

- Supports the full recipe manifest format
- Leverages battle-tested configuration logic
- Maintains compatibility with existing Symfony projects

### Event-Driven Architecture

The plugin uses Composer's event system for:

- Automatic processing during normal operations
- No manual intervention required
- Consistent with Composer plugin best practices

## Configuration Flow

```
composer.json
      ↓
extra.valksor.allow
      ↓
┌─────────────┬────────────────┐
│  allow: "*" │  Specific list │
│  (all pkgs) │  of packages   │
└─────────────┴────────────────┘
      ↓
RecipeHandler.isPackageAllowed()
      ↓
Process or skip package
```

## See Also

- [Recipe System](recipe-system.md) - Recipe format and processing
- [Event Flow](event-flow.md) - Detailed event handling
