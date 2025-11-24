# Collections Extra - PHP SDK Documentation

This document provides additional information about working with Collections in the BosBase PHP SDK.

## Collection Types

Currently there are 3 collection types: **Base**, **View** and **Auth**.

### Base Collection

**Base collection** is the default collection type and it could be used to store any application data (articles, products, posts, etc.).

```php
<?php
require_once 'vendor/autoload.php';

use BosBase\BosBase;

$pb = new BosBase('http://localhost:8090');
$pb->collection('_superusers')->authWithPassword('admin@example.com', 'password');

// Create a base collection
$collection = $pb->collections->createBase('articles', [
    'fields' => [
        [
            'name' => 'title',
            'type' => 'text',
            'required' => true,
            'min' => 6,
            'max' => 100,
        ],
        [
            'name' => 'description',
            'type' => 'text',
        ],
    ],
    'listRule' => '@request.auth.id != "" || status = "public"',
    'viewRule' => '@request.auth.id != "" || status = "public"',
]);
```

### View Collection

**View collection** is a read-only collection type where the data is populated from a plain SQL `SELECT` statement.

```php
// Create a view collection
$viewCollection = $pb->collections->createView('post_stats', 
    'SELECT posts.id, posts.name, count(comments.id) as totalComments 
     FROM posts 
     LEFT JOIN comments on comments.postId = posts.id 
     GROUP BY posts.id'
);
```

**Note**: View collections don't receive realtime events because they don't have create/update/delete operations.

### Auth Collection

**Auth collection** has everything from the **Base collection** but with some additional special fields to help you manage your app users and also provide various authentication options.

```php
// Create an auth collection
$usersCollection = $pb->collections->createAuth('users', [
    'fields' => [
        [
            'name' => 'name',
            'type' => 'text',
            'required' => true,
        ],
        [
            'name' => 'role',
            'type' => 'select',
            'options' => [
                'values' => ['employee', 'staff', 'admin'],
            ],
        ],
    ],
]);
```

## Related Documentation

- [Collections](./COLLECTIONS.md) - Main collections documentation
- [Collection API](./COLLECTION_API.md) - Collection management API
- [Authentication](./AUTHENTICATION.md) - User authentication

