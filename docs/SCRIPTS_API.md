# Scripts API - PHP SDK

## Overview

`$pb->scripts` provides superuser-only helpers for storing and managing function code snippets via `/api/scripts`. The backend persists content and auto-increments `version` on updates.

**Table schema**
- `id` (uuidv7, auto-generated)
- `name` (primary key)
- `content` (script body)
- `description` (optional)
- `version` (starts at 1, increments on update)
- `created`, `updated` (ISO timestamps)

## Authentication

Authenticate as a superuser before calling script management APIs:

```php
<?php
require __DIR__ . '/../vendor/autoload.php';

use BosBase\BosBase;

$pb = new BosBase('http://127.0.0.1:8090');
$pb->collection('_superusers')->authWithPassword('admin@example.com', 'password');
```

## Creating a script

`$pb->scripts->create()` creates the table if missing, writes the script, and returns the stored row with `version = 1`.

```php
$python = <<<'PY'
def main():
    print("Hello from functions!")

if __name__ == "__main__":
    main()
PY;

$script = $pb->scripts->create(
    'hello.py',
    $python,
    description: 'Hello from functions!'
);

echo $script['id'];      // uuidv7
echo $script['version']; // 1
```

## Reading scripts

```php
$script = $pb->scripts->get('hello.py');
echo $script['content'];

$all = $pb->scripts->list();
print_r(array_map(fn($s) => [$s['name'], $s['version']], $all));
```

## Updating scripts (auto-versioned)

Updates bump `version` and `updated`.

```php
$updated = $pb->scripts->update('hello.py', content: <<<'PY'
def main():
    print("Hi from functions!")

if __name__ == "__main__":
    main()
PY,
    description: 'Tweaked output'
);

echo $updated['version']; // previous version + 1

// description-only update
$pb->scripts->update('hello.py', description: 'Docs-only tweak');
```

## Executing scripts

Runs the stored script through `/api/scripts/{name}/execute`. Execution permission is controlled by `$pb->scriptsPermissions`.
- `anonymous`: anyone can execute
- `user`: authenticated users (and superusers)
- `superuser`: only superusers (default when no permission row exists)

```php
$result = $pb->scripts->execute('hello.py');
echo $result['output']; // combined stdout/stderr from the script
```

## Managing script permissions

Use `$pb->scriptsPermissions` to control who may execute a script. Superuser auth required for CRUD.

```php
// create or update permissions
$pb->scriptsPermissions->create('hello.py', 'user');

$perm = $pb->scriptsPermissions->get('hello.py');
echo $perm['content']; // "user"

$pb->scriptsPermissions->update('hello.py', content: 'anonymous');
$pb->scriptsPermissions->delete('hello.py'); // back to superuser-only
```

## Running shell commands

Run arbitrary shell commands in the functions directory (`EXECUTE_PATH` or `/pb/functions`). Superuser auth is required.

```php
$result = $pb->scripts->command('cat pyproject.toml');
echo $result['output'];
```

## Deleting scripts

```php
$removed = $pb->scripts->delete('hello.py');
var_export($removed); // true
```

## Notes
- Script CRUD and `scriptsPermissions` require superuser auth; `scripts->execute()` obeys the stored permission level.
- `id` is generated as UUIDv7 on insert and backfilled automatically for older rows.
- Execution uses `EXECUTE_PATH` (default `/pb/functions`) and expects Python available in `.venv`.
- `command` also runs inside `EXECUTE_PATH` and returns combined stdout/stderr.
