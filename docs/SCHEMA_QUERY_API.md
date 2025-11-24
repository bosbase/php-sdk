# Schema Query API - PHP SDK Documentation

## Overview

The Schema Query API provides lightweight interfaces to retrieve collection field information without fetching full collection definitions. This is particularly useful for AI systems that need to understand the structure of collections and the overall system architecture.

**Key Features:**
- Get schema for a single collection by name or ID
- Get schemas for all collections in the system
- Lightweight response with only essential field information
- Support for all collection types (base, auth, view)
- Fast and efficient queries

**Backend Endpoints:**
- `GET /api/collections/{collection}/schema` - Get single collection schema
- `GET /api/collections/schemas` - Get all collection schemas

**Note**: All Schema Query API operations require superuser authentication.

## Authentication

All Schema Query API operations require superuser authentication:

```php
<?php
require_once 'vendor/autoload.php';

use BosBase\BosBase;

$pb = new BosBase('http://127.0.0.1:8090');

// Authenticate as superuser
$pb->collection('_superusers')->authWithPassword('admin@example.com', 'password');
```

## Type Definitions

### CollectionFieldSchemaInfo

Simplified field information returned by schema queries:

```php
[
    'name' => 'string',        // Field name
    'type' => 'string',        // Field type (e.g., "text", "number", "email", "relation")
    'required' => bool,        // Whether the field is required (optional)
    'system' => bool,          // Whether the field is a system field (optional)
    'hidden' => bool,          // Whether the field is hidden (optional)
]
```

### CollectionSchemaInfo

Schema information for a single collection:

```php
[
    'name' => 'string',                        // Collection name
    'type' => 'string',                        // Collection type ("base", "auth", "view")
    'fields' => [/* CollectionFieldSchemaInfo */],  // Array of field information
]
```

## Get Single Collection Schema

Retrieves the schema (fields and types) for a single collection by name or ID.

### Basic Usage

```php
// Get schema for a collection by name
$schema = $pb->collections->getSchema('demo1');

echo $schema['name'];    // "demo1"
echo $schema['type'];    // "base"
print_r($schema['fields']);  // Array of field information

// Iterate through fields
foreach ($schema['fields'] as $field) {
    $required = isset($field['required']) && $field['required'] ? ' (required)' : '';
    echo "{$field['name']}: {$field['type']}$required\n";
}
```

### Using Collection ID

```php
// Get schema for a collection by ID
$schema = $pb->collections->getSchema('_pbc_base_123');

echo $schema['name'];  // "demo1"
```

### Handling Different Collection Types

```php
// Base collection
$baseSchema = $pb->collections->getSchema('demo1');
echo $baseSchema['type'];  // "base"

// Auth collection
$authSchema = $pb->collections->getSchema('users');
echo $authSchema['type'];  // "auth"

// View collection
$viewSchema = $pb->collections->getSchema('view1');
echo $viewSchema['type'];  // "view"
```

### Error Handling

```php
try {
    $schema = $pb->collections->getSchema('nonexistent');
} catch (\BosBase\Exceptions\ClientResponseError $error) {
    if ($error->getStatus() === 404) {
        echo "Collection not found\n";
    } else {
        echo "Error: " . $error->getMessage() . "\n";
    }
}
```

## Get All Collection Schemas

Retrieves the schema (fields and types) for all collections in the system.

### Basic Usage

```php
// Get schemas for all collections
$result = $pb->collections->getAllSchemas();

print_r($result['collections']);  // Array of all collection schemas

// Iterate through all collections
foreach ($result['collections'] as $collection) {
    echo "Collection: {$collection['name']} ({$collection['type']})\n";
    echo "Fields: " . count($collection['fields']) . "\n";
    
    // List all fields
    foreach ($collection['fields'] as $field) {
        echo "  - {$field['name']}: {$field['type']}\n";
    }
}
```

### Filtering Collections by Type

```php
$result = $pb->collections->getAllSchemas();

// Filter to only base collections
$baseCollections = array_filter($result['collections'], function($c) {
    return $c['type'] === 'base';
});

// Filter to only auth collections
$authCollections = array_filter($result['collections'], function($c) {
    return $c['type'] === 'auth';
});

// Filter to only view collections
$viewCollections = array_filter($result['collections'], function($c) {
    return $c['type'] === 'view';
});
```

### Building a Field Index

```php
// Build a map of all field names and types across all collections
$result = $pb->collections->getAllSchemas();

$fieldIndex = [];

foreach ($result['collections'] as $collection) {
    foreach ($collection['fields'] as $field) {
        $key = "{$collection['name']}.{$field['name']}";
        $fieldIndex[$key] = [
            'collection' => $collection['name'],
            'collectionType' => $collection['type'],
            'fieldName' => $field['name'],
            'fieldType' => $field['type'],
            'required' => $field['required'] ?? false,
            'system' => $field['system'] ?? false,
            'hidden' => $field['hidden'] ?? false,
        ];
    }
}

// Use the index
print_r($fieldIndex['demo1.title']);  // Field information
```

## Complete Examples

### Example 1: AI System Understanding Collection Structure

```php
<?php
require_once 'vendor/autoload.php';

use BosBase\BosBase;

$pb = new BosBase('http://127.0.0.1:8090');
$pb->collection('_superusers')->authWithPassword('admin@example.com', 'password');

// Get all collection schemas for system understanding
$result = $pb->collections->getAllSchemas();

// Create a comprehensive system overview
$systemOverview = array_map(function($collection) {
    return [
        'name' => $collection['name'],
        'type' => $collection['type'],
        'fields' => array_map(function($field) {
            return [
                'name' => $field['name'],
                'type' => $field['type'],
                'required' => $field['required'] ?? false,
            ];
        }, $collection['fields']),
    ];
}, $result['collections']);

echo "System Collections Overview:\n";
foreach ($systemOverview as $collection) {
    echo "\n{$collection['name']} ({$collection['type']}):\n";
    foreach ($collection['fields'] as $field) {
        $required = $field['required'] ? ' [required]' : '';
        echo "  {$field['name']}: {$field['type']}$required\n";
    }
}
```

### Example 2: Validating Field Existence Before Query

```php
// Check if a field exists before querying
function checkFieldExists($pb, $collectionName, $fieldName) {
    try {
        $schema = $pb->collections->getSchema($collectionName);
        foreach ($schema['fields'] as $field) {
            if ($field['name'] === $fieldName) {
                return true;
            }
        }
        return false;
    } catch (\Exception $error) {
        return false;
    }
}

// Usage
$hasTitleField = checkFieldExists($pb, 'demo1', 'title');
if ($hasTitleField) {
    // Safe to query the field
    $records = $pb->collection('demo1')->getList(1, 20, [
        'fields' => 'id,title',
    ]);
}
```

### Example 3: Dynamic Form Generation

```php
// Generate form fields based on collection schema
function generateFormFields($pb, $collectionName) {
    $schema = $pb->collections->getSchema($collectionName);
    
    $fields = array_filter($schema['fields'], function($field) {
        return !($field['system'] ?? false) && !($field['hidden'] ?? false);
    });
    
    return array_map(function($field) {
        return [
            'name' => $field['name'],
            'type' => $field['type'],
            'required' => $field['required'] ?? false,
            'label' => ucfirst($field['name']),
        ];
    }, $fields);
}

// Usage
$formFields = generateFormFields($pb, 'demo1');
print_r($formFields);
// Output: [
//   ['name' => 'title', 'type' => 'text', 'required' => true, 'label' => 'Title'],
//   ['name' => 'description', 'type' => 'text', 'required' => false, 'label' => 'Description'],
//   ...
// ]
```

### Example 4: Schema Comparison

```php
// Compare schemas between two collections
function compareSchemas($pb, $collection1, $collection2) {
    $schema1 = $pb->collections->getSchema($collection1);
    $schema2 = $pb->collections->getSchema($collection2);
    
    $fields1 = array_column($schema1['fields'], 'name');
    $fields2 = array_column($schema2['fields'], 'name');
    
    return [
        'common' => array_intersect($fields1, $fields2),
        'onlyIn1' => array_diff($fields1, $fields2),
        'onlyIn2' => array_diff($fields2, $fields1),
    ];
}

// Usage
$comparison = compareSchemas($pb, 'demo1', 'demo2');
echo "Common fields: " . implode(', ', $comparison['common']) . "\n";
echo "Only in demo1: " . implode(', ', $comparison['onlyIn1']) . "\n";
echo "Only in demo2: " . implode(', ', $comparison['onlyIn2']) . "\n";
```

## Response Structure

### Single Collection Schema Response

```json
{
  "name": "demo1",
  "type": "base",
  "fields": [
    {
      "name": "id",
      "type": "text",
      "required": true,
      "system": true,
      "hidden": false
    },
    {
      "name": "title",
      "type": "text",
      "required": true,
      "system": false,
      "hidden": false
    },
    {
      "name": "description",
      "type": "text",
      "required": false,
      "system": false,
      "hidden": false
    }
  ]
}
```

### All Collections Schemas Response

```json
{
  "collections": [
    {
      "name": "demo1",
      "type": "base",
      "fields": [...]
    },
    {
      "name": "users",
      "type": "auth",
      "fields": [...]
    },
    {
      "name": "view1",
      "type": "view",
      "fields": [...]
    }
  ]
}
```

## Use Cases

### 1. AI System Design
AI systems can query all collection schemas to understand the overall database structure and design queries or operations accordingly.

### 2. Code Generation
Generate client-side code, TypeScript types, or form components based on collection schemas.

### 3. Documentation Generation
Automatically generate API documentation or data dictionaries from collection schemas.

### 4. Schema Validation
Validate queries or operations before execution by checking field existence and types.

### 5. Migration Planning
Compare schemas between environments or versions to plan migrations.

### 6. Dynamic UI Generation
Create dynamic forms, tables, or interfaces based on collection field definitions.

## Performance Considerations

- **Lightweight**: Schema queries return only essential field information, not full collection definitions
- **Efficient**: Much faster than fetching full collection objects
- **Cached**: Results can be cached for better performance
- **Batch**: Use `getAllSchemas()` to get all schemas in a single request

## Error Handling

```php
try {
    $schema = $pb->collections->getSchema('demo1');
} catch (\BosBase\Exceptions\ClientResponseError $error) {
    switch ($error->getStatus()) {
        case 401:
            echo "Authentication required\n";
            break;
        case 403:
            echo "Superuser access required\n";
            break;
        case 404:
            echo "Collection not found\n";
            break;
        default:
            echo "Unexpected error: " . $error->getMessage() . "\n";
    }
}
```

## Best Practices

1. **Cache Results**: Schema information rarely changes, so cache results when appropriate
2. **Error Handling**: Always handle 404 errors for non-existent collections
3. **Filter System Fields**: When building UI, filter out system and hidden fields
4. **Batch Queries**: Use `getAllSchemas()` when you need multiple collection schemas
5. **Type Safety**: Use proper type checking when working with schema data

## Related Documentation

- [Collection API](./COLLECTION_API.md) - Full collection management API
- [Records API](./API_RECORDS.md) - Record CRUD operations

