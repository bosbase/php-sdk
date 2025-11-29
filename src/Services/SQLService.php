<?php

namespace BosBase\Services;

/**
 * Superuser-only helpers for executing SQL statements against the backend.
 */
class SQLService extends BaseService
{
    /**
     * Execute a SQL statement and return the raw result.
     *
     * @throws \InvalidArgumentException when the query is empty.
     */
    public function execute(
        string $query,
        ?array $queryParams = null,
        ?array $headers = null,
        ?float $timeout = null
    ): array {
        $trimmed = trim($query ?? '');
        if ($trimmed === '') {
            throw new \InvalidArgumentException('query must be non-empty');
        }

        return $this->client->send('/api/sql/execute', [
            'method' => 'POST',
            'body' => ['query' => $trimmed],
            'query' => $queryParams,
            'headers' => $headers,
            'timeout' => $timeout,
        ]);
    }
}
