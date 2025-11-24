# Collection API - PHP SDK Documentation

## Overview

The Collection API provides endpoints for managing collections (Base, Auth, and View types). All operations require superuser authentication and allow you to create, read, update, and delete collections along with their schemas and configurations.

**Key Features:**
- List and search collections
- View collection details
- Create collections (base, auth, view)
- Update collection schemas and rules
- Delete collections
- Truncate collections (delete all records)
- Import collections in bulk
- Get collection scaffolds (templates)

**Backend Endpoints:**
- `GET /api/collections` - List collections
- `GET /api/collections/{collection}` - View collection
- `POST /api/collections` - Create collection
- `PATCH /api/collections/{collection}` - Update collection
- `DELETE /api/collections/{collection}` - Delete collection
- `DELETE /api/collections/{collection}/truncate` - Truncate collection
- `PUT /api/collections/import` - Import collections
- `GET /api/collections/meta/scaffolds` - Get scaffolds

**Note**: All Collection API operations require superuser authentication.

## Authentication

All Collection API operations require superuser authentication:

```php
<?php
require_once 'vendor/autoload.php';

use BosBase\BosBase;

$pb = new BosBase('http://127.0.0.1:8090');

// Authenticate as superuser
$pb->collection('_superusers')->authWithPassword('admin@example.com', 'password');
```

## List Collections

Returns a paginated list of collections with support for filtering and sorting.

```php
// Basic list
$result = $pb->collections->getList(1, 30);

echo $result['page'];        // 1
echo $result['perPage'];     // 30
echo $result['totalItems'];  // Total collections count
print_r($result['items']);   // Array of collections
```

### Advanced Filtering and Sorting

```php
// Filter by type
$authCollections = $pb->collections->getList(1, 100, [
    'filter' => 'type = "auth"',
]);

// Filter by name pattern
$matchingCollections = $pb->collections->getList(1, 100, [
    'filter' => 'name ~ "user"',
]);

// Sort by creation date
$sortedCollections = $pb->collections->getList(1, 100, [
    'sort' => '-created',
]);

// Complex filter
$filtered = $pb->collections->getList(1, 100, [
    'filter' => 'type = "base" && system = false && created >= "2023-01-01"',
    'sort' => 'name',
]);
```

### Get Full List

```php
// Get all collections at once
$allCollections = $pb->collections->getFullList([
    'sort' => 'name',
    'filter' => 'system = false',
]);
```

### Get First Matching Collection

```php
// Get first auth collection
$authCollection = $pb->collections->getFirstListItem('type = "auth"');
```

## View Collection

Retrieve a single collection by ID or name:

```php
// By name
$collection = $pb->collections->getOne('posts');

// By ID
$collection = $pb->collections->getOne('_pbc_2287844090');

// With field selection
$collection = $pb->collections->getOne('posts', [
    'fields' => 'id,name,type,fields.name,fields.type',
]);
```

## Create Collection

Create a new collection with schema fields and configuration.

**Note**: If the `created` and `updated` fields are not specified during collection initialization, BosBase will automatically create them. These system fields are added to all collections by default and track when records are created and last modified. You don't need to include them in your field definitions.

### Create Base Collection

```php
$baseCollection = $pb->collections->create([
    'name' => 'posts',
    'type' => 'base',
    'fields' => [
        [
            'name' => 'title',
            'type' => 'text',
            'required' => true,
            'min' => 10,
            'max' => 255,
        ],
        [
            'name' => 'content',
            'type' => 'editor',
            'required' => false,
        ],
        [
            'name' => 'published',
            'type' => 'bool',
            'required' => false,
        ],
        [
            'name' => 'author',
            'type' => 'relation',
            'required' => true,
            'collectionId' => '_pbc_users_auth_',
            'maxSelect' => 1,
        ],
    ],
    'listRule' => '@request.auth.id != ""',
    'viewRule' => '@request.auth.id != "" || published = true',
    'createRule' => '@request.auth.id != ""',
    'updateRule' => 'author = @request.auth.id',
    'deleteRule' => 'author = @request.auth.id',
]);
```

### Create Auth Collection

```php
$authCollection = $pb->collections->create([
    'name' => 'users',
    'type' => 'auth',
    'fields' => [
        [
            'name' => 'name',
            'type' => 'text',
            'required' => false,
        ],
        [
            'name' => 'avatar',
            'type' => 'file',
            'required' => false,
            'maxSelect' => 1,
            'maxSize' => 2097152, // 2MB
            'mimeTypes' => ['image/jpeg', 'image/png'],
        ],
    ],
    'listRule' => null,
    'viewRule' => '@request.auth.id = id',
    'createRule' => null,
    'updateRule' => '@request.auth.id = id',
    'deleteRule' => '@request.auth.id = id',
    'manageRule' => null,
    'authRule' => 'verified = true', // Only verified users can authenticate
    'passwordAuth' => [
        'enabled' => true,
        'identityFields' => ['email', 'username'],
    ],
    'authToken' => [
        'duration' => 604800, // 7 days
    ],
]);
```

### Create View Collection

```php
$viewCollection = $pb->collections->create([
    'name' => 'published_posts',
    'type' => 'view',
    'listRule' => '@request.auth.id != ""',
    'viewRule' => '@request.auth.id != ""',
    'viewQuery' => "
        SELECT 
            p.id,
            p.title,
            p.content,
            p.created,
            u.name as author_name,
            u.email as author_email
        FROM posts p
        LEFT JOIN users u ON p.author = u.id
        WHERE p.published = true
    ",
]);
```

### Create from Scaffold

Use predefined scaffolds as a starting point:

```php
// Get available scaffolds
$scaffolds = $pb->collections->getScaffolds();

// Create base collection from scaffold
$baseCollection = $pb->collections->createBase('my_posts', [
    'fields' => [
        [
            'name' => 'title',
            'type' => 'text',
            'required' => true,
        ],
    ],
]);

// Create auth collection from scaffold
$authCollection = $pb->collections->createAuth('my_users', [
    'passwordAuth' => [
        'enabled' => true,
        'identityFields' => ['email'],
    ],
]);

// Create view collection from scaffold
$viewCollection = $pb->collections->createView('my_view', 'SELECT id, title FROM posts', [
    'listRule' => '@request.auth.id != ""',
]);
```

### Accessing Collection ID After Creation

When a collection is successfully created, the returned array includes the `id` property, which contains the unique identifier assigned by the backend. You can access it immediately after creation:

```php
// Create a collection and access its ID
$collection = $pb->collections->create([
    'name' => 'posts',
    'type' => 'base',
    'fields' => [
        [
            'name' => 'title',
            'type' => 'text',
            'required' => true,
        ],
    ],
]);

// Access the collection ID
echo $collection['id']; // e.g., "_pbc_2287844090"

// Use the ID for subsequent operations
$pb->collections->update($collection['id'], [
    'listRule' => '@request.auth.id != ""',
]);

// Store the ID for later use
$collectionId = $collection['id'];
```

## Update Collection

Update an existing collection's schema, fields, or rules:

```php
// Update collection name and rules
$updated = $pb->collections->update('posts', [
    'name' => 'articles',
    'listRule' => '@request.auth.id != "" || status = "public"',
    'viewRule' => '@request.auth.id != "" || status = "public"',
]);

// Add new field
$collection = $pb->collections->getOne('posts');
$collection['fields'][] = [
    'name' => 'tags',
    'type' => 'select',
    'options' => [
        'values' => ['tech', 'science', 'art'],
    ],
];
$pb->collections->update('posts', $collection);

// Update field configuration
$collection = $pb->collections->getOne('posts');
foreach ($collection['fields'] as &$field) {
    if ($field['name'] === 'title') {
        $field['max'] = 200;
        break;
    }
}
$pb->collections->update('posts', $collection);
```

## Delete Collection

Delete a collection (including all records and files):

```php
// Delete by name
$pb->collections->delete('old_collection');

// Delete by ID
$pb->collections->delete('_pbc_2287844090');

// Using deleteCollection method (alias)
$pb->collections->deleteCollection('old_collection');
```

**Warning**: This operation is destructive and will:
- Delete the collection schema
- Delete all records in the collection
- Delete all associated files
- Remove all indexes

**Note**: Collections referenced by other collections cannot be deleted.

## Truncate Collection

Delete all records in a collection while keeping the collection schema:

```php
// Truncate collection (delete all records)
$pb->collections->truncate('posts');

// This will:
// - Delete all records in the collection
// - Delete all associated files
// - Delete cascade-enabled relations
// - Keep the collection schema intact
```

**Warning**: This operation is destructive and cannot be undone. It's useful for:
- Clearing test data
- Resetting collections
- Bulk data removal

**Note**: View collections cannot be truncated.

## Import Collections

Bulk import multiple collections at once:

```php
$collectionsToImport = [
    [
        'name' => 'posts',
        'type' => 'base',
        'fields' => [
            [
                'name' => 'title',
                'type' => 'text',
                'required' => true,
            ],
            [
                'name' => 'content',
                'type' => 'editor',
            ],
        ],
        'listRule' => '@request.auth.id != ""',
    ],
    [
        'name' => 'categories',
        'type' => 'base',
        'fields' => [
            [
                'name' => 'name',
                'type' => 'text',
                'required' => true,
            ],
        ],
    ],
];

// Import without deleting existing collections
$pb->collections->importCollections($collectionsToImport, false);

// Import and delete collections not in the import list
$pb->collections->importCollections($collectionsToImport, true);
```

### Import Options

- **`deleteMissing: false`** (default): Only create/update collections in the import list
- **`deleteMissing: true`**: Delete all collections not present in the import list

**Warning**: Using `deleteMissing: true` will permanently delete collections and all their data.

## Get Scaffolds

Get collection templates for creating new collections:

```php
$scaffolds = $pb->collections->getScaffolds();

// Available scaffold types
print_r($scaffolds['base']);   // Base collection template
print_r($scaffolds['auth']);   // Auth collection template
print_r($scaffolds['view']);   // View collection template

// Use scaffold as starting point
$baseTemplate = $scaffolds['base'];
$newCollection = array_merge($baseTemplate, [
    'name' => 'my_collection',
    'fields' => array_merge($baseTemplate['fields'], [
        [
            'name' => 'custom_field',
            'type' => 'text',
        ],
    ]),
]);

$pb->collections->create($newCollection);
```

## Complete Examples

### Example 1: Setup Blog Collections

```php
<?php
require_once 'vendor/autoload.php';

use BosBase\BosBase;

$pb = new BosBase('http://localhost:8090');
$pb->collection('_superusers')->authWithPassword('admin@example.com', 'password');

// Create posts collection
$posts = $pb->collections->create([
    'name' => 'posts',
    'type' => 'base',
    'fields' => [
        [
            'name' => 'title',
            'type' => 'text',
            'required' => true,
            'min' => 10,
            'max' => 255,
        ],
        [
            'name' => 'slug',
            'type' => 'text',
            'required' => true,
            'options' => [
                'pattern' => '^[a-z0-9-]+$',
            ],
        ],
        [
            'name' => 'content',
            'type' => 'editor',
            'required' => true,
        ],
        [
            'name' => 'featured_image',
            'type' => 'file',
            'maxSelect' => 1,
            'maxSize' => 5242880, // 5MB
            'mimeTypes' => ['image/jpeg', 'image/png'],
        ],
        [
            'name' => 'published',
            'type' => 'bool',
            'required' => false,
        ],
        [
            'name' => 'author',
            'type' => 'relation',
            'collectionId' => '_pbc_users_auth_',
            'maxSelect' => 1,
        ],
        [
            'name' => 'categories',
            'type' => 'relation',
            'collectionId' => 'categories',
            'maxSelect' => 5,
        ],
    ],
    'listRule' => '@request.auth.id != "" || published = true',
    'viewRule' => '@request.auth.id != "" || published = true',
    'createRule' => '@request.auth.id != ""',
    'updateRule' => 'author = @request.auth.id',
    'deleteRule' => 'author = @request.auth.id',
]);

// Create categories collection
$categories = $pb->collections->create([
    'name' => 'categories',
    'type' => 'base',
    'fields' => [
        [
            'name' => 'name',
            'type' => 'text',
            'required' => true,
            'unique' => true,
        ],
        [
            'name' => 'slug',
            'type' => 'text',
            'required' => true,
        ],
        [
            'name' => 'description',
            'type' => 'text',
            'required' => false,
        ],
    ],
    'listRule' => '@request.auth.id != ""',
    'viewRule' => '@request.auth.id != ""',
]);

// Access collection IDs immediately after creation
echo 'Posts collection ID: ' . $posts['id'] . "\n";
echo 'Categories collection ID: ' . $categories['id'] . "\n";

echo 'Blog setup complete!' . "\n";
```

## Error Handling

```php
try {
    $pb->collections->create([
        'name' => 'test',
        'type' => 'base',
        'fields' => [],
    ]);
} catch (\BosBase\Exceptions\ClientResponseError $error) {
    if ($error->getStatus() === 401) {
        echo 'Not authenticated' . "\n";
    } else if ($error->getStatus() === 403) {
        echo 'Not a superuser' . "\n";
    } else if ($error->getStatus() === 400) {
        echo 'Validation error: ' . json_encode($error->getData()) . "\n";
    } else {
        echo 'Unexpected error: ' . $error->getMessage() . "\n";
    }
}
```

## Best Practices

1. **Always Authenticate**: Ensure you're authenticated as a superuser before making requests
2. **Backup Before Import**: Always backup existing collections before using `import` with `deleteMissing: true`
3. **Validate Schema**: Validate collection schemas before creating/updating
4. **Use Scaffolds**: Use scaffolds as starting points for consistency
5. **Test Rules**: Test API rules thoroughly before deploying to production
6. **Document Schemas**: Keep documentation of your collection schemas
7. **Version Control**: Store collection schemas in version control for migration tracking

## Limitations

- **Superuser Only**: All operations require superuser authentication
- **System Collections**: System collections cannot be deleted or renamed
- **View Collections**: Cannot be truncated (they don't store records)
- **Relations**: Collections referenced by others cannot be deleted
- **Field Modifications**: Some field type changes may require data migration

## Related Documentation

- [Collections Guide](./COLLECTIONS.md) - Working with collections and records
- [API Records](./API_RECORDS.md) - Record CRUD operations
- [API Rules and Filters](./API_RULES_AND_FILTERS.md) - Understanding API rules

