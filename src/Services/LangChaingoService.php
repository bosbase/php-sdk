<?php

namespace BosBase\Services;

use BosBase\Utils;

class LangChaingoService extends BaseService
{
    private string $basePath = '/api/langchaingo';

    public function completions(
        array $payload,
        ?array $query = null,
        ?array $headers = null
    ): array {
        return $this->client->send($this->basePath . '/completions', [
            'method' => 'POST',
            'body' => Utils::toSerializable($payload),
            'query' => $query,
            'headers' => $headers,
        ]);
    }

    public function rag(
        array $payload,
        ?array $query = null,
        ?array $headers = null
    ): array {
        return $this->client->send($this->basePath . '/rag', [
            'method' => 'POST',
            'body' => Utils::toSerializable($payload),
            'query' => $query,
            'headers' => $headers,
        ]);
    }

    public function queryDocuments(
        array $payload,
        ?array $query = null,
        ?array $headers = null
    ): array {
        return $this->client->send($this->basePath . '/documents/query', [
            'method' => 'POST',
            'body' => Utils::toSerializable($payload),
            'query' => $query,
            'headers' => $headers,
        ]);
    }

    public function sql(
        array $payload,
        ?array $query = null,
        ?array $headers = null
    ): array {
        return $this->client->send($this->basePath . '/sql', [
            'method' => 'POST',
            'body' => Utils::toSerializable($payload),
            'query' => $query,
            'headers' => $headers,
        ]);
    }
}
