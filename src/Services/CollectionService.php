<?php

namespace BosBase\Services;

use BosBase\Utils;

class CollectionService extends BaseCrudService
{
    protected function getBaseCrudPath(): string
    {
        return '/api/collections';
    }

    public function deleteCollection(
        string $collectionIdOrName,
        ?array $body = null,
        ?array $query = null,
        ?array $headers = null
    ): void {
        $this->delete($collectionIdOrName, $body, $query, $headers);
    }

    public function truncate(
        string $collectionIdOrName,
        ?array $body = null,
        ?array $query = null,
        ?array $headers = null
    ): void {
        $encoded = Utils::encodePathSegment($collectionIdOrName);
        $this->client->send($this->getBaseCrudPath() . '/' . $encoded . '/truncate', [
            'method' => 'DELETE',
            'body' => $body,
            'query' => $query,
            'headers' => $headers,
        ]);
    }

    public function importCollections(
        mixed $collections,
        bool $deleteMissing = false,
        ?array $body = null,
        ?array $query = null,
        ?array $headers = null
    ): void {
        $payload = $body ? $body : [];
        $payload['collections'] = $collections;
        $payload['deleteMissing'] = $deleteMissing;

        $this->client->send($this->getBaseCrudPath() . '/import', [
            'method' => 'PUT',
            'body' => $payload,
            'query' => $query,
            'headers' => $headers,
        ]);
    }

    public function getScaffolds(
        ?array $body = null,
        ?array $query = null,
        ?array $headers = null
    ): array {
        $payload = $body ? $body : [];
        return $this->client->send($this->getBaseCrudPath() . '/meta/scaffolds', [
            'body' => $payload,
            'query' => $query,
            'headers' => $headers,
        ]);
    }

    public function createFromScaffold(
        string $scaffoldType,
        string $name,
        ?array $overrides = null,
        ?array $body = null,
        ?array $query = null,
        ?array $headers = null
    ): array {
        $scaffolds = $this->getScaffolds(query: $query, headers: $headers);
        $scaffold = $scaffolds[$scaffoldType] ?? null;
        if (!$scaffold) {
            throw new \InvalidArgumentException("Scaffold for type '{$scaffoldType}' not found.");
        }

        $data = $scaffold;
        $data['name'] = $name;
        if ($overrides) {
            $data = array_merge($data, $overrides);
        }
        if ($body) {
            $data = array_merge($data, $body);
        }

        return $this->create($data, $query, null, $headers);
    }

    public function createBase(
        string $name,
        ?array $overrides = null,
        ?array $body = null,
        ?array $query = null,
        ?array $headers = null
    ): array {
        return $this->createFromScaffold('base', $name, $overrides, $body, $query, $headers);
    }

    public function createAuth(
        string $name,
        ?array $overrides = null,
        ?array $body = null,
        ?array $query = null,
        ?array $headers = null
    ): array {
        return $this->createFromScaffold('auth', $name, $overrides, $body, $query, $headers);
    }

    public function createView(
        string $name,
        ?string $viewQuery = null,
        ?array $overrides = null,
        ?array $body = null,
        ?array $query = null,
        ?array $headers = null
    ): array {
        $scaffoldOverrides = $overrides ? $overrides : [];
        if ($viewQuery !== null) {
            $scaffoldOverrides['viewQuery'] = $viewQuery;
        }

        return $this->createFromScaffold('view', $name, $scaffoldOverrides, $body, $query, $headers);
    }

    public function addIndex(
        string $collectionIdOrName,
        array $columns,
        bool $unique = false,
        ?string $indexName = null,
        ?array $query = null,
        ?array $headers = null
    ): array {
        if (!$columns) {
            throw new \InvalidArgumentException('At least one column must be specified.');
        }

        $collection = $this->getOne($collectionIdOrName, query: $query, headers: $headers);
        $fields = $collection['fields'] ?? [];
        $fieldNames = [];
        foreach ($fields as $field) {
            if (is_array($field) && isset($field['name'])) {
                $fieldNames[] = $field['name'];
            }
        }

        foreach ($columns as $column) {
            if ($column !== 'id' && !in_array($column, $fieldNames, true)) {
                throw new \InvalidArgumentException("Field \"{$column}\" does not exist in the collection.");
            }
        }

        $collectionName = $collection['name'] ?? $collectionIdOrName;
        $idxName = $indexName ?? ('idx_' . $collectionName . '_' . implode('_', $columns));
        $columnsStr = implode(', ', array_map(fn($col) => '`' . $col . '`', $columns));
        $indexSql = $unique
            ? "CREATE UNIQUE INDEX `{$idxName}` ON `{$collectionName}` ({$columnsStr})"
            : "CREATE INDEX `{$idxName}` ON `{$collectionName}` ({$columnsStr})";

        $indexes = $collection['indexes'] ?? [];
        if (in_array($indexSql, $indexes, true)) {
            throw new \InvalidArgumentException('Index already exists.');
        }

        $indexes[] = $indexSql;
        $collection['indexes'] = $indexes;
        return $this->update($collectionIdOrName, $collection, $query, null, $headers);
    }

    public function removeIndex(
        string $collectionIdOrName,
        array $columns,
        ?array $query = null,
        ?array $headers = null
    ): array {
        if (!$columns) {
            throw new \InvalidArgumentException('At least one column must be specified.');
        }

        $collection = $this->getOne($collectionIdOrName, query: $query, headers: $headers);
        $indexes = $collection['indexes'] ?? [];
        $initialLength = count($indexes);

        $indexes = array_values(array_filter($indexes, function ($idx) use ($columns) {
            foreach ($columns as $column) {
                $backticked = '`' . $column . '`';
                if (
                    str_contains($idx, $backticked) ||
                    str_contains($idx, '(' . $column . ')') ||
                    str_contains($idx, '(' . $column . ',') ||
                    str_contains($idx, ', ' . $column . ')')
                ) {
                    continue;
                }
                return true;
            }
            return false;
        }));

        if (count($indexes) === $initialLength) {
            throw new \InvalidArgumentException('Index not found.');
        }

        $collection['indexes'] = $indexes;
        return $this->update($collectionIdOrName, $collection, $query, null, $headers);
    }

    public function getIndexes(
        string $collectionIdOrName,
        ?array $query = null,
        ?array $headers = null
    ): array {
        $collection = $this->getOne($collectionIdOrName, query: $query, headers: $headers);
        $existing = $collection['indexes'] ?? [];
        return array_values(array_filter($existing, 'is_string'));
    }

    public function getSchema(
        string $collectionIdOrName,
        ?array $query = null,
        ?array $headers = null
    ): array {
        $encoded = Utils::encodePathSegment($collectionIdOrName);
        return $this->client->send($this->getBaseCrudPath() . '/' . $encoded . '/schema', [
            'query' => $query,
            'headers' => $headers,
        ]);
    }

    public function getAllSchemas(
        ?array $query = null,
        ?array $headers = null
    ): array {
        return $this->client->send($this->getBaseCrudPath() . '/schemas', [
            'query' => $query,
            'headers' => $headers,
        ]);
    }
}
