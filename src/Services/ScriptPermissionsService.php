<?php

namespace BosBase\Services;

use BosBase\Utils;

class ScriptPermissionsService extends BaseService
{
    private string $basePath = '/api/script-permissions';

    public function create(
        string $scriptName,
        string $content,
        ?string $scriptId = null,
        ?array $body = null,
        ?array $query = null,
        ?array $headers = null
    ): array {
        $this->requireSuperuser();

        $normalizedName = $this->normalizeName($scriptName);
        $payload = $body ? $body : [];
        $payload['script_name'] = $payload['script_name'] ?? $normalizedName;
        $payload['content'] = $payload['content'] ?? trim($content);
        if ($scriptId !== null) {
            $payload['script_id'] = $payload['script_id'] ?? trim($scriptId);
        }

        if (($payload['content'] ?? '') === '') {
            throw new \InvalidArgumentException('content is required');
        }

        return $this->client->send($this->basePath, [
            'method' => 'POST',
            'body' => $payload,
            'query' => $query,
            'headers' => $headers,
        ]);
    }

    public function get(
        string $scriptName,
        ?array $query = null,
        ?array $headers = null
    ): array {
        $this->requireSuperuser();
        $normalized = $this->normalizeName($scriptName);

        return $this->client->send($this->basePath . '/' . Utils::encodePathSegment($normalized), [
            'method' => 'GET',
            'query' => $query,
            'headers' => $headers,
        ]);
    }

    public function update(
        string $scriptName,
        ?string $content = null,
        ?string $scriptId = null,
        ?string $newScriptName = null,
        ?array $body = null,
        ?array $query = null,
        ?array $headers = null
    ): array {
        $this->requireSuperuser();
        $normalized = $this->normalizeName($scriptName);

        $payload = $body ? $body : [];
        if ($content !== null) {
            $payload['content'] = $payload['content'] ?? trim($content);
        }
        if ($scriptId !== null) {
            $payload['script_id'] = $payload['script_id'] ?? trim($scriptId);
        }
        if ($newScriptName !== null) {
            $payload['script_name'] = $payload['script_name'] ?? $this->normalizeName($newScriptName);
        }

        return $this->client->send($this->basePath . '/' . Utils::encodePathSegment($normalized), [
            'method' => 'PATCH',
            'body' => $payload,
            'query' => $query,
            'headers' => $headers,
        ]);
    }

    public function delete(
        string $scriptName,
        ?array $query = null,
        ?array $headers = null
    ): bool {
        $this->requireSuperuser();
        $normalized = $this->normalizeName($scriptName);

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
            throw new \InvalidArgumentException('scriptName is required');
        }
        return $trimmed;
    }
}
