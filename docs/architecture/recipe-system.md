# Recipe System

The Valksor PHP Plugin uses a recipe system compatible with Symfony Flex. Recipes are stored locally within package directories and define how packages should be configured when installed.

## Recipe Location

Recipes are stored in the `recipe/` directory within a package:

```
vendor/package/
    composer.json
    src/
        PackageCode.php
    recipe/
        manifest.json
        config/
            packages.yaml
        templates/
            template.php.twig
        public/
            assets/
```

## Manifest Format

The `manifest.json` file defines the recipe configuration:

```json
{
    "bundles": {
        "Vendor\\Package\\Bundle": ["all"]
    },
    "copy-from-recipe": {
        "config/": "%CONFIG_DIR%/",
        "templates/": "%TEMPLATES_DIR%/"
    },
    "copy-from-package": {
        "public/": "%PUBLIC_DIR%/assets/"
    },
    "env": {
        "DATABASE_URL": "mysql://localhost/db",
        "API_KEY": "your-api-key-here"
    },
    "gitignore": [
        "/.env.local",
        "/config/secrets/"
    ],
    "post-install-output": [
        "  <info>✓ Package configured successfully</info>",
        "  <comment>Update your .env file with the API key</comment>"
    ]
}
```

## Manifest Sections

### bundles

Register Symfony bundles for auto-loading:

```json
{
    "bundles": {
        "Vendor\\Package\\Bundle": ["all"],
        "Vendor\\Package\\DevBundle": ["dev", "test"]
    }
}
```

**Environment values:** `all`, `dev`, `prod`, `test`

### copy-from-recipe

Copy files from the recipe directory to the project:

```json
{
    "copy-from-recipe": {
        "config/": "%CONFIG_DIR%/",
        "templates/": "%TEMPLATES_DIR%/",
        "public/": "%PUBLIC_DIR%/"
    }
}
```

**Available tokens:**
- `%CONFIG_DIR%` - config directory (default: `config/`)
- `%TEMPLATES_DIR%` - templates directory (default: `templates/`)
- `%PUBLIC_DIR%` - public directory (default: `public/`)
- `%SRC_DIR%` - source directory (default: `src/`)
- `%VAR_DIR%` - var directory (default: `var/`)
- `%ROOT_DIR%` - project root

### copy-from-package

Copy files from the package itself (not the recipe):

```json
{
    "copy-from-package": {
        "public/assets/": "%PUBLIC_DIR%/vendor/package/"
    }
}
```

### env

Define environment variables to add to `.env` files:

```json
{
    "env": {
        "PACKAGE_DATABASE_URL": "mysql://localhost/package_db",
        "PACKAGE_VERSION": "1.0.0"
    }
}
```

**Behavior:**
- Adds to `.env` and `.env.local`
- Does not overwrite existing values
- Comments are added for documentation

### gitignore

Add entries to `.gitignore`:

```json
{
    "gitignore": [
        "/config/package/secrets.yaml",
        "/data/package/"
    ]
}
```

### post-install-output

Output messages after recipe installation:

```json
{
    "post-install-output": [
        "  <info>✓ Package configured</info>",
        "  <comment>Run: php bin/console package:setup</comment>"
    ]
}
```

**Available formatting:** Symfony Console output tags (`<info>`, `<comment>`, `<error>`, etc.)

## Recipe Processing

### Discovery

```php
// RecipeHandler searches for:
$installPath . '/recipe/manifest.json'
```

### Validation

1. Check package is allowed in configuration
2. Verify recipe directory exists
3. Parse and validate `manifest.json`
4. Load recipe files

### Application

1. Add recipe to `symfony.lock`
2. Apply configuration via Symfony Flex Configurator:
   - Register bundles
   - Copy files from recipe
   - Copy files from package
   - Add environment variables
   - Update `.gitignore`
   - Display post-install output
3. Write updated `symfony.lock`

### Removal

1. Remove recipe from `symfony.lock`
2. Unconfigure via Symfony Flex:
   - Unregister bundles
   - Remove copied files
   - Remove environment variables
   - Clean `.gitignore` entries

## Recipe Lock File

The `symfony.lock` file tracks installed recipes:

```json
{
    "vendor/package": {
        "version": "1.0.0"
    }
}
```

**Purposes:**
- Track which recipes have been applied
- Enable proper cleanup on uninstall
- Prevent duplicate processing

## Directory Tokens Reference

| Token | Default | Description |
|-------|---------|-------------|
| `%CONFIG_DIR%` | `config/` | Configuration files |
| `%TEMPLATES_DIR%` | `templates/` | Twig templates |
| `%PUBLIC_DIR%` | `public/` | Web-accessible files |
| `%SRC_DIR%` | `src/` | PHP source code |
| `%VAR_DIR%` | `var/` | Cache, logs, sessions |
| `%ROOT_DIR%` | `.` | Project root |

## See Also

- [Creating Recipes](../guides/creating-recipes.md) - How to create recipes
- [Overview](overview.md) - System architecture
