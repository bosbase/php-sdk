# LLM Document API - PHP SDK Documentation

The `LLMDocumentService` wraps the `/api/llm-documents` endpoints that are backed by the embedded chromem-go vector store (persisted in rqlite). Each document contains text content, optional metadata and an embedding vector that can be queried with semantic search.

## Getting Started

```php
<?php
require_once 'vendor/autoload.php';

use BosBase\BosBase;

$pb = new BosBase('http://localhost:8090');

// Create a logical namespace for your documents
$pb->llmDocuments->createCollection('knowledge-base', ['domain' => 'internal']);
```

## Insert Documents

```php
$doc = $pb->llmDocuments->insert([
    'content' => 'Leaves are green because chlorophyll absorbs red and blue light.',
    'metadata' => ['topic' => 'biology'],
], 'knowledge-base');

$pb->llmDocuments->insert([
    'id' => 'sky',
    'content' => 'The sky is blue because of Rayleigh scattering.',
    'metadata' => ['topic' => 'physics'],
], 'knowledge-base');
```

## Query Documents

```php
$result = $pb->llmDocuments->query([
    'queryText' => 'Why is the sky blue?',
    'limit' => 3,
    'where' => ['topic' => 'physics'],
], 'knowledge-base');

foreach ($result['results'] as $match) {
    echo "{$match['id']}: {$match['similarity']}\n";
}
```

## Manage Documents

```php
// Update a document
$pb->llmDocuments->update('sky', [
    'metadata' => ['topic' => 'physics', 'reviewed' => 'true']
], 'knowledge-base');

// List documents with pagination
$page = $pb->llmDocuments->list('knowledge-base', 1, 25);

// Delete unwanted entries
$pb->llmDocuments->delete('sky', 'knowledge-base');
```

## HTTP Endpoints

| Method | Path | Purpose |
| --- | --- | --- |
| `GET /api/llm-documents/collections` | List collections |
| `POST /api/llm-documents/collections/{name}` | Create collection |
| `DELETE /api/llm-documents/collections/{name}` | Delete collection |
| `GET /api/llm-documents/{collection}` | List documents |
| `POST /api/llm-documents/{collection}` | Insert document |
| `GET /api/llm-documents/{collection}/{id}` | Fetch document |
| `PATCH /api/llm-documents/{collection}/{id}` | Update document |
| `DELETE /api/llm-documents/{collection}/{id}` | Delete document |
| `POST /api/llm-documents/{collection}/documents/query` | Query by semantic similarity |

## Related Documentation

- [Vector API](./VECTOR_API.md) - Vector database operations
- [LangChaingo API](./LANGCHAINGO_API.md) - LangChain integration

