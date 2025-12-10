# Plugins Proxy API - PHP SDK

## Overview

`$pb->plugins($method, $path, $options)` forwards requests through `/api/plugins/...` to your configured plugin service (set via `PLUGIN_URL` in docker-compose). All HTTP verbs are supported, plus helpers for SSE and WebSockets. The endpoint is public; the SDK still includes your auth token when present.

**Key points**
- Methods: `GET`, `POST`, `PUT`, `PATCH`, `DELETE`, `HEAD`, `OPTIONS`, `SSE`, `WEBSOCKET` (or `WS`).
- Paths are normalized to `/api/plugins/{path}` (leading slashes trimmed; `/api/plugins/...` accepted as-is).
- Query params, bodies, and headers are passed through unchanged.
- SSE/WebSocket helpers append `?token=...` when you are authenticated (headers are forwarded when supported).

## Quick start

```php
<?php
require __DIR__ . '/../vendor/autoload.php';

use BosBase\BosBase;

$pb = new BosBase('http://127.0.0.1:8080');

// Simple GET to your plugin (e.g., FastAPI /health)
$health = $pb->plugins('GET', '/health');

print_r($health); // ['status' => 'ok']
```

## Bodies, headers, and query params

```php
// POST with body + custom header
$pb->plugins('POST', 'tasks', [
    'body' => ['title' => 'Generate docs', 'priority' => 'high'],
    'headers' => ['X-Plugin-Key' => 'demo-secret'],
]);

// GET with query params
$summary = $pb->plugins('GET', 'reports/summary', [
    'query' => ['since' => '2024-01-01', 'limit' => 50, 'tags' => ['ops', 'ml']],
]);
```

## Other verbs

```php
$pb->plugins('PATCH', 'tasks/42', ['body' => ['status' => 'done']]);
$pb->plugins('DELETE', 'tasks/42');
$pb->plugins('HEAD', 'health');
$pb->plugins('OPTIONS', 'tasks');
```

## Server-Sent Events (SSE)

`SSE` returns a `PluginSSEStream` iterator that yields raw SSE lines. The SDK appends `?token=...` when authenticated because browsers cannot set custom headers for SSE; headers are still forwarded for runtimes that support them.

```php
$stream = $pb->plugins('SSE', 'events/updates', [
    'query' => ['topic' => 'team-alpha'],
    'headers' => ['X-Plugin-Key' => 'secret'],
]);

foreach ($stream as $line) {
    if ($line === '') {
        continue;
    }
    echo "SSE: $line\n";
}

$stream->close(); // stop reading
```

## WebSockets

`WEBSOCKET` (or `WS`) opens a WebSocket using the PHP `textalk/websocket` client bundled with the SDK. Query params are preserved and the token is appended automatically. Custom headers and subprotocols are passed to the client.

```php
use WebSocket\Client as WSClient;

/** @var WSClient $socket */
$socket = $pb->plugins('WEBSOCKET', 'ws/chat', [
    'query' => ['room' => 'general'],
    'headers' => ['X-Plugin-Key' => 'secret'],
    'websocketProtocols' => ['json'],
]);

$socket->send(json_encode(['type' => 'join', 'name' => 'lea']));
echo $socket->receive(); // prints plugin message
$socket->close();
```

## Notes
- Requests use the standard `$pb->send` machinery (including `beforeSend`/`afterSend`) for HTTP verbs.
- SSE/WebSocket helpers bypass `beforeSend`/`afterSend` because they don't rely on the HTTP client.
- Because the endpoint is public, add any plugin-side auth/allowlist you need.
