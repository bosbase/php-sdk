<?php

namespace BosBase\Services;

use BosBase\Utils;

class BackupService extends BaseService
{
    public function getFullList(?array $query = null, ?array $headers = null): array
    {
        $data = $this->client->send('/api/backups', [
            'query' => $query,
            'headers' => $headers,
        ]);

        return $data ? (array) $data : [];
    }

    public function create(
        string $name,
        ?array $body = null,
        ?array $query = null,
        ?array $headers = null
    ): void {
        $payload = $body ? $body : [];
        $payload['name'] = $payload['name'] ?? $name;

        $this->client->send('/api/backups', [
            'method' => 'POST',
            'body' => $payload,
            'query' => $query,
            'headers' => $headers,
        ]);
    }

    public function upload(
        array $files,
        ?array $body = null,
        ?array $query = null,
        ?array $headers = null
    ): void {
        $this->client->send('/api/backups/upload', [
            'method' => 'POST',
            'body' => $body,
            'query' => $query,
            'headers' => $headers,
            'files' => $files,
        ]);
    }

    public function delete(
        string $key,
        ?array $body = null,
        ?array $query = null,
        ?array $headers = null
    ): void {
        $this->client->send('/api/backups/' . Utils::encodePathSegment($key), [
            'method' => 'DELETE',
            'body' => $body,
            'query' => $query,
            'headers' => $headers,
        ]);
    }

    public function restore(
        string $key,
        ?array $body = null,
        ?array $query = null,
        ?array $headers = null
    ): void {
        $this->client->send('/api/backups/' . Utils::encodePathSegment($key) . '/restore', [
            'method' => 'POST',
            'body' => $body,
            'query' => $query,
            'headers' => $headers,
        ]);
    }

    public function getDownloadUrl(string $token, string $key, ?array $query = null): string
    {
        $params = $query ? $query : [];
        $params['token'] = $params['token'] ?? $token;
        return $this->client->buildUrl('/api/backups/' . Utils::encodePathSegment($key), $params);
    }
}
