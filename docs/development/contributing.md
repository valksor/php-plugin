# Contributing

Contributions to the Valksor PHP Plugin are welcome!

## Development Setup

### 1. Clone the Repository

```bash
git clone https://github.com/valksor/php-plugin.git
cd php-plugin
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Run Tests

```bash
vendor/bin/phpunit
```

## Code Standards

All code must follow PSR-12 coding standards:

```bash
# Check code style
vendor/bin/php-cs-fixer fix --dry-run --diff

# Fix code style
vendor/bin/php-cs-fixer fix
```

## Running Tests

```bash
# Run all tests
vendor/bin/phpunit

# Run with coverage
vendor/bin/phpunit --coverage-html=coverage

# Run specific test
vendor/bin/phpunit tests/Unit/ValksorFlexTest.php
```

## Submitting Changes

### 1. Create a Branch

```bash
git checkout -b feature/your-feature-name
```

### 2. Make Your Changes

- Write code following PSR-12 standards
- Add tests for new features
- Update documentation as needed

### 3. Run Tests

```bash
vendor/bin/phpunit
vendor/bin/php-cs-fixer fix
```

### 4. Commit

```bash
git add .
git commit -m "Description of your changes"
```

### 5. Push and Create Pull Request

```bash
git push origin feature/your-feature-name
```

Then create a pull request on GitHub.

## Pull Request Guidelines

- **PSR-12 Coding Standards**: Ensure code follows PSR-12 standards
- **Tests**: Include tests for new features with 50%+ coverage
- **Documentation**: Update documentation (README, docblocks)
- **Commits**: Use clear, descriptive commit messages
- **Branching**: Create feature branches from `master`

## Code Quality

All code must pass:

- **PHPUnit tests** with 50%+ coverage
- **PHP-CS-Fixer** code style checks

## Project Structure

```
src/ValksorPlugin/
├── ValksorFlex.php           # Main plugin class
├── RecipeHandler.php          # Recipe processing logic
├── Command/                   # Composer commands
│   ├── AbstractValksorRecipeCommand.php
│   ├── ValksorRecipesInstallCommand.php
│   └── ValksorRecipesUninstallCommand.php
├── Contracts/                 # Interfaces
│   └── RecipeCommandInterface.php
└── Tests/                     # Test files
    ├── Unit/
    └── bootstrap.php
```

## Reporting Issues

Please use [GitHub Issues](https://github.com/valksor/php-plugin/issues) to report bugs or request features. Include:

- PHP and Composer versions
- Steps to reproduce
- Expected vs actual behavior
- Example configuration if applicable

## Security

If you discover a security vulnerability, please send an email to `packages@valksor.com` instead of using the issue tracker.

## See Also

- [Testing](testing.md) - Running and writing tests
