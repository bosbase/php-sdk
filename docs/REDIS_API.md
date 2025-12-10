# Redis API (PHP SDK)

Redis support is powered by [rueidis](https://github.com/redis/rueidis) and is **disabled unless `REDIS_URL` is set** on the server (optionally `REDIS_PASSWORD`). The routes are superuser-only; regular users or misconfigured nodes won't expose them.

Steps:
- Set `REDIS_URL` (e.g., `redis://redis:6379` or `rediss://cache:6379`); optionally set `REDIS_PASSWORD`.
- Authenticate as a superuser before calling Redis endpoints.
- When `ttlSeconds` is omitted during updates, the existing TTL is preserved. Use `ttlSeconds = 0` to remove a TTL or a positive value to set one.

## Discover keys

```php
<?php
require __DIR__ . '/../vendor/autoload.php';

use BosBase\BosBase;

$pb = new BosBase('http://127.0.0.1:8090');
$pb->collection('_superusers')->authWithPassword('root@example.com', 'hunter2');

// Scan keys with optional cursor, pattern, and count hint
$page = $pb->redis->listKeys(pattern: 'session:*', count: 100);

echo $page['cursor'];      // pass back into listKeys to continue scanning
print_r($page['items']);   // [['key' => 'session:123'], ...]
```

## Create, read, update, delete keys

```php
// Create if it does NOT already exist (409 on conflict)
$pb->redis->createKey('session:123', [
    'prompt' => 'hello',
    'tokens' => 42,
], ttlSeconds: 3600);

// Read value + current TTL (ttlSeconds is null/absent when persistent)
$entry = $pb->redis->getKey('session:123');
print_r($entry['value']);
echo $entry['ttlSeconds'] ?? 'no-ttl';

// Update existing key (preserves TTL when ttlSeconds is omitted)
$pb->redis->updateKey('session:123', [
    'prompt' => 'updated',
    'tokens' => 99,
], ttlSeconds: 0); // set to 0 to remove TTL, or a positive int to set one

// Delete key
$pb->redis->deleteKey('session:123');
```

Responses:
- `listKeys` returns `['cursor' => string, 'items' => [['key' => string], ...]]`.
- `createKey`, `getKey`, and `updateKey` return `['key' => ..., 'value' => ..., 'ttlSeconds' => ?int]`.
