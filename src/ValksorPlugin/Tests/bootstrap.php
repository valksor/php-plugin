<?php declare(strict_types = 1);

/*
 * This file is part of the Valksor package.
 *
 * (c) Davis Zalitis (k0d3r1s)
 * (c) SIA Valksor <packages@valksor.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

// Set error reporting for tests
error_reporting(E_ALL);

$autoloadPath = __DIR__ . '/../vendor/autoload.php';

if (!is_file($autoloadPath)) {
    throw new RuntimeException('Composer autoload file not found. Run "composer install" first.');
}

require $autoloadPath;

if (!file_exists($autoloadPath)) {
    throw new LogicException('Dependencies not installed. Please run "composer install" in the plugin root directory.');
}

require_once $autoloadPath;

// Define test constants if not already defined
if (!defined('TEST_FIXTURES_DIR')) {
    define('TEST_FIXTURES_DIR', __DIR__ . '/Fixtures');
}

if (!defined('TEST_TEMP_DIR')) {
    define('TEST_TEMP_DIR', sys_get_temp_dir() . '/valksor_plugin_tests');
}

// Create temp directory if it doesn't exist
if (!is_dir(TEST_TEMP_DIR)) {
    mkdir(TEST_TEMP_DIR, 0o777, true);
}

// Don't convert errors to exceptions - let tests run without interference

// Cleanup function for temp files
register_shutdown_function(
    static function (): void {
        // Clean up any temporary test files if needed
        if (is_dir(TEST_TEMP_DIR)) {
            foreach (glob(TEST_TEMP_DIR . '/*') as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    },
);

// Mock some functions that might cause issues in tests
if (!function_exists('is_executable')) {
    function is_executable(
        string $filename,
    ): bool {
        return false; // Default to false for tests
    }
}
