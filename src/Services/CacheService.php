<?php

namespace BosBase\Services;

use BosBase\Utils;

class CacheService extends BaseService
{
    public function listCaches(?array $query = null, ?array $headers = null): array
    {
        $data = $this->client->send('/api/cache', [
            'query' => $query,
            'headers' => $headers,
        ]);

        if (is_array($data) && isset($data['items'])) {
            return (array) $data['items'];
        }

        return $data ? (array) $data : [];
    }

    public function create(
        string $name,
        ?int $sizeBytes = null,
        ?int $defaultTtlSeconds = null,
        ?int $readTimeoutMs = null,
        ?array $body = null,
        ?array $query = null,
        ?array $headers = null
    ): array {
        $payload = $body ? $body : [];
        $payload['name'] = $name;
        if ($sizeBytes !== null) {
            $payload['sizeBytes'] = $sizeBytes;
        }
        if ($defaultTtlSeconds !== null) {
            $payload['defaultTTLSeconds'] = $defaultTtlSeconds;
        }
        if ($readTimeoutMs !== null) {
            $payload['readTimeoutMs'] = $readTimeoutMs;
        }

        return $this->client->send('/api/cache', [
            'method' => 'POST',
            'body' => $payload,
            'query' => $query,
            'headers' => $headers,
        ]);
    }

    public function update(
        string $name,
        ?array $body = null,
        ?array $query = null,
        ?array $headers = null
    ): array {
        return $this->client->send('/api/cache/' . Utils::encodePathSegment($name), [
            'method' => 'PATCH',
            'body' => $body,
            'query' => $query,
            'headers' => $headers,
        ]);
    }

    public function delete(
        string $name,
        ?array $query = null,
        ?array $headers = null
    ): void {
        $this->client->send('/api/cache/' . Utils::encodePathSegment($name), [
            'method' => 'DELETE',
            'query' => $query,
            'headers' => $headers,
        ]);
    }

    public function setEntry(
        string $cache,
        string $key,
        mixed $value,
        ?int $ttlSeconds = null,
        ?array $body = null,
        ?array $query = null,
        ?array $headers = null
    ): array {
        $payload = $body ? $body : [];
        $payload['value'] = $value;
        if ($ttlSeconds !== null) {
            $payload['ttlSeconds'] = $ttlSeconds;
        }

        return $this->client->send('/api/cache/' . Utils::encodePathSegment($cache) . '/entries/' . Utils::encodePathSegment($key), [
            'method' => 'PUT',
            'body' => $payload,
            'query' => $query,
            'headers' => $headers,
        ]);
    }

    public function getEntry(
        string $cache,
        string $key,
        ?array $query = null,
        ?array $headers = null
    ): array {
        return $this->client->send('/api/cache/' . Utils::encodePathSegment($cache) . '/entries/' . Utils::encodePathSegment($key), [
            'query' => $query,
            'headers' => $headers,
        ]);
    }

    public function renewEntry(
        string $cache,
        string $key,
        ?int $ttlSeconds = null,
        ?array $body = null,
        ?array $query = null,
        ?array $headers = null
    ): array {
        $payload = $body ? $body : [];
        if ($ttlSeconds !== null) {
            $payload['ttlSeconds'] = $ttlSeconds;
        }

        return $this->client->send('/api/cache/' . $cache . '/entries/' . $key, [
            'method' => 'PATCH',
            'body' => $payload,
            'query' => $query,
            'headers' => $headers,
        ]);
    }

    public function deleteEntry(
        string $cache,
        string $key,
        ?array $query = null,
        ?array $headers = null
    ): void {
        $this->client->send('/api/cache/' . Utils::encodePathSegment($cache) . '/entries/' . Utils::encodePathSegment($key), [
            'method' => 'DELETE',
            'query' => $query,
            'headers' => $headers,
        ]);
    }
}
