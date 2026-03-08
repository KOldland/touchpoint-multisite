# Stripe Dependencies and Deploy Requirements

This plugin requires Composer dependencies for Stripe-backed features (including Stripe level import/mirroring).

## Required install step for every environment

From `wp-content/plugins/khm-plugin` run:

```bash
./install-deps.sh
```

Equivalent manual command:

```bash
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
```

If Composer is not installed globally, you can use local Composer:

```bash
php composer.phar install --no-dev --prefer-dist --no-interaction --optimize-autoloader
```

## Repository policy

- Keep `vendor/` out of git.
- Commit `composer.lock` to git after dependency changes so installs are reproducible.

## Deploy pipeline requirement

Add a deploy/build step that runs the install command above before enabling the plugin or running WP admin tasks.

Example deploy stage:

```bash
cd wp-content/plugins/khm-plugin
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
```

## Runtime behavior

If Stripe SDK is missing, Stripe import UI now shows a blocking admin error and disables import actions until dependencies are installed.
