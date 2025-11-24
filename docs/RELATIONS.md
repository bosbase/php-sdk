# Working with Relations - PHP SDK Documentation

## Overview

Relations allow you to link records between collections. BosBase supports both single and multiple relations, and provides powerful features for expanding related records and working with back-relations.

**Key Features:**
- Single and multiple relations
- Expand related records without additional requests
- Nested relation expansion (up to 6 levels)
- Back-relations for reverse lookups
- Field modifiers for append/prepend/remove operations

**Relation Field Types:**
- **Single Relation**: Links to one record (MaxSelect <= 1)
- **Multiple Relation**: Links to multiple records (MaxSelect > 1)

**Backend Behavior:**
- Relations are stored as record IDs or arrays of IDs
- Expand only includes relations the client can view (satisfies View API Rule)
- Back-relations use format: `collectionName_via_fieldName`
- Back-relation expand limited to 1000 records per field

## Setting Up Relations

### Creating a Relation Field

```php
<?php
require_once 'vendor/autoload.php';

use BosBase\BosBase;

$pb = new BosBase('http://localhost:8090');
$pb->collection('_superusers')->authWithPassword('admin@example.com', 'password');

$collection = $pb->collections->getOne('posts');

$collection['fields'][] = [
    'name' => 'user',
    'type' => 'relation',
    'collectionId' => 'users',  // ID of related collection
    'maxSelect' => 1,           // Single relation
    'required' => true
];

// Multiple relation field
$collection['fields'][] = [
    'name' => 'tags',
    'type' => 'relation',
    'collectionId' => 'tags',
    'maxSelect' => 10,          // Multiple relation (max 10)
    'minSelect' => 1,           // Minimum 1 required
    'cascadeDelete' => false    // Don't delete post when tags deleted
];

$pb->collections->update('posts', ['fields' => $collection['fields']]);
```

## Creating Records with Relations

### Single Relation

```php
// Create a post with a single user relation
$post = $pb->collection('posts')->create([
    'title' => 'My Post',
    'user' => 'USER_ID'  // Single relation ID
]);
```

### Multiple Relations

```php
// Create a post with multiple tags
$post = $pb->collection('posts')->create([
    'title' => 'My Post',
    'tags' => ['TAG_ID1', 'TAG_ID2', 'TAG_ID3']  // Array of IDs
]);
```

### Mixed Relations

```php
// Create a comment with both single and multiple relations
$comment = $pb->collection('comments')->create([
    'message' => 'Great post!',
    'post' => 'POST_ID',        // Single relation
    'user' => 'USER_ID',        // Single relation
    'tags' => ['TAG1', 'TAG2']  // Multiple relation
]);
```

## Updating Relations

### Replace All Relations

```php
// Replace all tags
$pb->collection('posts')->update('POST_ID', [
    'tags' => ['NEW_TAG1', 'NEW_TAG2']
]);
```

### Append Relations (Using + Modifier)

```php
// Append tags to existing ones
$pb->collection('posts')->update('POST_ID', [
    'tags+' => 'NEW_TAG_ID'  // Append single tag
]);

// Append multiple tags
$pb->collection('posts')->update('POST_ID', [
    'tags+' => ['TAG_ID1', 'TAG_ID2']  // Append multiple tags
]);
```

### Prepend Relations (Using + Prefix)

```php
// Prepend tags (tags will appear first)
$pb->collection('posts')->update('POST_ID', [
    '+tags' => 'PRIORITY_TAG'  // Prepend single tag
]);

// Prepend multiple tags
$pb->collection('posts')->update('POST_ID', [
    '+tags' => ['TAG1', 'TAG2']  // Prepend multiple tags
]);
```

### Remove Relations (Using - Modifier)

```php
// Remove single tag
$pb->collection('posts')->update('POST_ID', [
    'tags-' => 'TAG_ID_TO_REMOVE'
]);

// Remove multiple tags
$pb->collection('posts')->update('POST_ID', [
    'tags-' => ['TAG1', 'TAG2']
]);
```

### Complete Example

```php
// Get existing post
$post = $pb->collection('posts')->getOne('POST_ID');
print_r($post['tags']);  // ['tag1', 'tag2']

// Remove one tag, add two new ones
$pb->collection('posts')->update('POST_ID', [
    'tags-' => 'tag1',           // Remove
    'tags+' => ['tag3', 'tag4']  // Append
]);

$updated = $pb->collection('posts')->getOne('POST_ID');
print_r($updated['tags']);  // ['tag2', 'tag3', 'tag4']
```

## Expanding Relations

The `expand` parameter allows you to fetch related records in a single request, eliminating the need for multiple API calls.

### Basic Expand

```php
// Get comment with expanded user
$comment = $pb->collection('comments')->getOne('COMMENT_ID', [
    'expand' => 'user'
]);

echo $comment['expand']['user']['name'];  // "John Doe"
echo $comment['user'];              // Still the ID: "USER_ID"
```

### Expand Multiple Relations

```php
// Expand multiple relations (comma-separated)
$comment = $pb->collection('comments')->getOne('COMMENT_ID', [
    'expand' => 'user,post'
]);

echo $comment['expand']['user']['name'];   // "John Doe"
echo $comment['expand']['post']['title'];  // "My Post"
```

### Nested Expand (Dot Notation)

You can expand nested relations up to 6 levels deep using dot notation:

```php
// Expand post and its tags, and user
$comment = $pb->collection('comments')->getOne('COMMENT_ID', [
    'expand' => 'user,post.tags'
]);

// Access nested expands
print_r($comment['expand']['post']['expand']['tags']);
// Array of tag records

// Expand even deeper
$post = $pb->collection('posts')->getOne('POST_ID', [
    'expand' => 'user,comments.user'
]);

// Access: $post['expand']['comments'][0]['expand']['user']
```

### Expand with List Requests

```php
// List comments with expanded users
$comments = $pb->collection('comments')->getList(1, 20, [
    'expand' => 'user'
]);

foreach ($comments['items'] as $comment) {
    echo $comment['message'] . "\n";
    echo $comment['expand']['user']['name'] . "\n";
}
```

### Expand Single vs Multiple Relations

```php
// Single relation - expand.user is an object
$post = $pb->collection('posts')->getOne('POST_ID', [
    'expand' => 'user'
]);
echo is_array($post['expand']['user']) ? 'array' : 'object';  // "object"

// Multiple relation - expand.tags is an array
$postWithTags = $pb->collection('posts')->getOne('POST_ID', [
    'expand' => 'tags'
]);
echo is_array($postWithTags['expand']['tags']) ? 'array' : 'object';  // "array"
```

### Expand Permissions

**Important**: Only relations that satisfy the related collection's `viewRule` will be expanded. If you don't have permission to view a related record, it won't appear in the expand.

```php
// If you don't have view permission for user, expand.user will be undefined
$comment = $pb->collection('comments')->getOne('COMMENT_ID', [
    'expand' => 'user'
]);

if (isset($comment['expand']['user'])) {
    echo $comment['expand']['user']['name'];
} else {
    echo 'User not accessible or not found';
}
```

## Back-Relations

Back-relations allow you to query and expand records that reference the current record through a relation field.

### Back-Relation Syntax

The format is: `collectionName_via_fieldName`

- `collectionName`: The collection that contains the relation field
- `fieldName`: The name of the relation field that points to your record

### Example: Posts with Comments

```php
// Get a post and expand all comments that reference it
$post = $pb->collection('posts')->getOne('POST_ID', [
    'expand' => 'comments_via_post'
]);

// comments_via_post is always an array (even if original field is single)
print_r($post['expand']['comments_via_post']);
// Array of comment records
```

### Back-Relation with Nested Expand

```php
// Get post with comments, and expand each comment's user
$post = $pb->collection('posts')->getOne('POST_ID', [
    'expand' => 'comments_via_post.user'
]);

// Access nested expands
foreach ($post['expand']['comments_via_post'] as $comment) {
    echo $comment['message'] . "\n";
    echo $comment['expand']['user']['name'] . "\n";
}
```

### Filtering with Back-Relations

```php
// List posts that have at least one comment containing "hello"
$posts = $pb->collection('posts')->getList(1, 20, [
    'filter' => "comments_via_post.message ?~ 'hello'",
    'expand' => 'comments_via_post.user'
]);

foreach ($posts['items'] as $post) {
    echo $post['title'] . "\n";
    foreach ($post['expand']['comments_via_post'] as $comment) {
        echo "  - " . $comment['message'] . " by " . $comment['expand']['user']['name'] . "\n";
    }
}
```

### Back-Relation Caveats

1. **Always Multiple**: Back-relations are always treated as arrays, even if the original relation field is single. This is because one record can be referenced by multiple records.

   ```php
   // Even if comments.post is single, comments_via_post is always an array
   $post = $pb->collection('posts')->getOne('POST_ID', [
       'expand' => 'comments_via_post'
   ]);
   
   // Always an array
   echo is_array($post['expand']['comments_via_post']) ? 'true' : 'false';  // true
   ```

2. **UNIQUE Index Exception**: If the relation field has a UNIQUE index constraint, the back-relation will be treated as a single object (not an array).

3. **1000 Record Limit**: Back-relation expand is limited to 1000 records per field. For larger datasets, use separate paginated requests:

   ```php
   // Instead of expanding all comments (if > 1000)
   $post = $pb->collection('posts')->getOne('POST_ID');
   
   // Fetch comments separately with pagination
   $comments = $pb->collection('comments')->getList(1, 100, [
       'filter' => 'post = "' . $post['id'] . '"',
       'expand' => 'user',
       'sort' => '-created'
   ]);
   ```

## Complete Examples

### Example 1: Blog Post with Author and Tags

```php
// Create a blog post with relations
$post = $pb->collection('posts')->create([
    'title' => 'Getting Started with BosBase',
    'content' => 'Lorem ipsum...',
    'author' => 'AUTHOR_ID',           // Single relation
    'tags' => ['tag1', 'tag2', 'tag3'] // Multiple relation
]);

// Retrieve with all relations expanded
$fullPost = $pb->collection('posts')->getOne($post['id'], [
    'expand' => 'author,tags'
]);

echo $fullPost['title'] . "\n";
echo "Author: " . $fullPost['expand']['author']['name'] . "\n";
echo "Tags:\n";
foreach ($fullPost['expand']['tags'] as $tag) {
    echo "  - " . $tag['name'] . "\n";
}
```

### Example 2: Comment System with Nested Relations

```php
// Create a comment on a post
$comment = $pb->collection('comments')->create([
    'message' => 'Great article!',
    'post' => 'POST_ID',
    'user' => 'USER_ID'
]);

// Get post with all comments and their authors
$post = $pb->collection('posts')->getOne('POST_ID', [
    'expand' => 'author,comments_via_post.user'
]);

echo "Post: " . $post['title'] . "\n";
echo "Author: " . $post['expand']['author']['name'] . "\n";
echo "Comments (" . count($post['expand']['comments_via_post']) . "):\n";
foreach ($post['expand']['comments_via_post'] as $comment) {
    echo "  " . $comment['expand']['user']['name'] . ": " . $comment['message'] . "\n";
}
```

### Example 3: Dynamic Tag Management

```php
class PostManager {
    private $pb;

    public function __construct($pb) {
        $this->pb = $pb;
    }

    public function addTag($postId, $tagId) {
        $this->pb->collection('posts')->update($postId, [
            'tags+' => $tagId
        ]);
    }

    public function removeTag($postId, $tagId) {
        $this->pb->collection('posts')->update($postId, [
            'tags-' => $tagId
        ]);
    }

    public function setPriorityTags($postId, $tagIds) {
        // Clear existing and set priority tags first
        $post = $this->pb->collection('posts')->getOne($postId);
        $existingTags = $post['tags'] ?? [];
        $remainingTags = array_diff($existingTags, $tagIds);
        
        $this->pb->collection('posts')->update($postId, [
            'tags' => $tagIds,
            'tags+' => array_values($remainingTags)
        ]);
    }

    public function getPostWithTags($postId) {
        return $this->pb->collection('posts')->getOne($postId, [
            'expand' => 'tags'
        ]);
    }
}

// Usage
$manager = new PostManager($pb);
$manager->addTag('POST_ID', 'NEW_TAG_ID');
$post = $manager->getPostWithTags('POST_ID');
```

### Example 4: Filtering Posts by Tag

```php
// Get all posts with a specific tag
$posts = $pb->collection('posts')->getList(1, 50, [
    'filter' => 'tags.id ?= "TAG_ID"',
    'expand' => 'author,tags',
    'sort' => '-created'
]);

foreach ($posts['items'] as $post) {
    echo $post['title'] . " by " . $post['expand']['author']['name'] . "\n";
}
```

### Example 5: User Dashboard with Related Content

```php
function getUserDashboard($pb, $userId) {
    // Get user with all related content
    $user = $pb->collection('users')->getOne($userId, [
        'expand' => 'posts_via_author,comments_via_user.post'
    ]);

    echo "Dashboard for " . $user['name'] . "\n";
    echo "\nPosts (" . count($user['expand']['posts_via_author']) . "):\n";
    foreach ($user['expand']['posts_via_author'] as $post) {
        echo "  - " . $post['title'] . "\n";
    }

    echo "\nRecent Comments:\n";
    $recentComments = array_slice($user['expand']['comments_via_user'], 0, 5);
    foreach ($recentComments as $comment) {
        echo "  On \"" . $comment['expand']['post']['title'] . "\": " . $comment['message'] . "\n";
    }
}
```

## Best Practices

1. **Use Expand Wisely**: Only expand relations you actually need to reduce response size and improve performance.

2. **Handle Missing Expands**: Always check if expand data exists before accessing:

   ```php
   if (isset($record['expand']['user'])) {
       echo $record['expand']['user']['name'];
   }
   ```

3. **Pagination for Large Back-Relations**: If you expect more than 1000 related records, fetch them separately with pagination.

4. **Cache Expansion**: Consider caching expanded data on the client side to reduce API calls.

5. **Error Handling**: Handle cases where related records might not be accessible due to API rules.

6. **Nested Limit**: Remember that nested expands are limited to 6 levels deep.

## Performance Considerations

- **Expand Cost**: Expanding relations doesn't require additional round trips, but increases response payload size
- **Back-Relation Limit**: The 1000 record limit for back-relations prevents extremely large responses
- **Permission Checks**: Each expanded relation is checked against the collection's `viewRule`
- **Nested Depth**: Limit nested expands to avoid performance issues (max 6 levels supported)

## Related Documentation

- [Collections](./COLLECTIONS.md) - Collection and field configuration
- [API Rules and Filters](./API_RULES_AND_FILTERS.md) - Filtering and querying related records

