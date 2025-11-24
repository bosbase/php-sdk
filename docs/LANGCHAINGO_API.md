# LangChaingo API - PHP SDK Documentation

BosBase exposes the `/api/langchaingo` endpoints so you can run LangChainGo powered workflows without leaving the platform. The PHP SDK wraps these endpoints with the `$pb->langchaingo` service.

The service exposes four high-level methods:

| Method | HTTP Endpoint | Description |
| --- | --- | --- |
| `$pb->langchaingo->completions()` | `POST /api/langchaingo/completions` | Runs a chat/completion call using the configured LLM provider. |
| `$pb->langchaingo->rag()` | `POST /api/langchaingo/rag` | Runs a retrieval-augmented generation pass over an `llmDocuments` collection. |
| `$pb->langchaingo->queryDocuments()` | `POST /api/langchaingo/documents/query` | Asks an OpenAI-backed chain to answer questions over `llmDocuments` and optionally return matched sources. |
| `$pb->langchaingo->sql()` | `POST /api/langchaingo/sql` | Lets OpenAI draft and execute SQL against your BosBase database, then returns the results. |

Each method accepts an optional `model` block:

```php
[
    'provider' => 'openai' | 'ollama' | 'string',  // optional
    'model' => 'string',                           // optional
    'apiKey' => 'string',                          // optional
    'baseUrl' => 'string',                         // optional
]
```

If you omit the `model` section, BosBase defaults to `provider: "openai"` and `model: "gpt-4o-mini"` with credentials read from the server environment. Passing an `apiKey` lets you override server defaults on a per-request basis.

## Text + Chat Completions

```php
<?php
require_once 'vendor/autoload.php';

use BosBase\BosBase;

$pb = new BosBase('http://localhost:8090');

$completion = $pb->langchaingo->completions([
    'model' => ['provider' => 'openai', 'model' => 'gpt-4o-mini'],
    'messages' => [
        ['role' => 'system', 'content' => 'Answer in one sentence.'],
        ['role' => 'user', 'content' => 'Explain Rayleigh scattering.'],
    ],
    'temperature' => 0.2,
]);

echo $completion['content'];
```

The completion response mirrors the LangChainGo `ContentResponse` shape, so you can inspect the `functionCall`, `toolCalls`, or `generationInfo` fields when you need more than plain text.

## Retrieval-Augmented Generation (RAG)

Pair the LangChaingo endpoints with the `/api/llm-documents` store to build RAG workflows. The backend automatically uses the chromem-go collection configured for the target LLM collection.

```php
$answer = $pb->langchaingo->rag([
    'collection' => 'knowledge-base',
    'question' => 'Why is the sky blue?',
    'topK' => 4,
    'returnSources' => true,
    'filters' => [
        'where' => ['topic' => 'physics'],
    ],
]);

echo $answer['answer'] . "\n";
if (isset($answer['sources'])) {
    foreach ($answer['sources'] as $source) {
        $score = isset($source['score']) ? number_format($source['score'], 3) : 'N/A';
        $title = $source['metadata']['title'] ?? 'N/A';
        echo "$score: $title\n";
    }
}
```

Set `promptTemplate` when you want to control how the retrieved context is stuffed into the answer prompt:

```php
$pb->langchaingo->rag([
    'collection' => 'knowledge-base',
    'question' => 'Summarize the explanation below in 2 sentences.',
    'promptTemplate' => "Context:\n{{.context}}\n\nQuestion: {{.question}}\nSummary:",
]);
```

## LLM Document Queries

> **Note**: This interface is only available to superusers.

When you want to pose a question to a specific `llmDocuments` collection and have LangChaingo+OpenAI synthesize an answer, use `queryDocuments`. It mirrors the RAG arguments but takes a `query` field:

```php
$response = $pb->langchaingo->queryDocuments([
    'collection' => 'knowledge-base',
    'query' => 'List three bullet points about Rayleigh scattering.',
    'topK' => 3,
    'returnSources' => true,
]);

echo $response['answer'] . "\n";
print_r($response['sources']);
```

## SQL Generation + Execution

> **Important Notes**:
> - This interface is only available to superusers. Requests authenticated with regular `users` tokens return a `401 Unauthorized`.
> - It is recommended to execute query statements (SELECT) only.
> - **Do not use this interface for adding or modifying table structures.** Collection interfaces should be used instead for managing database schema.
> - Directly using this interface for initializing table structures and adding or modifying database tables will cause errors that prevent the automatic generation of APIs.

Superuser tokens (`_superusers` records) can ask LangChaingo to have OpenAI propose a SQL statement, execute it, and return both the generated SQL and execution output.

```php
$result = $pb->langchaingo->sql([
    'query' => "Add a demo project row if it doesn't exist, then list the 5 most recent projects.",
    'tables' => ['projects'], // optional hint to limit which tables the model sees
    'topK' => 5,
]);

echo $result['sql'] . "\n";    // Generated SQL
echo $result['answer'] . "\n"; // Model's summary of the execution
print_r($result['columns']);
print_r($result['rows']);
```

Use `tables` to restrict which table definitions and sample rows are passed to the model, and `topK` to control how many rows the model should target when building queries. You can also pass the optional `model` block described above to override the default OpenAI model or key for this call.

## Related Documentation

- [LLM Documents](./LLM_DOCUMENTS.md) - LLM document storage
- [Vector API](./VECTOR_API.md) - Vector database operations
- [GraphQL](./GRAPHQL.md) - GraphQL queries

