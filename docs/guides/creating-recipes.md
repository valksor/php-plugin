# Creating Recipes

Learn how to create local recipes for your packages.

## Recipe Structure

A recipe is a directory containing a `manifest.json` file and any files that should be copied to the project:

```
your-package/
    composer.json
    src/
        YourCode.php
    recipe/
        manifest.json
        config/
            packages.yaml
            services.yaml
        templates/
            template.html.twig
        public/
            assets/
                style.css
```

## Step 1: Create the Recipe Directory

```bash
cd your-package/
mkdir -p recipe/config
mkdir -p recipe/templates
mkdir -p recipe/public
```

## Step 2: Create manifest.json

The `manifest.json` file defines what the recipe does:

```json
{
    "bundles": {
        "YourVendor\\YourPackage\\Bundle": ["all"]
    },
    "copy-from-recipe": {
        "config/": "%CONFIG_DIR%/",
        "templates/": "%TEMPLATES_DIR%/"
    },
    "env": {
        "YOUR_PACKAGE_URL": "https://api.example.com"
    },
    "post-install-output": [
        "  <info>✓ Your Package has been configured</info>"
    ]
}
```

## Step 3: Add Configuration Files

### Bundles Registration

```json
{
    "bundles": {
        "YourVendor\\YourPackage\\Bundle": ["all"],
        "YourVendor\\YourPackage\\DevBundle": ["dev", "test"]
    }
}
```

### Copy Files from Recipe

Files in the recipe directory can be copied to the project:

```json
{
    "copy-from-recipe": {
        "config/packages/": "%CONFIG_DIR%/packages/",
        "templates/": "%TEMPLATES_DIR%/"
    }
}
```

### Environment Variables

Add variables to `.env` files:

```json
{
    "env": {
        "PACKAGE_API_KEY": "your-default-api-key",
        "PACKAGE_DATABASE_URL": "mysql://localhost/package_db"
    }
}
```

## Complete Example

Here's a complete recipe for a Symfony bundle:

```
recipe/
├── manifest.json
└── config/
    └── packages/
        └── your_package.yaml
```

**manifest.json:**

```json
{
    "bundles": {
        "Acme\\BlogBundle\\AcmeBlogBundle": ["all"]
    },
    "copy-from-recipe": {
        "config/": "%CONFIG_DIR%/"
    },
    "env": {
        "BLOG_DB_URL": "mysql://localhost/blog"
    },
    "gitignore": [
        "/config/blog/secrets.yaml"
    ],
    "post-install-output": [
        "  <info>✓ Acme Blog Bundle configured</info>",
        "  <comment>Run: php bin/console blog:install</comment>"
    ]
}
```

**config/packages/your_package.yaml:**

```yaml
acme_blog:
  db_url: '%env(BLOG_DB_URL)%'
  cache:
    enabled: true
```

## Best Practices

### 1. Default Values

Provide sensible defaults in environment variables:

```json
{
    "env": {
        "BLOG_CACHE_TTL": "3600",
        "BLOG_ENABLED": "true"
    }
}
```

### 2. Conditional Configuration

Use environment-specific bundles:

```json
{
    "bundles": {
        "Acme\\BlogBundle": ["all"],
        "Acme\\BlogBundle\\Dev": ["dev", "test"]
    }
}
```

### 3. User Feedback

Always provide post-install output:

```json
{
    "post-install-output": [
        "  <info>✓ Package installed</info>",
        "  <comment>Next steps:</comment>",
        "  <comment>1. Configure your .env file</comment>",
        "  <comment>2. Run: php bin/console cache:clear</comment>"
    ]
}
```

### 4. File Organization

Organize recipe files by purpose:

```
recipe/
├── manifest.json
├── config/           # Configuration files
├── templates/        # Twig templates
├── public/           # Web assets
└── migrations/       # Database migrations
```

## Testing Your Recipe

```bash
# Install your package locally
composer install

# Check if recipe was applied
cat symfony.lock

# Re-apply the recipe
composer valksor:install vendor/package

# Remove the recipe
composer valksor:uninstall vendor/package
```

## Troubleshooting

### Recipe Not Applied

1. Check the plugin is allowed:
   ```bash
   composer config extra.valksor
   ```

2. Verify the recipe directory exists:
   ```bash
   ls vendor/your-package/recipe/
   ```

3. Check manifest.json syntax:
   ```bash
   cat vendor/your-package/recipe/manifest.json | jq
   ```

### Files Not Copied

1. Verify the `copy-from-recipe` paths
2. Check directory tokens are correct
3. Ensure source files exist in the recipe directory

### Bundle Not Registered

1. Check bundle namespace is correct
2. Verify bundle class is autoloadable
3. Check the environment list (`all`, `dev`, `prod`, `test`)

## See Also

- [Recipe System](../architecture/recipe-system.md) - Recipe format reference
- [Advanced Usage](advanced-usage.md) - Advanced configuration options
