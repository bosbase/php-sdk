# Built-in Users Collection Guide - PHP SDK

This guide explains how to use the built-in `users` collection for authentication, registration, and API rules. **The `users` collection is automatically created when BosBase is initialized and does not need to be created manually.**

## Overview

The `users` collection is a **built-in auth collection** that is automatically created when BosBase starts. It has:

- **Collection ID**: `_pb_users_auth_`
- **Collection Name**: `users`
- **Type**: `auth` (authentication collection)
- **Purpose**: User accounts, authentication, and authorization

**Important**: 
- ✅ **DO NOT** create a new `users` collection manually
- ✅ **DO** use the existing built-in `users` collection
- ✅ The collection already has proper API rules configured
- ✅ It supports password, OAuth2, and OTP authentication

### Getting Users Collection Information

```php
<?php
require_once 'vendor/autoload.php';

use BosBase\BosBase;

$pb = new BosBase('http://127.0.0.1:8090');
$pb->collection('_superusers')->authWithPassword('admin@example.com', 'password');

// Get the users collection details
$usersCollection = $pb->collections->getOne('users');
// or by ID
$usersCollection = $pb->collections->getOne('_pb_users_auth_');

echo "Collection ID: {$usersCollection['id']}\n";
echo "Collection Name: {$usersCollection['name']}\n";
echo "Collection Type: {$usersCollection['type']}\n";
print_r($usersCollection['fields']);
```

## User Registration

```php
// Register a new user
$record = $pb->collection('users')->create([
    'email' => 'user@example.com',
    'password' => 'password123',
    'passwordConfirm' => 'password123',
    'name' => 'John Doe',
]);

echo "User registered: {$record['id']}\n";
```

## User Login/Authentication

```php
// Authenticate with password
$authData = $pb->collection('users')->authWithPassword('user@example.com', 'password123');

echo "Authenticated as: {$authData['record']['email']}\n";
echo "Token: {$authData['token']}\n";
```

## API Rules

The `users` collection comes with these default API rules:

```php
[
    'listRule' => 'id = @request.auth.id',    // Users can only list themselves
    'viewRule' => 'id = @request.auth.id',    // Users can only view themselves
    'createRule' => '',                       // Anyone can register (public)
    'updateRule' => 'id = @request.auth.id', // Users can only update themselves
    'deleteRule' => 'id = @request.auth.id',  // Users can only delete themselves
]
```

## Related Documentation

- [Authentication](./AUTHENTICATION.md) - Detailed authentication guide
- [API Records](./API_RECORDS.md) - Record operations
- [OAuth2 Configuration](./OAUTH2_CONFIGURATION.md) - OAuth2 setup

