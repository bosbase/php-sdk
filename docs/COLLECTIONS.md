# Collections - PHP SDK Documentation

## Overview

**Collections** represent your application data. Under the hood they are backed by plain SQLite tables that are generated automatically with the collection **name** and **fields** (columns).

A single entry of a collection is called a **record** (a single row in the SQL table).

## Collection Types

### Base Collection

Default collection type for storing any application data.

```php
<?php
require_once 'vendor/autoload.php';

use BosBase\BosBase;

$pb = new BosBase('http://localhost:8090');
$pb->collection('_superusers')->authWithPassword('admin@example.com', 'password');

$collection = $pb->collections->createBase('articles', [
    'fields' => [
        ['name' => 'title', 'type' => 'text', 'required' => true],
        ['name' => 'description', 'type' => 'text']
    ]
]);
```

### View Collection

Read-only collection populated from a SQL SELECT statement.

```php
$view = $pb->collections->createView('post_stats', 
    "SELECT posts.id, posts.name, count(comments.id) as totalComments 
     FROM posts LEFT JOIN comments on comments.postId = posts.id 
     GROUP BY posts.id"
);
```

### Auth Collection

Base collection with authentication fields (email, password, etc.).

```php
$users = $pb->collections->createAuth('users', [
    'fields' => [['name' => 'name', 'type' => 'text', 'required' => true]]
]);
```

## Collections API

### List Collections

```php
$result = $pb->collections->getList(1, 50);
$all = $pb->collections->getFullList();
```

### Get Collection

```php
$collection = $pb->collections->getOne('articles');
```

### Create Collection

```php
// Using scaffolds
$base = $pb->collections->createBase('articles');
$auth = $pb->collections->createAuth('users');
$view = $pb->collections->createView('stats', 'SELECT * FROM posts');

// Manual
$collection = $pb->collections->create([
    'type' => 'base',
    'name' => 'articles',
    'fields' => [
        ['name' => 'title', 'type' => 'text', 'required' => true],
        // Note: created and updated fields must be explicitly added if you want to use them
        // For autodate fields, onCreate and onUpdate must be direct properties, not nested in options
        [
            'name' => 'created',
            'type' => 'autodate',
            'required' => false,
            'onCreate' => true,
            'onUpdate' => false
        ],
        [
            'name' => 'updated',
            'type' => 'autodate',
            'required' => false,
            'onCreate' => true,
            'onUpdate' => true
        ]
    ]
]);
```

### Update Collection

```php
// Update collection rules
$pb->collections->update('articles', ['listRule' => 'published = true']);

// Update collection name
$pb->collections->update('articles', ['name' => 'posts']);
```

### Add Fields to Collection

To add a new field to an existing collection, fetch the collection, add the field to the fields array, and update:

```php
// Get existing collection
$collection = $pb->collections->getOne('articles');

// Add new field to existing fields
$collection['fields'][] = [
    'name' => 'views',
    'type' => 'number',
    'min' => 0,
    'onlyInt' => true
];

// Update collection with new field
$pb->collections->update('articles', [
    'fields' => $collection['fields']
]);

// Or add multiple fields at once
$collection['fields'][] = [
    'name' => 'excerpt',
    'type' => 'text',
    'max' => 500
];
$collection['fields'][] = [
    'name' => 'cover',
    'type' => 'file',
    'maxSelect' => 1,
    'mimeTypes' => ['image/jpeg', 'image/png']
];

$pb->collections->update('articles', [
    'fields' => $collection['fields']
]);

// Adding created and updated autodate fields to existing collection
// Note: onCreate and onUpdate must be direct properties, not nested in options
$collection['fields'][] = [
    'name' => 'created',
    'type' => 'autodate',
    'required' => false,
    'onCreate' => true,
    'onUpdate' => false
];
$collection['fields'][] = [
    'name' => 'updated',
    'type' => 'autodate',
    'required' => false,
    'onCreate' => true,
    'onUpdate' => true
];

$pb->collections->update('articles', [
    'fields' => $collection['fields']
]);
```

### Delete Fields from Collection

To delete a field, fetch the collection, remove the field from the fields array, and update:

```php
// Get existing collection
$collection = $pb->collections->getOne('articles');

// Remove field by filtering it out
$collection['fields'] = array_filter($collection['fields'], function($field) {
    return $field['name'] !== 'oldFieldName';
});
$collection['fields'] = array_values($collection['fields']); // Re-index array

// Update collection without the deleted field
$pb->collections->update('articles', [
    'fields' => $collection['fields']
]);

// Or remove multiple fields
$fieldsToKeep = ['title', 'content', 'author', 'status'];
$collection['fields'] = array_filter($collection['fields'], function($field) use ($fieldsToKeep) {
    return in_array($field['name'], $fieldsToKeep) || ($field['system'] ?? false);
});
$collection['fields'] = array_values($collection['fields']);

$pb->collections->update('articles', [
    'fields' => $collection['fields']
]);
```

### Modify Fields in Collection

To modify an existing field (e.g., change its type, add options, etc.), fetch the collection, update the field object, and save:

```php
// Get existing collection
$collection = $pb->collections->getOne('articles');

// Find and modify a field
foreach ($collection['fields'] as &$field) {
    if ($field['name'] === 'title') {
        $field['max'] = 200;  // Change max length
        $field['required'] = true;  // Make required
        break;
    }
}

// Update the field type
foreach ($collection['fields'] as &$field) {
    if ($field['name'] === 'status') {
        // Note: Changing field types may require data migration
        $field['type'] = 'select';
        $field['options'] = [
            'values' => ['draft', 'published', 'archived']
        ];
        $field['maxSelect'] = 1;
        break;
    }
}

// Save changes
$pb->collections->update('articles', [
    'fields' => $collection['fields']
]);
```

### Complete Example: Managing Collection Fields

```php
<?php
require_once 'vendor/autoload.php';

use BosBase\BosBase;

$pb = new BosBase('http://localhost:8090');
$pb->collection('_superusers')->authWithPassword('admin@example.com', 'password');

// Get existing collection
$collection = $pb->collections->getOne('articles');

// Add new fields
$collection['fields'][] = [
    'name' => 'tags',
    'type' => 'select',
    'options' => [
        'values' => ['tech', 'design', 'business']
    ],
    'maxSelect' => 5
];
$collection['fields'][] = [
    'name' => 'published_at',
    'type' => 'date'
];

// Remove an old field
$collection['fields'] = array_filter($collection['fields'], function($field) {
    return $field['name'] !== 'oldField';
});
$collection['fields'] = array_values($collection['fields']);

// Modify existing field
foreach ($collection['fields'] as &$field) {
    if ($field['name'] === 'views') {
        $field['max'] = 1000000;  // Increase max value
        break;
    }
}

// Save all changes at once
$pb->collections->update('articles', [
    'fields' => $collection['fields']
]);
```

### Delete Collection

```php
$pb->collections->delete('articles');
```

## Records API

### List Records

```php
$result = $pb->collection('articles')->getList(1, 20, [
    'filter' => 'published = true',
    'sort' => '-created',
    'expand' => 'author'
]);
```

### Get Record

```php
$record = $pb->collection('articles')->getOne('RECORD_ID', [
    'expand' => 'author,category'
]);
```

### Create Record

```php
$record = $pb->collection('articles')->create([
    'title' => 'My Article',
    'views+' => 1  // Field modifier
]);
```

### Update Record

```php
$pb->collection('articles')->update('RECORD_ID', [
    'title' => 'Updated',
    'views+' => 1,
    'tags+' => 'new-tag'
]);
```

### Delete Record

```php
$pb->collection('articles')->delete('RECORD_ID');
```

## Field Types

### BoolField

```php
['name' => 'published', 'type' => 'bool', 'required' => true]
$pb->collection('articles')->create(['published' => true]);
```

### NumberField

```php
['name' => 'views', 'type' => 'number', 'min' => 0]
$pb->collection('articles')->update('ID', ['views+' => 1]);
```

### TextField

```php
['name' => 'title', 'type' => 'text', 'required' => true, 'min' => 6, 'max' => 100]
$pb->collection('articles')->create(['slug:autogenerate' => 'article-']);
```

### EmailField

```php
['name' => 'email', 'type' => 'email', 'required' => true]
```

### URLField

```php
['name' => 'website', 'type' => 'url']
```

### EditorField

```php
['name' => 'content', 'type' => 'editor', 'required' => true]
$pb->collection('articles')->create(['content' => '<p>HTML content</p>']);
```

### DateField

```php
['name' => 'published_at', 'type' => 'date']
$pb->collection('articles')->create([
    'published_at' => '2024-11-10 18:45:27.123Z'
]);
```

### AutodateField

**Important Note:** Bosbase does not initialize `created` and `updated` fields by default. To use these fields, you must explicitly add them when initializing the collection. For autodate fields, `onCreate` and `onUpdate` must be direct properties of the field object, not nested in an `options` object:

```php
// Create field with proper structure
[
    'name' => 'created',
    'type' => 'autodate',
    'required' => false,
    'onCreate' => true,  // Set on record creation (direct property)
    'onUpdate' => false  // Don't update on record update (direct property)
]

// For updated field
[
    'name' => 'updated',
    'type' => 'autodate',
    'required' => false,
    'onCreate' => true,  // Set on record creation (direct property)
    'onUpdate' => true   // Update on record update (direct property)
]

// The value is automatically set by the backend based on onCreate and onUpdate properties
```

### SelectField

```php
// Single select
['name' => 'status', 'type' => 'select', 'options' => ['values' => ['draft', 'published']], 'maxSelect' => 1]
$pb->collection('articles')->create(['status' => 'published']);

// Multiple select
['name' => 'tags', 'type' => 'select', 'options' => ['values' => ['tech', 'design']], 'maxSelect' => 5]
$pb->collection('articles')->update('ID', ['tags+' => 'marketing']);
```

### FileField

```php
// Single file
['name' => 'cover', 'type' => 'file', 'maxSelect' => 1, 'mimeTypes' => ['image/jpeg']]
// Note: File uploads in PHP require using CURLFile or file paths
$pb->collection('articles')->create([
    'title' => 'My Article',
    'cover' => new CURLFile('/path/to/image.jpg', 'image/jpeg', 'image.jpg')
]);
```

### RelationField

```php
['name' => 'author', 'type' => 'relation', 'options' => ['collectionId' => 'users'], 'maxSelect' => 1]
$pb->collection('articles')->create(['author' => 'USER_ID']);
$record = $pb->collection('articles')->getOne('ID', ['expand' => 'author']);
```

### JSONField

```php
['name' => 'metadata', 'type' => 'json']
$pb->collection('articles')->create([
    'metadata' => ['seo' => ['title' => 'SEO Title']]
]);
```

### GeoPointField

```php
['name' => 'location', 'type' => 'geoPoint']
$pb->collection('places')->create([
    'location' => ['lon' => 139.6917, 'lat' => 35.6586]
]);
```

## Complete Example

```php
<?php
require_once 'vendor/autoload.php';

use BosBase\BosBase;

$pb = new BosBase('http://localhost:8090');
$pb->collection('_superusers')->authWithPassword('admin@example.com', 'password');

// Create collections
$users = $pb->collections->createAuth('users');
$articles = $pb->collections->createBase('articles', [
    'fields' => [
        ['name' => 'title', 'type' => 'text', 'required' => true],
        ['name' => 'author', 'type' => 'relation', 'options' => ['collectionId' => $users['id']], 'maxSelect' => 1]
    ]
]);

// Create and authenticate user
$user = $pb->collection('users')->create([
    'email' => 'user@example.com',
    'password' => 'password123',
    'passwordConfirm' => 'password123'
]);
$pb->collection('users')->authWithPassword('user@example.com', 'password123');

// Create article
$article = $pb->collection('articles')->create([
    'title' => 'My Article',
    'author' => $user['id']
]);

// Subscribe to changes
$pb->collection('articles')->subscribe('*', function($e) {
    echo $e['action'] . ': ' . json_encode($e['record']) . "\n";
});
```

