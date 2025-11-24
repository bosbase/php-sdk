BosBase PHP SDK
================

This directory contains a PHP client that mirrors the JavaScript SDK surface so PHP services can talk to the BosBase Go backend.

## Installation

```bash
composer require bosbase/php-sdk
```

## Quick start

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use BosBase\BosBase;

$pb = new BosBase('http://127.0.0.1:8090');

// Authenticate against an auth collection
$auth = $pb->collection('users')->authWithPassword('test@example.com', '123456');

// CRUD
$posts = $pb->collection('posts')->getList(page: 1, perPage: 10);
$created = $pb->collection('posts')->create(['title' => 'Hello']);

// Files
$url = $pb->files->getUrl($created, $created['cover']);

// Realtime (runs a blocking event loop)
$unsubscribe = $pb->collection('posts')->subscribe('*', function ($event) {
    echo "Realtime event: {$event['action']}\n";
});
$pb->realtime->run(); // call poll() instead to integrate with your own loop
```

## Services

- `collection(<name>)` – CRUD helpers, auth flows, OTP/OAuth2, impersonation.
- `collections` – Manage collections, scaffolds, indexes, schema helpers.
- `files` – File URLs and download tokens.
- `logs`, `crons`, `backups`, `vectors`, `llmDocuments`, `langchaingo`, `caches`, `graphql`.
- `realtime` – Server-sent events subscription helper.
- `pubsub` – WebSocket publish/subscribe.
- `createBatch()` – Transactional multi-collection writes.

### Hooks

`beforeSend($url, $options)` lets you tweak outbound requests. Return `['url' => ..., 'options' => [...]]` to override.  
`afterSend($responseMeta, $data, $options)` can adjust responses before they are returned.

### Realtime and PubSub

Both realtime (SSE) and pub/sub (WebSockets) expose `run()` methods that block and dispatch events. Call `poll()` (realtime) or integrate `run()` in a worker process to keep subscriptions active.

## Notes

- PHP 8.1+ is required.
- WebSocket support relies on `textalk/websocket`; install via Composer.
- The SDK mirrors the JS API shape; if you see differences, let us know so we can align naming/behavior.
