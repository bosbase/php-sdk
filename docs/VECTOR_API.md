# Vector Database API - PHP SDK Documentation

Vector database operations for semantic search, RAG (Retrieval-Augmented Generation), and AI applications.

> **Note**: Vector operations are currently implemented using sqlite-vec but are designed with abstraction in mind to support future vector database providers.

## Overview

The Vector API provides a unified interface for working with vector embeddings, enabling you to:
- Store and search vector embeddings
- Perform similarity search
- Build RAG applications
- Create recommendation systems
- Enable semantic search capabilities

## Getting Started

```php
<?php
require_once 'vendor/autoload.php';

use BosBase\BosBase;

$pb = new BosBase('http://localhost:8090');

// Authenticate as superuser (vectors require superuser auth)
$pb->collection('_superusers')->authWithPassword('admin@example.com', 'password');
```

## Types

### VectorEmbedding
Array of numbers representing a vector embedding.

```php
// In PHP, this is simply an array of floats
$vector = [0.1, 0.2, 0.3, 0.4];
```

### VectorDocument
A vector document with embedding, metadata, and optional content.

```php
[
    'id' => 'string',                    // Unique identifier (auto-generated if not provided)
    'vector' => [/* array of floats */], // The vector embedding
    'metadata' => [/* key-value pairs */], // Optional metadata
    'content' => 'string',              // Optional text content
]
```

### VectorSearchOptions
Options for vector similarity search.

```php
[
    'queryVector' => [/* array of floats */], // Query vector to search for
    'limit' => 10,                            // Max results (default: 10, max: 100)
    'filter' => [/* metadata filter */],      // Optional metadata filter
    'minScore' => 0.7,                        // Minimum similarity score threshold
    'maxDistance' => 0.3,                     // Maximum distance threshold
    'includeDistance' => true,                // Include distance in results
    'includeContent' => true,                 // Include full document content
]
```

## Collection Management

### Create Collection

Create a new vector collection with specified dimension and distance metric.

```php
$pb->vectors->createCollection('documents', [
    'dimension' => 384,      // Vector dimension (default: 384)
    'distance' => 'cosine'   // Distance metric: 'cosine' (default), 'l2', 'dot'
]);

// Minimal example (uses defaults)
$pb->vectors->createCollection('documents', []);
```

**Parameters:**
- `name` (string): Collection name
- `config` (array):
  - `dimension` (int, optional): Vector dimension. Default: 384
  - `distance` (string, optional): Distance metric. Default: 'cosine'
  - Options: 'cosine', 'l2', 'dot'

### List Collections

Get all available vector collections.

```php
$collections = $pb->vectors->listCollections();

foreach ($collections as $collection) {
    echo "{$collection['name']}: " . ($collection['count'] ?? 0) . " vectors\n";
}
```

**Response:**
```php
[
    [
        'name' => 'string',
        'count' => 0,        // optional
        'dimension' => 384,  // optional
    ],
    // ...
]
```

### Update Collection

Update a vector collection configuration (distance metric and options).
Note: Collection name and dimension cannot be changed after creation.

```php
$pb->vectors->updateCollection('documents', [
    'distance' => 'l2'  // Change from cosine to L2
]);

// Update with options
$pb->vectors->updateCollection('documents', [
    'distance' => 'inner_product',
    'options' => ['customOption' => 'value']
]);
```

### Delete Collection

Delete a vector collection and all its data.

```php
$pb->vectors->deleteCollection('documents');
```

**⚠️ Warning**: This permanently deletes the collection and all vectors in it!

## Document Operations

### Insert Document

Insert a single vector document.

```php
// With custom ID
$result = $pb->vectors->insert([
    'id' => 'doc_001',
    'vector' => [0.1, 0.2, 0.3, 0.4],
    'metadata' => ['category' => 'tech', 'tags' => ['AI', 'ML']],
    'content' => 'Document about machine learning'
], 'documents');

echo "Inserted: {$result['id']}\n";

// Without ID (auto-generated)
$result2 = $pb->vectors->insert([
    'vector' => [0.5, 0.6, 0.7, 0.8],
    'content' => 'Another document'
], 'documents');
```

**Response:**
```php
[
    'id' => 'string',        // The document ID
    'success' => true,
]
```

### Batch Insert

Insert multiple vector documents efficiently.

```php
$result = $pb->vectors->batchInsert([
    'documents' => [
        ['vector' => [0.1, 0.2, 0.3], 'metadata' => ['cat' => 'A'], 'content' => 'Doc A'],
        ['vector' => [0.4, 0.5, 0.6], 'metadata' => ['cat' => 'B'], 'content' => 'Doc B'],
        ['vector' => [0.7, 0.8, 0.9], 'metadata' => ['cat' => 'A'], 'content' => 'Doc C'],
    ],
    'skipDuplicates' => true  // Skip documents with duplicate IDs
], 'documents');

echo "Inserted: {$result['insertedCount']}\n";
echo "Failed: {$result['failedCount']}\n";
print_r($result['ids']);
```

**Response:**
```php
[
    'insertedCount' => 3,   // Number of successfully inserted vectors
    'failedCount' => 0,     // Number of failed insertions
    'ids' => ['id1', 'id2'], // List of inserted document IDs
    'errors' => [],         // Error messages (if any)
]
```

### Get Document

Retrieve a vector document by ID.

```php
$doc = $pb->vectors->get('doc_001', 'documents');
echo "Vector: " . json_encode($doc['vector']) . "\n";
echo "Content: {$doc['content']}\n";
print_r($doc['metadata']);
```

### Update Document

Update an existing vector document.

```php
// Update all fields
$pb->vectors->update('doc_001', [
    'vector' => [0.9, 0.8, 0.7, 0.6],
    'metadata' => ['updated' => true],
    'content' => 'Updated content'
], 'documents');

// Partial update (only metadata and content)
$pb->vectors->update('doc_001', [
    'metadata' => ['category' => 'updated'],
    'content' => 'New content'
], 'documents');
```

### Delete Document

Delete a vector document.

```php
$pb->vectors->delete('doc_001', 'documents');
```

### List Documents

List all documents in a collection with pagination.

```php
// Get first page
$result = $pb->vectors->listDocuments('documents', 1, 100);

echo "Page {$result['page']} of {$result['totalPages']}\n";
foreach ($result['items'] as $item) {
    echo "{$item['id']}: {$item['content']}\n";
}
```

**Response:**
```php
[
    'page' => 1,
    'perPage' => 100,
    'totalItems' => 50,
    'totalPages' => 1,
    'items' => [/* VectorDocument[] */],
]
```

## Vector Search

### Basic Search

Perform similarity search on vectors.

```php
$results = $pb->vectors->search([
    'queryVector' => [0.1, 0.2, 0.3, 0.4],
    'limit' => 10
], 'documents');

foreach ($results['results'] as $result) {
    echo "Score: {$result['score']} - {$result['document']['content']}\n";
}
```

### Advanced Search

```php
$results = $pb->vectors->search([
    'queryVector' => [0.1, 0.2, 0.3, 0.4],
    'limit' => 20,
    'minScore' => 0.7,              // Minimum similarity threshold
    'maxDistance' => 0.3,          // Maximum distance threshold
    'includeDistance' => true,      // Include distance metric
    'includeContent' => true,       // Include full content
    'filter' => ['category' => 'tech'] // Filter by metadata
], 'documents');

echo "Found " . ($results['totalMatches'] ?? 0) . " matches in " . ($results['queryTime'] ?? 0) . "ms\n";
foreach ($results['results'] as $r) {
    echo "Score: {$r['score']}, Distance: " . ($r['distance'] ?? 'N/A') . "\n";
    echo "Content: {$r['document']['content']}\n";
}
```

## Common Use Cases

### Semantic Search

```php
// 1. Generate embeddings for your documents
$documents = [
    ['text' => 'Introduction to machine learning', 'id' => 'doc1'],
    ['text' => 'Deep learning fundamentals', 'id' => 'doc2'],
    ['text' => 'Natural language processing', 'id' => 'doc3'],
];

foreach ($documents as $doc) {
    // Generate embedding using your model
    $embedding = generateEmbedding($doc['text']);
    
    $pb->vectors->insert([
        'id' => $doc['id'],
        'vector' => $embedding,
        'content' => $doc['text'],
        'metadata' => ['type' => 'tutorial']
    ], 'articles');
}

// 2. Search
$queryEmbedding = generateEmbedding('What is AI?');
$results = $pb->vectors->search([
    'queryVector' => $queryEmbedding,
    'limit' => 5,
    'minScore' => 0.75
], 'articles');

foreach ($results['results'] as $r) {
    echo number_format($r['score'], 2) . ": {$r['document']['content']}\n";
}
```

### RAG (Retrieval-Augmented Generation)

```php
function retrieveContext($pb, $query, $limit = 5) {
    $queryEmbedding = generateEmbedding($query);
    
    $results = $pb->vectors->search([
        'queryVector' => $queryEmbedding,
        'limit' => $limit,
        'minScore' => 0.75,
        'includeContent' => true
    ], 'knowledge_base');
    
    return array_map(function($r) {
        return $r['document']['content'];
    }, $results['results']);
}

// Use with your LLM
$context = retrieveContext($pb, 'What are best practices for security?');
$answer = generateLLMResponse($context, $userQuery);
```

## Best Practices

### Vector Dimensions

Choose the right dimension for your use case:

- **OpenAI embeddings**: 1536 (`text-embedding-3-large`)
- **Sentence Transformers**: 384-768
  - `all-MiniLM-L6-v2`: 384
  - `all-mpnet-base-v2`: 768
- **Custom models**: Match your model's output

### Distance Metrics

| Metric | Best For | Notes |
|--------|----------|-------|
| `cosine` | Text embeddings | Works well with normalized vectors |
| `l2` | General similarity | Euclidean distance |
| `dot` | Performance | Requires normalized vectors |

### Performance Tips

1. **Use batch insert** for multiple vectors
2. **Set appropriate limits** to avoid excessive results
3. **Use metadata filtering** to narrow search space
4. **Enable indexes** (automatic with sqlite-vec)

### Security

- All vector endpoints require superuser authentication
- Never expose credentials in client-side code
- Use environment variables for sensitive data

## Error Handling

```php
try {
    $results = $pb->vectors->search([
        'queryVector' => [0.1, 0.2, 0.3]
    ], 'documents');
} catch (\BosBase\Exceptions\ClientResponseError $error) {
    if ($error->getStatus() === 404) {
        echo "Collection not found\n";
    } else if ($error->getStatus() === 400) {
        echo "Invalid request: " . json_encode($error->getData()) . "\n";
    } else {
        echo "Error: " . $error->getMessage() . "\n";
    }
}
```

## Related Documentation

- [LLM Documents](./LLM_DOCUMENTS.md) - LLM document storage
- [LangChaingo API](./LANGCHAINGO_API.md) - LangChain integration
- [Collection API](./COLLECTION_API.md) - Collection management

