<?php

namespace BosBase\Services;

use BosBase\Utils;

class ScriptService extends BaseService
{
    private string $basePath = '/api/scripts';

    public function create(
        string $name,
        string $content,
        ?string $description = null,
        ?array $body = null,
        ?array $query = null,
        ?array $headers = null
    ): array {
        $this->requireSuperuser();

        $trimmedName = trim($name);
        $trimmedContent = trim($content);
        if ($trimmedName === '' || $trimmedContent === '') {
            throw new \InvalidArgumentException('script name and content are required');
        }

        $payload = $body ? $body : [];
        $payload['name'] = $payload['name'] ?? $trimmedName;
        $payload['content'] = $payload['content'] ?? $trimmedContent;
        if ($description !== null) {
            $payload['description'] = $payload['description'] ?? $description;
        }

        return $this->client->send($this->basePath, [
            'method' => 'POST',
            'body' => $payload,
            'query' => $query,
            'headers' => $headers,
        ]);
    }

    public function command(
        string $command,
        ?array $body = null,
        ?array $query = null,
        ?array $headers = null
    ): array {
        $this->requireSuperuser();
        $trimmed = trim($command);
        if ($trimmed === '') {
            throw new \InvalidArgumentException('command is required');
        }

        $payload = $body ? $body : [];
        $payload['command'] = $payload['command'] ?? $trimmed;

        return $this->client->send($this->basePath . '/command', [
            'method' => 'POST',
            'body' => $payload,
            'query' => $query,
            'headers' => $headers,
        ]);
    }

    public function get(
        string $name,
        ?array $query = null,
        ?array $headers = null
    ): array {
        $this->requireSuperuser();
        $normalized = $this->normalizeName($name);

        return $this->client->send($this->basePath . '/' . Utils::encodePathSegment($normalized), [
            'method' => 'GET',
            'query' => $query,
            'headers' => $headers,
        ]);
    }

    public function list(
        ?array $query = null,
        ?array $headers = null
    ): array {
        $this->requireSuperuser();

        $data = $this->client->send($this->basePath, [
            'method' => 'GET',
            'query' => $query,
            'headers' => $headers,
        ]);

        if (is_array($data) && isset($data['items'])) {
            return (array) $data['items'];
        }

        return $data ? (array) $data : [];
    }

    public function update(
        string $name,
        ?string $content = null,
        ?string $description = null,
        ?array $body = null,
        ?array $query = null,
        ?array $headers = null
    ): array {
        $this->requireSuperuser();
        if ($content === null && $description === null) {
            throw new \InvalidArgumentException('content or description must be provided');
        }
        $normalized = $this->normalizeName($name);

        $payload = $body ? $body : [];
        if ($content !== null) {
            $payload['content'] = $payload['content'] ?? $content;
        }
        if ($description !== null) {
            $payload['description'] = $payload['description'] ?? $description;
        }

        return $this->client->send($this->basePath . '/' . Utils::encodePathSegment($normalized), [
            'method' => 'PATCH',
            'body' => $payload,
            'query' => $query,
            'headers' => $headers,
        ]);
    }

    public function execute(
        string $name,
        ?array $query = null,
        ?array $headers = null
    ): array {
        $normalized = $this->normalizeName($name);
        return $this->client->send($this->basePath . '/' . Utils::encodePathSegment($normalized) . '/execute', [
            'method' => 'POST',
            'query' => $query,
            'headers' => $headers,
        ]);
    }

    public function delete(
        string $name,
        ?array $query = null,
        ?array $headers = null
    ): bool {
        $this->requireSuperuser();
        $normalized = $this->normalizeName($name);

        $this->client->send($this->basePath . '/' . Utils::encodePathSegment($normalized), [
            'method' => 'DELETE',
            'query' => $query,
            'headers' => $headers,
        ]);

        return true;
    }

    private function normalizeName(string $name): string
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            throw new \InvalidArgumentException('script name is required');
        }
        return $trimmed;
    }
}
