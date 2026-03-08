# WordPress Test Suite Setup for KHM Plugin

## Overview

The KHM Plugin membership tests require a WordPress Test Suite environment for proper integration testing. The current PHPUnit bootstrap provides basic mocking, but database-dependent tests need a real WordPress test database.

---

## Prerequisites

- PHP 8.1+
- MySQL/MariaDB
- Composer
- SVN (for WordPress test library)

---

## Installation Steps

### 1. Install WordPress Test Library

```bash
cd /Users/krisoldland/Local\ Sites/touchpoint-template/app/public/wp-content/plugins/khm-plugin

# Install WP Test Suite
composer require --dev yoast/phpunit-polyfills

# Download WordPress test library
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
```

If `bin/install-wp-tests.sh` doesn't exist, create it:

```bash
mkdir -p bin
curl -o bin/install-wp-tests.sh https://raw.githubusercontent.com/wp-cli/scaffold-command/master/templates/install-wp-tests.sh
chmod +x bin/install-wp-tests.sh
```

### 2. Configure Test Database

Edit `bin/install-wp-tests.sh` to match your Local by Flywheel setup:

```bash
DB_NAME=${1-wordpress_test}
DB_USER=${2-root}
DB_PASS=${3-root}  # Local by Flywheel default
DB_HOST=${4-localhost}
WP_VERSION=${5-latest}
```

Run the installer:

```bash
./bin/install-wp-tests.sh wordpress_test root root localhost latest
```

### 3. Update Composer Dependencies

```bash
composer require --dev yoast/phpunit-polyfills
composer dump-autoload
```

### 4. Create WP Test Bootstrap

Create `tests/wp-bootstrap.php`:

```php
<?php
/**
 * WordPress Test Suite Bootstrap
 */

$_tests_dir = getenv('WP_TESTS_DIR');

if (!$_tests_dir) {
    $_tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
}

if (!file_exists($_tests_dir . '/includes/functions.php')) {
    echo "Could not find $_tests_dir/includes/functions.php\n";
    exit(1);
}

// Load WordPress test functions
require_once $_tests_dir . '/includes/functions.php';

// Manually load the plugin
function _manually_load_plugin() {
    require dirname(__DIR__) . '/khm-plugin.php';
}
tests_add_filter('muplugins_loaded', '_manually_load_plugin');

// Start WordPress test suite
require $_tests_dir . '/includes/bootstrap.php';

echo "WordPress Test Suite loaded\n";
```

### 5. Update phpunit.xml

Replace the current `phpunit.xml` with WP-specific configuration:

```xml
<?xml version="1.0"?>
<phpunit
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/9.5/phpunit.xsd"
    bootstrap="tests/wp-bootstrap.php"
    colors="true"
    verbose="true"
    stopOnFailure="false">
    <testsuites>
        <testsuite name="Unit Tests">
            <directory>tests/GEO</directory>
            <directory>tests/Sponsors</directory>
        </testsuite>
        <testsuite name="Membership Integration Tests">
            <directory>tests/Membership</directory>
        </testsuite>
    </testsuites>
    <php>
        <env name="WP_TESTS_DIR" value="/tmp/wordpress-tests-lib"/>
        <env name="WP_PHPUNIT__TESTS_CONFIG" value="tests/wp-config-test.php"/>
    </php>
</phpunit>
```

### 6. Update Membership Tests

Modify test classes to extend `WP_UnitTestCase` instead of `PHPUnit\Framework\TestCase`:

```php
<?php
namespace KHM\Tests\Membership;

use WP_UnitTestCase;  // Changed from PHPUnit\Framework\TestCase
use KHM\Membership\AttributionEndpoint;

class AttributionEndpointTest extends WP_UnitTestCase {
    // ... existing tests
}
```

---

## Running Tests

### Run All Tests
```bash
./vendor/bin/phpunit
```

### Run Membership Tests Only
```bash
./vendor/bin/phpunit --testsuite "Membership Integration Tests"
```

### Run Specific Test Class
```bash
./vendor/bin/phpunit tests/Membership/AttributionEndpointTest.php
```

### Run with Coverage
```bash
./vendor/bin/phpunit --coverage-html coverage/
```

---

## Alternative: Docker-Based WordPress Testing

If Local by Flywheel integration is complex, use Docker:

### docker-compose.test.yml

```yaml
version: '3.8'

services:
  wordpress-test:
    image: wordpress:latest
    environment:
      WORDPRESS_DB_HOST: db-test
      WORDPRESS_DB_USER: wordpress
      WORDPRESS_DB_PASSWORD: wordpress
      WORDPRESS_DB_NAME: wordpress_test
    volumes:
      - ./:/var/www/html/wp-content/plugins/khm-plugin

  db-test:
    image: mariadb:10.6
    environment:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: wordpress_test
      MYSQL_USER: wordpress
      MYSQL_PASSWORD: wordpress
```

### Run Tests in Docker

```bash
docker-compose -f docker-compose.test.yml up -d
docker-compose -f docker-compose.test.yml exec wordpress-test bash
cd /var/www/html/wp-content/plugins/khm-plugin
./vendor/bin/phpunit
```

---

## Expected Results After Setup

Once WordPress Test Suite is configured:

```
PHPUnit 9.6.29 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.1.33
Configuration: phpunit.xml

.........................                                         25 / 25 (100%)

Time: 00:02.156, Memory: 64.00 MB

OK (25 tests, 87 assertions)
```

---

## Troubleshooting

### Error: "Could not find /tmp/wordpress-tests-lib"

Run the install script:
```bash
./bin/install-wp-tests.sh wordpress_test root root localhost latest
```

### Error: "Database connection failed"

Check MySQL is running:
```bash
mysql -u root -p -e "SHOW DATABASES;"
```

Create test database manually:
```bash
mysql -u root -p -e "CREATE DATABASE wordpress_test;"
```

### Error: "Class WP_UnitTestCase not found"

Install WordPress test library:
```bash
composer require --dev yoast/phpunit-polyfills
```

---

## Integration with CI/CD

### GitHub Actions Workflow

```yaml
name: PHPUnit Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest

    services:
      mysql:
        image: mariadb:10.6
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: wordpress_test
        options: >-
          --health-cmd="mysqladmin ping"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=3

    steps:
      - uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
          extensions: mysqli, mbstring

      - name: Install dependencies
        run: composer install --prefer-dist --no-progress

      - name: Install WordPress Test Suite
        run: bash bin/install-wp-tests.sh wordpress_test root root 127.0.0.1 latest

      - name: Run PHPUnit
        run: ./vendor/bin/phpunit
```

---

## Resources

- [WordPress Plugin Handbook - PHPUnit](https://developer.wordpress.org/plugins/testing/phpunit/)
- [WP-CLI Scaffold](https://github.com/wp-cli/scaffold-command)
- [WordPress Test Suite on GitHub](https://github.com/WordPress/wordpress-develop/tree/trunk/tests/phpunit)

---

**Status**: Setup required for full integration testing.
**Current**: Basic mocking in `tests/bootstrap.php` (24% pass rate due to mock limitations).
**Target**: WordPress Test Suite with real database (100% pass rate expected).
