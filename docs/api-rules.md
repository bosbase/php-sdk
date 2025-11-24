# API Rules Documentation - PHP SDK

API Rules are collection access controls and data filters that determine who can perform actions on your collections and what data they can access.

## Overview

Each collection has 5 standard API rules, corresponding to specific API actions:

- **`listRule`** - Controls read/list access
- **`viewRule`** - Controls read/view access  
- **`createRule`** - Controls create access
- **`updateRule`** - Controls update access
- **`deleteRule`** - Controls delete access

Auth collections have two additional rules:

- **`manageRule`** - Admin-like permissions for managing auth records
- **`authRule`** - Additional constraints applied during authentication

## Rule Values

Each rule can be set to one of three values:

### 1. `null` (Locked)
Only authorized superusers can perform the action.

```php
$pb->collections->setListRule('products', null);
```

### 2. `""` (Empty String - Public)
Anyone (superusers, authorized users, and guests) can perform the action.

```php
$pb->collections->setListRule('products', '');
```

### 3. Non-empty String (Filter Expression)
Only users satisfying the filter expression can perform the action.

```php
$pb->collections->setListRule('products', '@request.auth.id != ""');
```

## Setting Rules

### Individual Rules

```php
// Set list rule
$pb->collections->setListRule('products', '@request.auth.id != ""');

// Set view rule
$pb->collections->setViewRule('products', '@request.auth.id != ""');

// Set create rule
$pb->collections->setCreateRule('products', '@request.auth.id != ""');

// Set update rule
$pb->collections->setUpdateRule('products', '@request.auth.id != "" && author.id ?= @request.auth.id');

// Set delete rule
$pb->collections->setDeleteRule('products', null);  // Only superusers
```

### Bulk Rule Updates

```php
$pb->collections->setRules('products', [
    'listRule' => '@request.auth.id != ""',
    'viewRule' => '@request.auth.id != ""',
    'createRule' => '@request.auth.id != ""',
    'updateRule' => '@request.auth.id != "" && createdBy = @request.auth.id',
    'deleteRule' => null,
]);
```

## Related Documentation

- [API Rules and Filters](./API_RULES_AND_FILTERS.md) - Detailed filter syntax
- [Collections](./COLLECTIONS.md) - Collection management

