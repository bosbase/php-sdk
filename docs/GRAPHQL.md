# GraphQL Queries - PHP SDK Documentation

Use `$pb->graphql->query()` to call `/api/graphql` with your current auth token. It returns `['data' => ..., 'errors' => ..., 'extensions' => ...]`.

> Authentication: the GraphQL endpoint is **superuser-only**. Authenticate as a superuser before calling GraphQL, e.g. `$pb->collection("_superusers")->authWithPassword($email, $password);`.

## Single-table query

```php
<?php
require_once 'vendor/autoload.php';

use BosBase\BosBase;

$pb = new BosBase('http://127.0.0.1:8090');
$pb->collection('_superusers')->authWithPassword('admin@example.com', 'password');

$query = '
  query ActiveUsers($limit: Int!) {
    records(collection: "users", perPage: $limit, filter: "status = true") {
      items { id data }
    }
  }
';

$result = $pb->graphql->query($query, ['limit' => 5]);
$data = $result['data'] ?? null;
$errors = $result['errors'] ?? null;
```

## Multi-table join via expands

```php
$query = '
  query PostsWithAuthors {
    records(
      collection: "posts",
      expand: ["author", "author.profile"],
      sort: "-created"
    ) {
      items {
        id
        data  # expanded relations live under data.expand
      }
    }
  }
';

$result = $pb->graphql->query($query);
$data = $result['data'];
```

## Conditional query with variables

```php
$query = '
  query FilteredOrders($minTotal: Float!, $state: String!) {
    records(
      collection: "orders",
      filter: "total >= $minTotal && status = $state",
      sort: "created"
    ) {
      items { id data }
    }
  }
';

$variables = ['minTotal' => 100, 'state' => 'paid'];
$result = $pb->graphql->query($query, $variables);
```

Use the `filter`, `sort`, `page`, `perPage`, and `expand` arguments to mirror REST list behavior while keeping query logic in GraphQL.

## Create a record

```php
$mutation = '
  mutation CreatePost($data: JSON!) {
    createRecord(collection: "posts", data: $data, expand: ["author"]) {
      id
      data
    }
  }
';

$data = ['title' => 'Hello', 'author' => 'USER_ID'];
$result = $pb->graphql->query($mutation, ['data' => $data]);
$createdPost = $result['data']['createRecord'] ?? null;
```

## Update a record

```php
$mutation = '
  mutation UpdatePost($id: ID!, $data: JSON!) {
    updateRecord(collection: "posts", id: $id, data: $data) {
      id
      data
    }
  }
';

$pb->graphql->query($mutation, [
    'id' => 'POST_ID',
    'data' => ['title' => 'Updated title'],
]);
```

## Delete a record

```php
$mutation = '
  mutation DeletePost($id: ID!) {
    deleteRecord(collection: "posts", id: $id)
  }
';

$pb->graphql->query($mutation, ['id' => 'POST_ID']);
```

## Error Handling

```php
try {
    $result = $pb->graphql->query($query, $variables);
    
    if (isset($result['errors']) && count($result['errors']) > 0) {
        echo "GraphQL errors:\n";
        foreach ($result['errors'] as $error) {
            echo "  - " . ($error['message'] ?? 'Unknown error') . "\n";
        }
    } else {
        // Use $result['data']
        print_r($result['data']);
    }
} catch (\BosBase\Exceptions\ClientResponseError $error) {
    if ($error->getStatus() === 401) {
        echo "Not authenticated as superuser\n";
    } else {
        echo "Error: " . $error->getMessage() . "\n";
    }
}
```

## Related Documentation

- [API Records](./API_RECORDS.md) - REST API for records
- [Authentication](./AUTHENTICATION.md) - User authentication

