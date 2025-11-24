# Pub/Sub API - PHP SDK Documentation

BosBase exposes a lightweight WebSocket-based publish/subscribe channel so SDK users can push and receive custom messages. The Go backend uses the `ws` transport and persists each published payload in the `_pubsub_messages` table so every node in a cluster can replay and fan-out messages to its local subscribers.

- Endpoint: `/api/pubsub` (WebSocket)
- Auth: the SDK automatically forwards `authStore.token` as a `token` query parameter; cookie-based auth also works. Anonymous clients may subscribe, but publishing requires an authenticated token.
- Reliability: automatic reconnect with topic re-subscription; messages are stored in the database and broadcasted to all connected nodes.

## Quick Start

```php
<?php
require_once 'vendor/autoload.php';

use BosBase\BosBase;

$pb = new BosBase('http://127.0.0.1:8090');

// Subscribe to a topic
$unsubscribe = $pb->pubsub->subscribe('chat/general', function($msg) {
    echo "message: {$msg['topic']}\n";
    print_r($msg['data']);
});

// Publish to a topic (resolves when the server stores and accepts it)
$ack = $pb->pubsub->publish('chat/general', ['text' => 'Hello team!']);
echo "published at: {$ack['created']}\n";

// Later, stop listening
$unsubscribe();
```

## API Surface

- `$pb->pubsub->publish($topic, $data)` → `['id' => ..., 'topic' => ..., 'created' => ...]`
- `$pb->pubsub->subscribe($topic, $handler)` → `callable` (unsubscribe function)
- `$pb->pubsub->unsubscribe($topic?)` → `void` (omit `topic` to drop all topics)
- `$pb->pubsub->disconnect()` to explicitly close the socket and clear pending requests.
- `$pb->pubsub->isConnected()` exposes the current WebSocket state.

## Examples

### Basic Pub/Sub

```php
// Subscribe to multiple topics
$unsub1 = $pb->pubsub->subscribe('notifications', function($msg) {
    echo "Notification: {$msg['data']['message']}\n";
});

$unsub2 = $pb->pubsub->subscribe('updates', function($msg) {
    echo "Update: {$msg['data']['type']}\n";
});

// Publish messages
$pb->pubsub->publish('notifications', ['message' => 'New user registered']);
$pb->pubsub->publish('updates', ['type' => 'system', 'status' => 'online']);

// Unsubscribe
$unsub1();
$unsub2();
```

### Unsubscribe All

```php
// Unsubscribe from all topics
$pb->pubsub->unsubscribe();
```

### Check Connection Status

```php
if ($pb->pubsub->isConnected()) {
    echo "WebSocket is connected\n";
} else {
    echo "WebSocket is not connected\n";
}
```

### Disconnect

```php
// Explicitly disconnect
$pb->pubsub->disconnect();
```

## Notes for Clusters

- Messages are written to `_pubsub_messages` with a timestamp; every running node polls the table and pushes new rows to its connected WebSocket clients.
- Old pub/sub rows are cleaned up automatically after a day to keep the table small.
- If a node restarts, it resumes from the latest message and replays new rows as they are inserted, so connected clients on other nodes stay in sync.

## Error Handling

```php
try {
    $ack = $pb->pubsub->publish('chat/general', ['text' => 'Hello']);
    echo "Published: {$ack['id']}\n";
} catch (\Exception $error) {
    echo "Failed to publish: " . $error->getMessage() . "\n";
}

try {
    $unsubscribe = $pb->pubsub->subscribe('chat/general', function($msg) {
        // Handle message
    });
} catch (\Exception $error) {
    echo "Failed to subscribe: " . $error->getMessage() . "\n";
}
```

## Related Documentation

- [Realtime](./REALTIME.md) - Server-Sent Events for record changes
- [Authentication](./AUTHENTICATION.md) - User authentication

