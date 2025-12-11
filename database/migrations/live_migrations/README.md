# Live Migrations Folder

## Purpose

This folder contains migrations that are **already applied to the production/live database** and should **NOT** be run automatically when executing `php artisan migrate`.

## Why?

When setting up a new development environment or fresh database, running `php artisan migrate` would attempt to run all migrations, including ones that have already been applied to the live database. This can cause errors or conflicts.

## How It Works

The `AppServiceProvider` has been configured to automatically exclude any migrations in this `live_migrations` folder when running migrations.

## Manual Execution

If you need to run these migrations manually (e.g., for a specific environment), you can:

1. Temporarily move them back to the main `migrations` folder
2. Run the specific migration: `php artisan migrate --path=database/migrations/live_migrations/2025_12_10_153234_add_formula_and_reserve_columns_to_marketplace_stock_table.php`
3. Move them back to `live_migrations` folder

## Current Migrations in This Folder

- `2025_12_10_153234_add_formula_and_reserve_columns_to_marketplace_stock_table.php` - Adds formula and reserve columns to marketplace_stock table
- `2025_12_11_120319_update_marketplace_table_add_columns.php` - Adds description, status, api_secret, api_url, and timestamps to marketplace table

---

**Note:** Migrations in this folder are excluded from automatic migration runs but are still tracked in version control for reference.

