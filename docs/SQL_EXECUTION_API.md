# SQL Execution API - PHP SDK

## Overview

The SQL Execution API lets superusers run ad-hoc SQL statements against the BosBase database and retrieve the results. Use it only for controlled maintenance or diagnostics—never expose it to untrusted users.

**Key points**
- Superuser authentication is required for every request.
- Supports both read and write statements.
- Returns column names, rows, and `rowsAffected` for writes.
- Respects the SDK's regular request hooks, headers, and timeout options.

**Endpoint**
- `POST /api/sql/execute`
- Body: `{ "query": "<your SQL statement>" }`

## Authentication

Authenticate as a superuser before calling `pb->sql->execute()`:

```php
<?php

use BosBase\BosBase;

$pb = new BosBase('http://127.0.0.1:8090');
$pb->collection('_superusers')->authWithPassword('admin@example.com', 'password');
```

## Executing a SELECT

```php
$result = $pb->sql->execute('SELECT id, text FROM demo1 ORDER BY id LIMIT 5');

print_r($result['columns']); // ["id", "text"]
print_r($result['rows']);    // [["84nmscqy84lsi1t", "test"], ...]
```

## Executing a Write Statement

```php
$update = $pb->sql->execute(
    "UPDATE demo1 SET text='updated via api' WHERE id='84nmscqy84lsi1t'"
);

echo $update['rowsAffected']; // 1
```

## Response Shape

```jsonc
{
  "columns": ["col1", "col2"], // omitted when empty
  "rows": [["v1", "v2"]],      // omitted when empty
  "rowsAffected": 3            // only present for write operations
}
```

## Error Handling

- The SDK rejects empty queries before sending a request.
- Database or syntax errors are returned as `ClientResponseError` instances.
- You can pass custom query params, headers, or timeout overrides via the optional arguments to `execute()`.

## Safety Tips

- Never pass user-controlled SQL into this API.
- Prefer explicit statements over multi-statement payloads.
- Audit who has superuser credentials and rotate them regularly.
