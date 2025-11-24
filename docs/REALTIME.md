# Realtime API - PHP SDK Documentation

## Overview

The Realtime API enables real-time updates for collection records using **Server-Sent Events (SSE)**. It allows you to subscribe to changes in collections or specific records and receive instant notifications when records are created, updated, or deleted.

**Key Features:**
- Real-time notifications for record changes
- Collection-level and record-level subscriptions
- Automatic connection management and reconnection
- Authorization support
- Subscription options (expand, custom headers, query params)
- Event-driven architecture

**Backend Endpoints:**
- `GET /api/realtime` - Establish SSE connection
- `POST /api/realtime` - Set subscriptions

## How It Works

1. **Connection**: The SDK establishes an SSE connection to `/api/realtime`
2. **Client ID**: Server sends `PB_CONNECT` event with a unique `clientId`
3. **Subscriptions**: Client submits subscription topics via POST request
4. **Events**: Server sends events when matching records change
5. **Reconnection**: SDK automatically reconnects on connection loss

## Basic Usage

### Subscribe to Collection Changes

Subscribe to all changes in a collection:

```php
<?php
require_once 'vendor/autoload.php';

use BosBase\BosBase;

$pb = new BosBase('http://127.0.0.1:8090');

// Subscribe to all changes in the 'posts' collection
$unsubscribe = $pb->collection('posts')->subscribe('*', function ($e) {
    echo 'Action: ' . $e['action'] . "\n";  // 'create', 'update', or 'delete'
    echo 'Record: ' . json_encode($e['record']) . "\n";  // The record data
});

// Later, unsubscribe
$unsubscribe();
```

### Subscribe to Specific Record

Subscribe to changes for a single record:

```php
// Subscribe to changes for a specific post
$pb->collection('posts')->subscribe('RECORD_ID', function ($e) {
    echo 'Record changed: ' . json_encode($e['record']) . "\n";
    echo 'Action: ' . $e['action'] . "\n";
});
```

### Multiple Subscriptions

You can subscribe multiple times to the same or different topics:

```php
// Subscribe to multiple records
$unsubscribe1 = $pb->collection('posts')->subscribe('RECORD_ID_1', 'handleChange');
$unsubscribe2 = $pb->collection('posts')->subscribe('RECORD_ID_2', 'handleChange');
$unsubscribe3 = $pb->collection('posts')->subscribe('*', 'handleAllChanges');

function handleChange($e) {
    echo 'Change event: ' . json_encode($e) . "\n";
}

function handleAllChanges($e) {
    echo 'Collection-wide change: ' . json_encode($e) . "\n";
}

// Unsubscribe individually
$unsubscribe1();
$unsubscribe2();
$unsubscribe3();
```

## Event Structure

Each event received contains:

```php
[
    'action' => 'create' | 'update' | 'delete',  // Action type
    'record' => [                                 // Record data
        'id' => 'RECORD_ID',
        'collectionId' => 'COLLECTION_ID',
        'collectionName' => 'collection_name',
        'created' => '2023-01-01 00:00:00.000Z',
        'updated' => '2023-01-01 00:00:00.000Z',
        // ... other fields
    ]
]
```

### PB_CONNECT Event

When the connection is established, you receive a `PB_CONNECT` event:

```php
$pb->realtime->subscribe('PB_CONNECT', function ($e) {
    echo 'Connected! Client ID: ' . $e['clientId'] . "\n";
    // $e['clientId'] - unique client identifier
});
```

## Subscription Topics

### Collection-Level Subscription

Subscribe to all changes in a collection:

```php
// Wildcard subscription - all records in collection
$pb->collection('posts')->subscribe('*', $handler);
```

**Access Control**: Uses the collection's `ListRule` to determine if the subscriber has access to receive events.

### Record-Level Subscription

Subscribe to changes for a specific record:

```php
// Specific record subscription
$pb->collection('posts')->subscribe('RECORD_ID', $handler);
```

**Access Control**: Uses the collection's `ViewRule` to determine if the subscriber has access to receive events.

## Subscription Options

You can pass additional options when subscribing:

```php
$pb->collection('posts')->subscribe('*', $handler, [
    // Query parameters (for API rule filtering)
    'query' => [
        'filter' => 'status = "published"',
        'expand' => 'author',
    ],
    // Custom headers
    'headers' => [
        'X-Custom-Header' => 'value',
    ],
]);
```

### Expand Relations

Expand relations in the event data:

```php
$pb->collection('posts')->subscribe('RECORD_ID', function ($e) {
    if (isset($e['record']['expand']['author'])) {
        echo 'Author: ' . $e['record']['expand']['author']['name'] . "\n";
    }
}, [
    'query' => [
        'expand' => 'author,categories',
    ],
]);
```

### Filter with Query Parameters

Use query parameters for API rule filtering:

```php
$pb->collection('posts')->subscribe('*', $handler, [
    'query' => [
        'filter' => 'status = "published"',
    ],
]);
```

## Unsubscribing

### Unsubscribe from Specific Topic

```php
// Remove all subscriptions for a specific record
$pb->collection('posts')->unsubscribe('RECORD_ID');

// Remove all wildcard subscriptions for the collection
$pb->collection('posts')->unsubscribe('*');
```

### Unsubscribe from All

```php
// Unsubscribe from all subscriptions in the collection
$pb->collection('posts')->unsubscribe();

// Or unsubscribe from everything
$pb->realtime->unsubscribe();
```

### Unsubscribe Using Returned Function

```php
$unsubscribe = $pb->collection('posts')->subscribe('*', $handler);

// Later...
$unsubscribe();  // Removes this specific subscription
```

## Connection Management

### Connection Status

Check if the realtime connection is established:

```php
if ($pb->realtime->isConnected) {
    echo 'Realtime connected' . "\n";
} else {
    echo 'Realtime disconnected' . "\n";
}
```

### Disconnect Handler

Handle disconnection events:

```php
$pb->realtime->onDisconnect = function ($activeSubscriptions) {
    if (count($activeSubscriptions) > 0) {
        echo 'Connection lost, but subscriptions remain: ' . json_encode($activeSubscriptions) . "\n";
        // Connection will automatically reconnect
    } else {
        echo 'Intentionally disconnected (no active subscriptions)' . "\n";
    }
};
```

### Automatic Reconnection

The SDK automatically:
- Reconnects when the connection is lost
- Resubmits all active subscriptions
- Handles network interruptions gracefully
- Closes connection after 5 minutes of inactivity (server-side timeout)

## Authorization

### Authenticated Subscriptions

Subscriptions respect authentication. If you're authenticated, events are filtered based on your permissions:

```php
// Authenticate first
$pb->collection('users')->authWithPassword('user@example.com', 'password');

// Now subscribe - events will respect your permissions
$pb->collection('posts')->subscribe('*', $handler);
```

### Authorization Rules

- **Collection-level (`*`)**: Uses `ListRule` to determine access
- **Record-level**: Uses `ViewRule` to determine access
- **Superusers**: Can receive all events (if rules allow)
- **Guests**: Only receive events they have permission to see

### Auth State Changes

When authentication state changes, you may need to resubscribe:

```php
// After login/logout, resubscribe to update permissions
$pb->collection('users')->authWithPassword('user@example.com', 'password');

// Re-subscribe to update auth state in realtime connection
$pb->collection('posts')->subscribe('*', $handler);
```

## Advanced Examples

### Example 1: Real-time Chat

```php
// Subscribe to messages in a chat room
function setupChatRoom($pb, $roomId) {
    $unsubscribe = $pb->collection('messages')->subscribe('*', function ($e) use ($roomId) {
        // Filter for this room only
        if ($e['record']['roomId'] === $roomId) {
            if ($e['action'] === 'create') {
                displayMessage($e['record']);
            } else if ($e['action'] === 'delete') {
                removeMessage($e['record']['id']);
            }
        }
    }, [
        'query' => [
            'filter' => "roomId = \"$roomId\"",
        ],
    ]);
    
    return $unsubscribe;
}

// Usage
$unsubscribeChat = setupChatRoom($pb, 'ROOM_ID');

// Cleanup
$unsubscribeChat();
```

### Example 2: Real-time Dashboard

```php
// Subscribe to multiple collections
function setupDashboard($pb) {
    // Posts updates
    $pb->collection('posts')->subscribe('*', function ($e) {
        if ($e['action'] === 'create') {
            addPostToFeed($e['record']);
        } else if ($e['action'] === 'update') {
            updatePostInFeed($e['record']);
        }
    }, [
        'query' => [
            'filter' => 'status = "published"',
            'expand' => 'author',
        ],
    ]);

    // Comments updates
    $pb->collection('comments')->subscribe('*', function ($e) {
        updateCommentsCount($e['record']['postId']);
    }, [
        'query' => [
            'expand' => 'user',
        ],
    ]);
}

setupDashboard($pb);
```

## Error Handling

```php
try {
    $pb->collection('posts')->subscribe('*', $handler);
} catch (\BosBase\Exceptions\ClientResponseError $error) {
    if ($error->getStatus() === 403) {
        echo 'Permission denied' . "\n";
    } else if ($error->getStatus() === 404) {
        echo 'Collection not found' . "\n";
    } else {
        echo 'Subscription error: ' . $error->getMessage() . "\n";
    }
}
```

## Best Practices

1. **Unsubscribe When Done**: Always unsubscribe when components unmount or subscriptions are no longer needed
2. **Handle Disconnections**: Implement `onDisconnect` handler for better UX
3. **Filter Server-Side**: Use query parameters to filter events server-side when possible
4. **Limit Subscriptions**: Don't subscribe to more collections than necessary
5. **Use Record-Level When Possible**: Prefer record-level subscriptions over collection-level when you only need specific records
6. **Monitor Connection**: Track connection state for debugging and user feedback
7. **Handle Errors**: Wrap subscriptions in try-catch blocks
8. **Respect Permissions**: Understand that events respect API rules and permissions

## Limitations

- **Maximum Subscriptions**: Up to 1000 subscriptions per client
- **Topic Length**: Maximum 2500 characters per topic
- **Idle Timeout**: Connection closes after 5 minutes of inactivity
- **Network Dependency**: Requires stable network connection
- **SSE Support**: SSE requires modern PHP with stream support

## Troubleshooting

### Connection Not Establishing

```php
// Check connection status
echo 'Connected: ' . ($pb->realtime->isConnected ? 'true' : 'false') . "\n";

// Manually trigger connection
$pb->collection('posts')->subscribe('*', $handler);
```

### Events Not Received

1. Check API rules - you may not have permission
2. Verify subscription is active
3. Check network connectivity
4. Review server logs for errors

### Memory Leaks

Always unsubscribe:

```php
// Good
$unsubscribe = $pb->collection('posts')->subscribe('*', $handler);
// ... later
$unsubscribe();

// Bad - no cleanup
$pb->collection('posts')->subscribe('*', $handler);
// Never unsubscribed - memory leak!
```

## Related Documentation

- [API Records](./API_RECORDS.md) - CRUD operations
- [Collections](./COLLECTIONS.md) - Collection configuration
- [API Rules and Filters](./API_RULES_AND_FILTERS.md) - Understanding API rules

