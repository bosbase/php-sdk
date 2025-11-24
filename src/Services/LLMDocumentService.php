<?php

namespace BosBase\Services;

use BosBase\Utils;

class LLMDocumentService extends BaseService
{
    private string $basePath = '/api/llm-documents';

    private function collectionPath(string $collection): string
    {
        return $this->basePath . '/' . Utils::encodePathSegment($collection);
    }

    public function listCollections(?array $query = null, ?array $headers = null): array
    {
        $data = $this->client->send($this->basePath . '/collections', [
            'query' => $query,
            'headers' => $headers,
        ]);

        return $data ? (array) $data : [];
    }

    public function createCollection(
        string $name,
        ?array $metadata = null,
        ?array $query = null,
        ?array $headers = null
    ): void {
        $this->client->send($this->basePath . '/collections/' . Utils::encodePathSegment($name), [
            'method' => 'POST',
            'body' => ['metadata' => $metadata],
            'query' => $query,
            'headers' => $headers,
        ]);
    }

    public function deleteCollection(
        string $name,
        ?array $query = null,
        ?array $headers = null
    ): void {
        $this->client->send($this->basePath . '/collections/' . Utils::encodePathSegment($name), [
            'method' => 'DELETE',
            'query' => $query,
            'headers' => $headers,
        ]);
    }

    public function insert(
        string $collection,
        array $document,
        ?array $query = null,
        ?array $headers = null
    ): array {
        return $this->client->send($this->collectionPath($collection), [
            'method' => 'POST',
            'body' => Utils::toSerializable($document),
            'query' => $query,
            'headers' => $headers,
        ]);
    }

    public function get(
        string $collection,
        string $documentId,
        ?array $query = null,
        ?array $headers = null
    ): array {
        return $this->client->send($this->collectionPath($collection) . '/' . Utils::encodePathSegment($documentId), [
            'query' => $query,
            'headers' => $headers,
        ]);
    }

    public function update(
        string $collection,
        string $documentId,
        array $document,
        ?array $query = null,
        ?array $headers = null
    ): array {
        return $this->client->send($this->collectionPath($collection) . '/' . Utils::encodePathSegment($documentId), [
            'method' => 'PATCH',
            'body' => Utils::toSerializable($document),
            'query' => $query,
            'headers' => $headers,
        ]);
    }

    public function delete(
        string $collection,
        string $documentId,
        ?array $query = null,
        ?array $headers = null
    ): void {
        $this->client->send($this->collectionPath($collection) . '/' . Utils::encodePathSegment($documentId), [
            'method' => 'DELETE',
            'query' => $query,
            'headers' => $headers,
        ]);
    }

    public function listDocuments(
        string $collection,
        ?int $page = null,
        ?int $perPage = null,
        ?array $query = null,
        ?array $headers = null
    ): array {
        $params = $query ? $query : [];
        if ($page !== null) {
            $params['page'] = $page;
        }
        if ($perPage !== null) {
            $params['perPage'] = $perPage;
        }

        return $this->client->send($this->collectionPath($collection), [
            'query' => $params,
            'headers' => $headers,
        ]);
    }

    public function queryDocuments(
        string $collection,
        array $options,
        ?array $query = null,
        ?array $headers = null
    ): array {
        return $this->client->send($this->collectionPath($collection) . '/documents/query', [
            'method' => 'POST',
            'body' => Utils::toSerializable($options),
            'query' => $query,
            'headers' => $headers,
        ]);
    }
}
