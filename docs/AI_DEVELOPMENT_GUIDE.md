# AI Development Guide - PHP SDK

This guide provides a comprehensive, fast reference for AI systems to quickly develop applications using the BosBase PHP SDK. All examples are production-ready and follow best practices.

## Table of Contents

1. [Authentication](#authentication)
2. [Initialize Collections](#initialize-collections)
3. [Define Collection Fields](#define-collection-fields)
4. [Add Data to Collections](#add-data-to-collections)
5. [Modify Collection Data](#modify-collection-data)
6. [Delete Data from Collections](#delete-data-from-collections)
7. [Query Collection Contents](#query-collection-contents)
8. [Add and Delete Fields from Collections](#add-and-delete-fields-from-collections)
9. [Query Collection Field Information](#query-collection-field-information)
10. [Upload Files](#upload-files)
11. [Query Logs](#query-logs)

## Authentication

### Initialize Client

```php
<?php
require_once 'vendor/autoload.php';

use BosBase\BosBase;

$pb = new BosBase('http://localhost:8090');
```

### Password Authentication

```php
// Authenticate with email/username and password
$authData = $pb->collection('users')->authWithPassword(
    'user@example.com',
    'password123'
);

// Auth data is automatically stored
echo $pb->authStore->isValid() ? 'true' : 'false';  // true
echo $pb->authStore->getToken();    // JWT token
print_r($pb->authStore->getRecord());   // User record
```

### Check Authentication Status

```php
if ($pb->authStore->isValid()) {
    echo 'Authenticated as: ' . $pb->authStore->getRecord()['email'] . "\n";
} else {
    echo "Not authenticated\n";
}
```

### Logout

```php
$pb->authStore->clear();
```

## Initialize Collections

### Create Base Collection

```php
$collection = $pb->collections->createBase('articles', [
    'fields' => [
        [
            'name' => 'title',
            'type' => 'text',
            'required' => true,
        ],
    ],
]);
```

### Create Auth Collection

```php
$collection = $pb->collections->createAuth('users', [
    'fields' => [
        [
            'name' => 'name',
            'type' => 'text',
        ],
    ],
]);
```

## Add Data to Collections

```php
$record = $pb->collection('articles')->create([
    'title' => 'My Article',
    'content' => 'Article content',
]);
```

## Modify Collection Data

```php
$record = $pb->collection('articles')->update('RECORD_ID', [
    'title' => 'Updated Title',
]);
```

## Delete Data from Collections

```php
$pb->collection('articles')->delete('RECORD_ID');
```

## Query Collection Contents

```php
// List records
$result = $pb->collection('articles')->getList(1, 20, [
    'filter' => 'status = "published"',
    'sort' => '-created',
]);

// Get single record
$record = $pb->collection('articles')->getOne('RECORD_ID');
```

## Query Collection Field Information

```php
// Get schema for a collection
$schema = $pb->collections->getSchema('articles');
print_r($schema['fields']);

// Get all collection schemas
$result = $pb->collections->getAllSchemas();
print_r($result['collections']);
```

## Upload Files

```php
$record = $pb->collection('articles')->create([
    'title' => 'Article with Image',
    'image' => new CURLFile('/path/to/image.jpg', 'image/jpeg', 'image.jpg'),
]);
```

## Query Logs

```php
// Get logs (requires superuser)
$logs = $pb->logs->getList(1, 50, 'data.status >= 400');
print_r($logs['items']);
```

## Related Documentation

- [Authentication](./AUTHENTICATION.md) - Detailed authentication guide
- [Collections](./COLLECTIONS.md) - Collection management
- [API Records](./API_RECORDS.md) - Record operations

