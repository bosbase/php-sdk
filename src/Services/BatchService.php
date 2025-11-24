<?php

namespace BosBase\Services;

use BosBase\Utils;

class BatchService extends BaseService
{
    /** @var array<int, array<string, mixed>> */
    private array $requests = [];
    /** @var array<string, SubBatchService> */
    private array $collections = [];

    public function collection(string $collectionIdOrName): SubBatchService
    {
        if (!isset($this->collections[$collectionIdOrName])) {
            $this->collections[$collectionIdOrName] = new SubBatchService(
                $this,
                $collectionIdOrName
            );
        }

        return $this->collections[$collectionIdOrName];
    }

    /**
     * @param array<string, string>|null $headers
     * @param array<string, mixed>|null $body
     * @param array<int, array<string, mixed>>|null $files
     */
    public function queueRequest(
        string $method,
        string $url,
        ?array $headers = null,
        ?array $body = null,
        ?array $files = null
    ): void {
        $this->requests[] = [
            'method' => strtoupper($method),
            'url' => $url,
            'headers' => $headers ? $headers : [],
            'body' => $body !== null ? Utils::toSerializable($body) : [],
            'files' => $files ? $files : [],
        ];
    }

    public function send(
        ?array $body = null,
        ?array $query = null,
        ?array $headers = null
    ): array {
        $requestsPayload = [];
        $attachments = [];

        foreach ($this->requests as $index => $req) {
            $requestsPayload[] = [
                'method' => $req['method'],
                'url' => $req['url'],
                'headers' => $req['headers'],
                'body' => $req['body'],
            ];

            foreach ($req['files'] as $filePair) {
                [$field, $file] = $filePair;
                $attachments["requests.$index.$field"] = $file;
            }
        }

        $payload = $body ? $body : [];
        $payload['requests'] = $requestsPayload;

        $response = $this->client->send('/api/batch', [
            'method' => 'POST',
            'body' => $payload,
            'query' => $query,
            'headers' => $headers,
            'files' => $attachments ?: null,
        ]);

        $this->requests = [];

        return $response ?? [];
    }
}

class SubBatchService
{
    private BatchService $batch;
    private string $collection;

    public function __construct(BatchService $batch, string $collectionIdOrName)
    {
        $this->batch = $batch;
        $this->collection = $collectionIdOrName;
    }

    private function collectionUrl(): string
    {
        $encoded = Utils::encodePathSegment($this->collection);
        return '/api/collections/' . $encoded . '/records';
    }

    public function create(
        ?array $body = null,
        ?array $query = null,
        ?array $files = null,
        ?array $headers = null,
        ?string $expand = null,
        ?string $fields = null
    ): void {
        $params = $query ? $query : [];
        if ($expand !== null) {
            $params['expand'] = $params['expand'] ?? $expand;
        }
        if ($fields !== null) {
            $params['fields'] = $params['fields'] ?? $fields;
        }
        $url = Utils::buildRelativeUrl($this->collectionUrl(), $params);
        $this->batch->queueRequest('POST', $url, $headers, $body, $this->normalizeFiles($files));
    }

    public function upsert(
        ?array $body = null,
        ?array $query = null,
        ?array $files = null,
        ?array $headers = null,
        ?string $expand = null,
        ?string $fields = null
    ): void {
        $params = $query ? $query : [];
        if ($expand !== null) {
            $params['expand'] = $params['expand'] ?? $expand;
        }
        if ($fields !== null) {
            $params['fields'] = $params['fields'] ?? $fields;
        }
        $url = Utils::buildRelativeUrl($this->collectionUrl(), $params);
        $this->batch->queueRequest('PUT', $url, $headers, $body, $this->normalizeFiles($files));
    }

    public function update(
        string $recordId,
        ?array $body = null,
        ?array $query = null,
        ?array $files = null,
        ?array $headers = null,
        ?string $expand = null,
        ?string $fields = null
    ): void {
        $params = $query ? $query : [];
        if ($expand !== null) {
            $params['expand'] = $params['expand'] ?? $expand;
        }
        if ($fields !== null) {
            $params['fields'] = $params['fields'] ?? $fields;
        }
        $encodedId = Utils::encodePathSegment($recordId);
        $url = Utils::buildRelativeUrl($this->collectionUrl() . '/' . $encodedId, $params);
        $this->batch->queueRequest('PATCH', $url, $headers, $body, $this->normalizeFiles($files));
    }

    public function delete(
        string $recordId,
        ?array $body = null,
        ?array $query = null,
        ?array $headers = null
    ): void {
        $encodedId = Utils::encodePathSegment($recordId);
        $url = Utils::buildRelativeUrl($this->collectionUrl() . '/' . $encodedId, $query ?? []);
        $this->batch->queueRequest('DELETE', $url, $headers, $body);
    }

    private function normalizeFiles(?array $files): array
    {
        if (!$files) {
            return [];
        }

        $result = [];
        foreach ($files as $key => $file) {
            $result[] = [$key, $file];
        }
        return $result;
    }
}
