# API Records - PHP SDK Documentation

## Overview

The Records API provides comprehensive CRUD (Create, Read, Update, Delete) operations for collection records, along with powerful search, filtering, and authentication capabilities.

**Key Features:**
- Paginated list and search with filtering and sorting
- Single record retrieval with expand support
- Create, update, and delete operations
- Batch operations for multiple records
- Authentication methods (password, OAuth2, OTP)
- Email verification and password reset
- Relation expansion up to 6 levels deep
- Field selection and excerpt modifiers

**Backend Endpoints:**
- `GET /api/collections/{collection}/records` - List records
- `GET /api/collections/{collection}/records/{id}` - View record
- `POST /api/collections/{collection}/records` - Create record
- `PATCH /api/collections/{collection}/records/{id}` - Update record
- `DELETE /api/collections/{collection}/records/{id}` - Delete record
- `POST /api/batch` - Batch operations

## CRUD Operations

### List/Search Records

Returns a paginated records list with support for sorting, filtering, and expansion.

```php
<?php
require_once 'vendor/autoload.php';

use BosBase\BosBase;

$pb = new BosBase('http://127.0.0.1:8090');

// Basic list with pagination
$result = $pb->collection('posts')->getList(1, 50);

echo $result['page'];        // 1
echo $result['perPage'];     // 50
echo $result['totalItems'];  // 150
echo $result['totalPages'];  // 3
print_r($result['items']);   // Array of records
```

#### Advanced List with Filtering and Sorting

```php
// Filter and sort
$result = $pb->collection('posts')->getList(1, 50, [
    'filter' => 'created >= "2022-01-01 00:00:00" && status = "published"',
    'sort' => '-created,title',  // DESC by created, ASC by title
    'expand' => 'author,categories',
]);

// Filter with operators
$result2 = $pb->collection('posts')->getList(1, 50, [
    'filter' => 'title ~ "javascript" && views > 100',
    'sort' => '-views',
]);
```

#### Get Full List

Fetch all records at once (useful for small collections):

```php
// Get all records
$allPosts = $pb->collection('posts')->getFullList([
    'sort' => '-created',
    'filter' => 'status = "published"',
]);

// With batch size for large collections
$allPosts = $pb->collection('posts')->getFullList(200, [
    'sort' => '-created',
]);
```

#### Get First Matching Record

Get only the first record that matches a filter:

```php
$post = $pb->collection('posts')->getFirstListItem(
    'slug = "my-post-slug"',
    [
        'expand' => 'author,categories.tags',
    ]
);
```

### View Record

Retrieve a single record by ID:

```php
// Basic retrieval
$record = $pb->collection('posts')->getOne('RECORD_ID');

// With expanded relations
$record = $pb->collection('posts')->getOne('RECORD_ID', [
    'expand' => 'author,categories,tags',
]);

// Nested expand
$record = $pb->collection('comments')->getOne('COMMENT_ID', [
    'expand' => 'post.author,user',
]);

// Field selection
$record = $pb->collection('posts')->getOne('RECORD_ID', [
    'fields' => 'id,title,content,author.name',
]);
```

### Create Record

Create a new record:

```php
// Simple create
$record = $pb->collection('posts')->create([
    'title' => 'My First Post',
    'content' => 'Lorem ipsum...',
    'status' => 'draft',
]);

// Create with relations
$record = $pb->collection('posts')->create([
    'title' => 'My Post',
    'author' => 'AUTHOR_ID',           // Single relation
    'categories' => ['cat1', 'cat2'],  // Multiple relation
]);

// Create with file upload
$record = $pb->collection('posts')->create([
    'title' => 'My Post',
    'image' => new CURLFile('/path/to/image.jpg', 'image/jpeg', 'image.jpg')
]);

// Create with expand to get related data immediately
$record = $pb->collection('posts')->create([
    'title' => 'My Post',
    'author' => 'AUTHOR_ID',
], [
    'expand' => 'author',
]);
```

### Update Record

Update an existing record:

```php
// Simple update
$record = $pb->collection('posts')->update('RECORD_ID', [
    'title' => 'Updated Title',
    'status' => 'published',
]);

// Update with relations
$pb->collection('posts')->update('RECORD_ID', [
    'categories+' => 'NEW_CATEGORY_ID',  // Append
    'tags-' => 'OLD_TAG_ID',              // Remove
]);

// Update with file upload
$record = $pb->collection('posts')->update('RECORD_ID', [
    'title' => 'Updated Title',
    'image' => new CURLFile('/path/to/newimage.jpg', 'image/jpeg', 'newimage.jpg')
]);

// Update with expand
$record = $pb->collection('posts')->update('RECORD_ID', [
    'title' => 'Updated',
], [
    'expand' => 'author,categories',
]);
```

### Delete Record

Delete a record:

```php
// Simple delete
$pb->collection('posts')->delete('RECORD_ID');

// Note: Returns 204 No Content on success
// Throws error if record doesn't exist or permission denied
```

## Filter Syntax

The filter parameter supports a powerful query syntax:

### Comparison Operators

```php
// Equal
'filter' => 'status = "published"'

// Not equal
'filter' => 'status != "draft"'

// Greater than / Less than
'filter' => 'views > 100'
'filter' => 'created < "2023-01-01"'

// Greater/Less than or equal
'filter' => 'age >= 18'
'filter' => 'price <= 99.99'
```

### String Operators

```php
// Contains (like)
'filter' => 'title ~ "javascript"'
// Equivalent to: title LIKE "%javascript%"

// Not contains
'filter' => 'title !~ "deprecated"'

// Exact match (case-sensitive)
'filter' => 'email = "user@example.com"'
```

### Array Operators (for multiple relations/files)

```php
// Any of / At least one
'filter' => 'tags.id ?= "TAG_ID"'         // Any tag matches
'filter' => 'tags.name ?~ "important"'    // Any tag name contains "important"

// All must match
'filter' => 'tags.id = "TAG_ID" && tags.id = "TAG_ID2"'
```

### Logical Operators

```php
// AND
'filter' => 'status = "published" && views > 100'

// OR
'filter' => 'status = "published" || status = "featured"'

// Parentheses for grouping
'filter' => '(status = "published" || featured = true) && views > 50'
```

## Sorting

Sort records using the `sort` parameter:

```php
// Single field (ASC)
'sort' => 'created'

// Single field (DESC)
'sort' => '-created'

// Multiple fields
'sort' => '-created,title'  // DESC by created, then ASC by title

// Supported fields
'sort' => '@random'         // Random order
'sort' => '@rowid'          // Internal row ID
'sort' => 'id'              // Record ID
'sort' => 'fieldName'       // Any collection field

// Relation field sorting
'sort' => 'author.name'     // Sort by related author's name
```

## Field Selection

Control which fields are returned:

```php
// Specific fields
'fields' => 'id,title,content'

// All fields at level
'fields' => '*'

// Nested field selection
'fields' => '*,author.name,author.email'

// Excerpt modifier for text fields
'fields' => '*,content:excerpt(200,true)'
// Returns first 200 characters with ellipsis if truncated

// Combined
'fields' => '*,content:excerpt(200),author.name,author.email'
```

## Expanding Relations

Expand related records without additional API calls:

```php
// Single relation
'expand' => 'author'

// Multiple relations
'expand' => 'author,categories,tags'

// Nested relations (up to 6 levels)
'expand' => 'author.profile,categories.tags'

// Back-relations
'expand' => 'comments_via_post.user'
```

See [Relations Documentation](./RELATIONS.md) for detailed information.

## Pagination Options

```php
// Skip total count (faster queries)
$result = $pb->collection('posts')->getList(1, 50, [
    'skipTotal' => true,  // totalItems and totalPages will be -1
    'filter' => 'status = "published"',
]);

// Get Full List with batch processing
$allPosts = $pb->collection('posts')->getFullList(200, [
    'sort' => '-created',
]);
// Processes in batches of 200 to avoid memory issues
```

## Batch Operations

Execute multiple operations in a single transaction:

```php
// Create a batch
$batch = $pb->createBatch();

// Add operations
$batch->collection('posts')->create([
    'title' => 'Post 1',
    'author' => 'AUTHOR_ID',
]);

$batch->collection('posts')->create([
    'title' => 'Post 2',
    'author' => 'AUTHOR_ID',
]);

$batch->collection('tags')->update('TAG_ID', [
    'name' => 'Updated Tag',
]);

$batch->collection('categories')->delete('CAT_ID');

// Upsert (create or update based on id)
$batch->collection('posts')->upsert([
    'id' => 'EXISTING_ID',
    'title' => 'Updated Post',
]);

// Send batch request
$results = $batch->send();

// Results is an array matching the order of operations
foreach ($results as $index => $result) {
    if ($result['status'] >= 400) {
        echo "Operation $index failed: " . json_encode($result['body']) . "\n";
    } else {
        echo "Operation $index succeeded: " . json_encode($result['body']) . "\n";
    }
}
```

**Note**: Batch operations must be enabled in Dashboard > Settings > Application.

## Authentication Actions

### List Auth Methods

Get available authentication methods for a collection:

```php
$methods = $pb->collection('users')->listAuthMethods();

echo $methods['password']['enabled'];      // true/false
echo $methods['oauth2']['enabled'];       // true/false
print_r($methods['oauth2']['providers']); // Array of OAuth2 providers
echo $methods['otp']['enabled'];          // true/false
echo $methods['mfa']['enabled'];          // true/false
```

### Auth with Password

```php
$authData = $pb->collection('users')->authWithPassword(
    'user@example.com',  // username or email
    'password123'
);

// Auth data is automatically stored in pb.authStore
echo $pb->authStore->isValid() ? 'true' : 'false';    // true
echo $pb->authStore->getToken();      // JWT token
print_r($pb->authStore->getRecord()['id']);  // User ID

// Access the returned data
echo $authData['token'];
print_r($authData['record']);

// With expand
$authData = $pb->collection('users')->authWithPassword(
    'user@example.com',
    'password123',
    'profile'  // expand parameter
);
```

### Auth with OAuth2

```php
// Step 1: Get OAuth2 URL (usually done in UI)
$methods = $pb->collection('users')->listAuthMethods();
$provider = null;
foreach ($methods['oauth2']['providers'] as $p) {
    if ($p['name'] === 'google') {
        $provider = $p;
        break;
    }
}

// Redirect user to provider.authURL
// header('Location: ' . $provider['authURL']);

// Step 2: After redirect, exchange code for token
$authData = $pb->collection('users')->authWithOAuth2Code(
    'google',                    // Provider name
    'AUTHORIZATION_CODE',        // From redirect URL
    $provider['codeVerifier'],   // From step 1
    'https://yourapp.com/callback', // Redirect URL
    [                            // Optional data for new accounts
        'name' => 'John Doe',
    ]
);
```

### Auth with OTP (One-Time Password)

```php
// Step 1: Request OTP
$otpRequest = $pb->collection('users')->requestOTP('user@example.com');
// Returns: ['otpId' => "..."]

// Step 2: User enters OTP from email
// Step 3: Authenticate with OTP
$authData = $pb->collection('users')->authWithOTP(
    $otpRequest['otpId'],
    '123456'  // OTP from email
);
```

### Auth Refresh

Refresh the current auth token and get updated user data:

```php
// Refresh auth (useful on page reload)
$authData = $pb->collection('users')->authRefresh();

// Check if still valid
if ($pb->authStore->isValid()) {
    echo 'User is authenticated';
} else {
    echo 'Token expired or invalid';
}
```

### Email Verification

```php
// Request verification email
$pb->collection('users')->requestVerification('user@example.com');

// Confirm verification (on verification page)
$pb->collection('users')->confirmVerification('VERIFICATION_TOKEN');
```

### Password Reset

```php
// Request password reset email
$pb->collection('users')->requestPasswordReset('user@example.com');

// Confirm password reset (on reset page)
// Note: This invalidates all previous auth tokens
$pb->collection('users')->confirmPasswordReset(
    'RESET_TOKEN',
    'newpassword123',
    'newpassword123'  // Confirm
);
```

### Email Change

```php
// Must be authenticated first
$pb->collection('users')->authWithPassword('user@example.com', 'password');

// Request email change
$pb->collection('users')->requestEmailChange('newemail@example.com');

// Confirm email change (on confirmation page)
// Note: This invalidates all previous auth tokens
$pb->collection('users')->confirmEmailChange(
    'EMAIL_CHANGE_TOKEN',
    'currentpassword'
);
```

### Impersonate (Superuser Only)

Generate a token to authenticate as another user:

```php
// Must be authenticated as superuser
$pb->collection('_superusers')->authWithPassword('admin@example.com', 'password');

// Impersonate a user
$impersonateClient = $pb->collection('users')->impersonate('USER_ID', 3600);
// Returns a new client instance with impersonated user's token

// Use the impersonated client
$posts = $impersonateClient->collection('posts')->getFullList();

// Access the token
echo $impersonateClient->authStore->getToken();
print_r($impersonateClient->authStore->getRecord());
```

## Complete Examples

### Example 1: Blog Post Search with Filters

```php
function searchPosts($pb, $query, $categoryId, $minViews) {
    $filter = 'title ~ "' . $query . '" || content ~ "' . $query . '"';
    
    if ($categoryId) {
        $filter .= ' && categories.id ?= "' . $categoryId . '"';
    }
    
    if ($minViews) {
        $filter .= ' && views >= ' . $minViews;
    }
    
    $result = $pb->collection('posts')->getList(1, 20, [
        'filter' => $filter,
        'sort' => '-created',
        'expand' => 'author,categories',
    ]);
    
    return $result['items'];
}
```

### Example 2: User Dashboard with Related Content

```php
function getUserDashboard($pb, $userId) {
    // Get user's posts
    $posts = $pb->collection('posts')->getList(1, 10, [
        'filter' => 'author = "' . $userId . '"',
        'sort' => '-created',
        'expand' => 'categories',
    ]);
    
    // Get user's comments
    $comments = $pb->collection('comments')->getList(1, 10, [
        'filter' => 'user = "' . $userId . '"',
        'sort' => '-created',
        'expand' => 'post',
    ]);
    
    return [
        'posts' => $posts['items'],
        'comments' => $comments['items'],
    ];
}
```

### Example 3: Advanced Filtering

```php
// Complex filter example
$result = $pb->collection('posts')->getList(1, 50, [
    'filter' => '
        (status = "published" || featured = true) &&
        created >= "2023-01-01" &&
        (tags.id ?= "important" || categories.id = "news") &&
        views > 100 &&
        author.email != ""
    ',
    'sort' => '-views,created',
    'expand' => 'author.profile,tags,categories',
    'fields' => '*,content:excerpt(300),author.name,author.email',
]);
```

### Example 4: Batch Create Posts

```php
function createMultiplePosts($pb, $postsData) {
    $batch = $pb->createBatch();
    
    foreach ($postsData as $postData) {
        $batch->collection('posts')->create($postData);
    }
    
    $results = $batch->send();
    
    // Check for failures
    $failures = [];
    foreach ($results as $index => $result) {
        if ($result['status'] >= 400) {
            $failures[] = ['index' => $index, 'result' => $result];
        }
    }
    
    if (count($failures) > 0) {
        echo 'Some posts failed to create: ' . json_encode($failures) . "\n";
    }
    
    return array_map(function($r) { return $r['body']; }, $results);
}
```

### Example 5: Pagination Helper

```php
function getAllRecordsPaginated($pb, $collectionName, $options = []) {
    $allRecords = [];
    $page = 1;
    $hasMore = true;
    
    while ($hasMore) {
        $opts = array_merge($options, ['skipTotal' => true]);
        $result = $pb->collection($collectionName)->getList($page, 500, $opts);
        
        $allRecords = array_merge($allRecords, $result['items']);
        
        $hasMore = count($result['items']) === 500;
        $page++;
    }
    
    return $allRecords;
}
```

## Error Handling

```php
try {
    $record = $pb->collection('posts')->create([
        'title' => 'My Post',
    ]);
} catch (\BosBase\Exceptions\ClientResponseError $error) {
    if ($error->getStatus() === 400) {
        // Validation error
        echo 'Validation errors: ' . json_encode($error->getData()) . "\n";
    } else if ($error->getStatus() === 403) {
        // Permission denied
        echo 'Access denied' . "\n";
    } else if ($error->getStatus() === 404) {
        // Not found
        echo 'Collection or record not found' . "\n";
    } else {
        echo 'Unexpected error: ' . $error->getMessage() . "\n";
    }
}
```

## Best Practices

1. **Use Pagination**: Always use pagination for large datasets
2. **Skip Total When Possible**: Use `skipTotal: true` for better performance when you don't need counts
3. **Batch Operations**: Use batch for multiple operations to reduce round trips
4. **Field Selection**: Only request fields you need to reduce payload size
5. **Expand Wisely**: Only expand relations you actually use
6. **Filter Before Sort**: Apply filters before sorting for better performance
7. **Cache Auth Tokens**: Auth tokens are automatically stored in `authStore`, no need to manually cache
8. **Handle Errors**: Always handle authentication and permission errors gracefully

## Related Documentation

- [Collections](./COLLECTIONS.md) - Collection configuration
- [Relations](./RELATIONS.md) - Working with relations
- [API Rules and Filters](./API_RULES_AND_FILTERS.md) - Filter syntax details
- [Authentication](./AUTHENTICATION.md) - Detailed authentication guide
- [Files](./FILES.md) - File uploads and handling

