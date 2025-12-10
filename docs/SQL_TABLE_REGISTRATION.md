# Register Existing SQL Tables with the PHP SDK

Expose existing SQL tables (or create them first) as REST collections. Both calls are **superuser-only**.

- `registerSqlTables(array $tables)` – map existing tables to collections without running SQL.
- `importSqlTables(array $definitions)` – optionally run SQL to create tables, then register them. Returns `['created' => [...], 'skipped' => [...]]`.

## Requirements

- Authenticate with a `_superusers` token.
- Each table must contain a `TEXT` primary key column named `id`.
- Missing audit columns (`created`, `updated`, `createdBy`, `updatedBy`) are automatically added so default API rules work.
- Non-system columns are mapped best-effort (text, number, bool, date/time, JSON).

## Basic usage

```php
<?php
require __DIR__ . '/../vendor/autoload.php';

use BosBase\BosBase;

$pb = new BosBase('http://127.0.0.1:8090');
$pb->collection('_superusers')->authWithPassword('admin@example.com', 'password');

$collections = $pb->collections->registerSqlTables(['projects', 'accounts']);

echo implode(', ', array_map(fn($c) => $c['name'], $collections));
// => "projects, accounts"
```

## With request options

```php
$collections = $pb->collections->registerSqlTables(
    ['legacy_orders'],
    query: ['q' => 1],
    headers: ['x-trace-id' => 'reg-123']
);
```

## Create-or-register flow

`importSqlTables()` accepts `['name' => string, 'sql' => ?string]` items, runs the SQL (if provided), and registers collections. Existing collection names are reported under `skipped`.

```php
$result = $pb->collections->importSqlTables([
    [
        'name' => 'legacy_orders',
        'sql' => '
          CREATE TABLE IF NOT EXISTS legacy_orders (
            id TEXT PRIMARY KEY,
            customer_email TEXT NOT NULL
          );
        ',
    ],
    ['name' => 'reporting_view'], // assumes table already exists
]);

echo implode(', ', array_map(fn($c) => $c['name'], $result['created'] ?? []));
print_r($result['skipped'] ?? []);
```

## What it does

- Creates BosBase collection metadata for the provided tables.
- Generates REST endpoints for CRUD against those tables.
- Applies the default API rules (authenticated create; update/delete scoped to the creator).
- Ensures audit columns exist (`created`, `updated`, `createdBy`, `updatedBy`) and leaves all other existing SQL schema and data untouched; no further field mutations or table syncs are performed.
- Marks created collections with `externalTable: true` to distinguish them from regular BosBase-managed tables.

## Troubleshooting

- 400 error: ensure `id` exists as `TEXT PRIMARY KEY` and the table name is not system-reserved (no leading `_`).
- 401/403: confirm you are authenticated as a superuser.
- Default audit fields are auto-added if missing so the default owner rules validate successfully.
