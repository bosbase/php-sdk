<?php

namespace BosBase\Services;

use BosBase\Utils;

class VectorService extends BaseService
{
    private string $basePath = '/api/vectors';

    private function collectionPath(?string $collection): string
    {
        if ($collection) {
            return $this->basePath . '/' . Utils::encodePathSegment($collection);
        }
        return $this->basePath;
    }

    public function insert(
        array $document,
        ?string $collection = null,
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

    public function batchInsert(
        array $options,
        ?string $collection = null,
        ?array $query = null,
        ?array $headers = null
    ): array {
        return $this->client->send($this->collectionPath($collection) . '/documents/batch', [
            'method' => 'POST',
            'body' => Utils::toSerializable($options),
            'query' => $query,
            'headers' => $headers,
        ]);
    }

    public function update(
        string $documentId,
        array $document,
        ?string $collection = null,
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
        string $documentId,
        ?string $collection = null,
        ?array $body = null,
        ?array $query = null,
        ?array $headers = null
    ): void {
        $this->client->send($this->collectionPath($collection) . '/' . Utils::encodePathSegment($documentId), [
            'method' => 'DELETE',
            'body' => $body,
            'query' => $query,
            'headers' => $headers,
        ]);
    }

    public function search(
        array $options,
        ?string $collection = null,
        ?array $query = null,
        ?array $headers = null
    ): array {
        return $this->client->send($this->collectionPath($collection) . '/documents/search', [
            'method' => 'POST',
            'body' => Utils::toSerializable($options),
            'query' => $query,
            'headers' => $headers,
        ]);
    }

    public function get(
        string $documentId,
        ?string $collection = null,
        ?array $query = null,
        ?array $headers = null
    ): array {
        return $this->client->send($this->collectionPath($collection) . '/' . Utils::encodePathSegment($documentId), [
            'query' => $query,
            'headers' => $headers,
        ]);
    }

    public function listDocuments(
        ?string $collection = null,
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

    public function createCollection(
        string $name,
        array $config,
        ?array $query = null,
        ?array $headers = null
    ): void {
        $this->client->send($this->basePath . '/collections/' . Utils::encodePathSegment($name), [
            'method' => 'POST',
            'body' => Utils::toSerializable($config),
            'query' => $query,
            'headers' => $headers,
        ]);
    }

    public function updateCollection(
        string $name,
        array $config,
        ?array $query = null,
        ?array $headers = null
    ): void {
        $this->client->send($this->basePath . '/collections/' . Utils::encodePathSegment($name), [
            'method' => 'PATCH',
            'body' => Utils::toSerializable($config),
            'query' => $query,
            'headers' => $headers,
        ]);
    }

    public function deleteCollection(
        string $name,
        ?array $body = null,
        ?array $query = null,
        ?array $headers = null
    ): void {
        $this->client->send($this->basePath . '/collections/' . Utils::encodePathSegment($name), [
            'method' => 'DELETE',
            'body' => $body,
            'query' => $query,
            'headers' => $headers,
        ]);
    }

    public function listCollections(?array $query = null, ?array $headers = null): array
    {
        $data = $this->client->send($this->basePath . '/collections', [
            'query' => $query,
            'headers' => $headers,
        ]);

        return $data ? (array) $data : [];
    }
}
