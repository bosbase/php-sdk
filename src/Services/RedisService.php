<?php

namespace BosBase\Services;

use BosBase\Utils;

class RedisService extends BaseService
{
    public function listKeys(
        ?string $cursor = null,
        ?string $pattern = null,
        ?int $count = null,
        ?array $query = null,
        ?array $headers = null
    ): array {
        $params = $query ? $query : [];
        if ($cursor !== null) {
            $params['cursor'] = $cursor;
        }
        if ($pattern !== null) {
            $params['pattern'] = $pattern;
        }
        if ($count !== null) {
            $params['count'] = $count;
        }

        return $this->client->send('/api/redis/keys', [
            'method' => 'GET',
            'query' => $params,
            'headers' => $headers,
        ]);
    }

    public function createKey(
        string $key,
        mixed $value,
        ?int $ttlSeconds = null,
        ?array $body = null,
        ?array $query = null,
        ?array $headers = null
    ): array {
        if ($key === '') {
            throw new \InvalidArgumentException('key must be provided.');
        }

        $payload = $body ? $body : [];
        $payload['key'] = $payload['key'] ?? $key;
        $payload['value'] = $payload['value'] ?? $value;
        if ($ttlSeconds !== null) {
            $payload['ttlSeconds'] = $payload['ttlSeconds'] ?? $ttlSeconds;
        }

        return $this->client->send('/api/redis/keys', [
            'method' => 'POST',
            'body' => $payload,
            'query' => $query,
            'headers' => $headers,
        ]);
    }

    public function getKey(
        string $key,
        ?array $query = null,
        ?array $headers = null
    ): array {
        if ($key === '') {
            throw new \InvalidArgumentException('key must be provided.');
        }

        return $this->client->send('/api/redis/keys/' . Utils::encodePathSegment($key), [
            'method' => 'GET',
            'query' => $query,
            'headers' => $headers,
        ]);
    }

    public function updateKey(
        string $key,
        mixed $value,
        ?int $ttlSeconds = null,
        ?array $body = null,
        ?array $query = null,
        ?array $headers = null
    ): array {
        if ($key === '') {
            throw new \InvalidArgumentException('key must be provided.');
        }

        $payload = $body ? $body : [];
        $payload['value'] = $payload['value'] ?? $value;
        if ($ttlSeconds !== null) {
            $payload['ttlSeconds'] = $payload['ttlSeconds'] ?? $ttlSeconds;
        }

        return $this->client->send('/api/redis/keys/' . Utils::encodePathSegment($key), [
            'method' => 'PUT',
            'body' => $payload,
            'query' => $query,
            'headers' => $headers,
        ]);
    }

    public function deleteKey(
        string $key,
        ?array $query = null,
        ?array $headers = null
    ): void {
        if ($key === '') {
            throw new \InvalidArgumentException('key must be provided.');
        }

        $this->client->send('/api/redis/keys/' . Utils::encodePathSegment($key), [
            'method' => 'DELETE',
            'query' => $query,
            'headers' => $headers,
        ]);
    }
}
