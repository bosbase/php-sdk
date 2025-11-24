# Cache API - PHP SDK Documentation

BosBase caches combine in-memory [FreeCache](https://github.com/coocood/freecache) storage with persistent database copies. Each cache instance is safe to use in single-node or multi-node (cluster) mode: nodes read from FreeCache first, fall back to the database if an item is missing or expired, and then reload FreeCache automatically.

The PHP SDK exposes the cache endpoints through `$pb->caches`. Typical use cases include:

- Caching AI prompts/responses that must survive restarts.
- Quickly sharing feature flags and configuration between workers.
- Preloading expensive vector search results for short periods.

> **Timeouts & TTLs:** Each cache defines a default TTL (in seconds). Individual entries may provide their own `ttlSeconds`. A value of `0` keeps the entry until it is manually deleted.

## List available caches

The `list()` function allows you to query and retrieve all currently available caches, including their names and capacities. This is particularly useful for AI systems to discover existing caches before creating new ones, avoiding duplicate cache creation.

```php
<?php
require_once 'vendor/autoload.php';

use BosBase\BosBase;

$pb = new BosBase('http://127.0.0.1:8090');
$pb->collection('_superusers')->authWithPassword('root@example.com', 'hunter2');

// Query all available caches
$caches = $pb->caches->list();

// Each cache object contains:
// - name: string - The cache identifier
// - sizeBytes: number - The cache capacity in bytes
// - defaultTTLSeconds: number - Default expiration time
// - readTimeoutMs: number - Read timeout in milliseconds
// - created: string - Creation timestamp (RFC3339)
// - updated: string - Last update timestamp (RFC3339)

// Example: Find a cache by name and check its capacity
$targetCache = null;
foreach ($caches as $cache) {
    if ($cache['name'] === 'ai-session') {
        $targetCache = $cache;
        break;
    }
}

if ($targetCache) {
    echo "Cache \"{$targetCache['name']}\" has capacity of {$targetCache['sizeBytes']} bytes\n";
    // Use the existing cache directly
} else {
    echo "Cache not found, create a new one if needed\n";
}
```

## Manage cache configurations

```php
<?php
require_once 'vendor/autoload.php';

use BosBase\BosBase;

$pb = new BosBase('http://127.0.0.1:8090');
$pb->collection('_superusers')->authWithPassword('root@example.com', 'hunter2');

// List all available caches (including name and capacity).
// This is useful for AI to discover existing caches before creating new ones.
$caches = $pb->caches->list();
echo "Available caches: " . json_encode($caches, JSON_PRETTY_PRINT) . "\n";
// Output example:
// [
//   {
//     "name": "ai-session",
//     "sizeBytes": 67108864,
//     "defaultTTLSeconds": 300,
//     "readTimeoutMs": 25,
//     "created": "2024-01-15T10:30:00Z",
//     "updated": "2024-01-15T10:30:00Z"
//   },
//   {
//     "name": "query-cache",
//     "sizeBytes": 33554432,
//     "defaultTTLSeconds": 600,
//     "readTimeoutMs": 50,
//     "created": "2024-01-14T08:00:00Z",
//     "updated": "2024-01-14T08:00:00Z"
//   }
// ]

// Find an existing cache by name
$existingCache = null;
foreach ($caches as $cache) {
    if ($cache['name'] === 'ai-session') {
        $existingCache = $cache;
        break;
    }
}

if ($existingCache) {
    echo "Found cache \"{$existingCache['name']}\" with capacity {$existingCache['sizeBytes']} bytes\n";
    // Use the existing cache directly without creating a new one
} else {
    // Create a new cache only if it doesn't exist
    $pb->caches->create([
        'name' => 'ai-session',
        'sizeBytes' => 64 * 1024 * 1024,
        'defaultTTLSeconds' => 300,
        'readTimeoutMs' => 25, // optional concurrency guard
    ]);
}

// Update limits later (eg. shrink TTL to 2 minutes).
$pb->caches->update('ai-session', [
    'defaultTTLSeconds' => 120,
]);

// Delete the cache (DB rows + FreeCache).
$pb->caches->delete('ai-session');
```

Field reference:

| Field | Description |
|-------|-------------|
| `sizeBytes` | Approximate FreeCache size. Values too small (<512KB) or too large (>512MB) are clamped. |
| `defaultTTLSeconds` | Default expiration for entries. `0` means no expiration. |
| `readTimeoutMs` | Optional lock timeout while reading FreeCache. When exceeded, the value is fetched from the database instead. |

## Work with cache entries

```php
// Store an object in cache. The same payload is serialized into the DB.
$pb->caches->setEntry('ai-session', 'dialog:42', [
    'prompt' => 'describe Saturn',
    'embedding' => [/* vector */],
], 90); // per-entry TTL in seconds

// Read from cache. `source` indicates where the hit came from.
$entry = $pb->caches->getEntry('ai-session', 'dialog:42');

echo $entry['source'];   // "cache" or "database"
echo $entry['expiresAt']; // RFC3339 timestamp or null

// Renew an entry's TTL without changing its value.
// This extends the expiration time by the specified TTL (or uses the cache's default TTL if omitted).
$renewed = $pb->caches->renewEntry('ai-session', 'dialog:42', 120); // extend by 120 seconds
echo $renewed['expiresAt']; // new expiration time

// Delete an entry.
$pb->caches->deleteEntry('ai-session', 'dialog:42');
```

### Cluster-aware behaviour

1. **Write-through persistence** – every `setEntry` writes to FreeCache and the `_cache_entries` table so other nodes (or a restarted node) can immediately reload values.
2. **Read path** – FreeCache is consulted first. If a lock cannot be acquired within `readTimeoutMs` or if the entry is missing/expired, BosBase queries the database copy and repopulates FreeCache in the background.
3. **Automatic cleanup** – expired entries are ignored and removed from the database when fetched, preventing stale data across nodes.

Use caches whenever you need fast, transient data that must still be recoverable or shareable across BosBase nodes.

## Complete Examples

### Example 1: AI Session Cache

```php
<?php
require_once 'vendor/autoload.php';

use BosBase\BosBase;

$pb = new BosBase('http://127.0.0.1:8090');
$pb->collection('_superusers')->authWithPassword('admin@example.com', 'password');

// Check if cache exists, create if not
$caches = $pb->caches->list();
$cacheExists = false;
foreach ($caches as $cache) {
    if ($cache['name'] === 'ai-session') {
        $cacheExists = true;
        break;
    }
}

if (!$cacheExists) {
    $pb->caches->create([
        'name' => 'ai-session',
        'sizeBytes' => 64 * 1024 * 1024, // 64MB
        'defaultTTLSeconds' => 300, // 5 minutes
    ]);
}

// Store AI conversation
$pb->caches->setEntry('ai-session', 'user:123:conversation', [
    'messages' => [
        ['role' => 'user', 'content' => 'Hello'],
        ['role' => 'assistant', 'content' => 'Hi there!'],
    ],
    'context' => ['topic' => 'general'],
], 600); // 10 minutes TTL

// Retrieve conversation
$conversation = $pb->caches->getEntry('ai-session', 'user:123:conversation');
if ($conversation) {
    print_r($conversation['value']);
    echo "Source: {$conversation['source']}\n";
}
```

### Example 2: Feature Flags Cache

```php
// Store feature flags
$pb->caches->setEntry('config', 'feature-flags', [
    'newDashboard' => true,
    'betaFeatures' => false,
    'maintenanceMode' => false,
], 0); // No expiration

// Read feature flags
$flags = $pb->caches->getEntry('config', 'feature-flags');
if ($flags && isset($flags['value']['newDashboard']) && $flags['value']['newDashboard']) {
    echo 'New dashboard is enabled' . "\n";
}
```

## Related Documentation

- [Collections](./COLLECTIONS.md) - Collection management
- [Health API](./HEALTH_API.md) - Server health checks

