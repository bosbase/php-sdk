<?php

namespace BosBase\Services;

use BosBase\BosBase;
use BosBase\Exceptions\ClientResponseError;
use BosBase\Utils;

class BaseService
{
    protected BosBase $client;

    public function __construct(BosBase $client)
    {
        $this->client = $client;
    }

    protected function requireSuperuser(): void
    {
        if (!$this->client->authStore->isSuperuser()) {
            throw new \RuntimeException('Superuser authentication is required for this operation.');
        }
    }
}

abstract class BaseCrudService extends BaseService
{
    abstract protected function getBaseCrudPath(): string;

    public function getFullList(
        int $batch = 500,
        ?string $expand = null,
        ?string $filter = null,
        ?string $sort = null,
        ?string $fields = null,
        ?array $query = null,
        ?array $headers = null
    ): array {
        if ($batch <= 0) {
            throw new \InvalidArgumentException('batch must be > 0');
        }

        $page = 1;
        $result = [];

        while (true) {
            $data = $this->getList(
                $page,
                $batch,
                true,
                $expand,
                $filter,
                $sort,
                $fields,
                $query,
                $headers
            );
            $items = $data['items'] ?? [];
            $result = array_merge($result, $items);

            if (count($items) < ($data['perPage'] ?? $batch)) {
                break;
            }
            $page += 1;
        }

        return $result;
    }

    public function getList(
        int $page = 1,
        int $perPage = 30,
        bool $skipTotal = false,
        ?string $expand = null,
        ?string $filter = null,
        ?string $sort = null,
        ?string $fields = null,
        ?array $query = null,
        ?array $headers = null
    ): array {
        $params = $query ? $query : [];
        $params['page'] = $params['page'] ?? $page;
        $params['perPage'] = $params['perPage'] ?? $perPage;
        $params['skipTotal'] = $params['skipTotal'] ?? $skipTotal;
        if ($filter !== null) {
            $params['filter'] = $params['filter'] ?? $filter;
        }
        if ($sort !== null) {
            $params['sort'] = $params['sort'] ?? $sort;
        }
        if ($expand !== null) {
            $params['expand'] = $params['expand'] ?? $expand;
        }
        if ($fields !== null) {
            $params['fields'] = $params['fields'] ?? $fields;
        }

        return $this->client->send($this->getBaseCrudPath(), [
            'query' => $params,
            'headers' => $headers,
        ]);
    }

    public function getOne(
        string $recordId,
        ?string $expand = null,
        ?string $fields = null,
        ?array $query = null,
        ?array $headers = null
    ): array {
        if ($recordId === '') {
            throw new ClientResponseError(
                $this->client->buildUrl($this->getBaseCrudPath() . '/'),
                404,
                [
                    'code' => 404,
                    'message' => 'Missing required record id.',
                    'data' => [],
                ]
            );
        }

        $params = $query ? $query : [];
        if ($expand !== null) {
            $params['expand'] = $params['expand'] ?? $expand;
        }
        if ($fields !== null) {
            $params['fields'] = $params['fields'] ?? $fields;
        }

        $encoded = Utils::encodePathSegment($recordId);
        return $this->client->send($this->getBaseCrudPath() . '/' . $encoded, [
            'query' => $params,
            'headers' => $headers,
        ]);
    }

    public function getFirstListItem(
        string $filter,
        ?string $expand = null,
        ?string $fields = null,
        ?array $query = null,
        ?array $headers = null
    ): array {
        $data = $this->getList(
            1,
            1,
            true,
            $expand,
            $filter,
            null,
            $fields,
            $query,
            $headers
        );
        $items = $data['items'] ?? [];
        if (!$items) {
            throw new ClientResponseError(
                null,
                404,
                [
                    'code' => 404,
                    'message' => "The requested resource wasn't found.",
                    'data' => [],
                ]
            );
        }

        return $items[0];
    }

    public function create(
        ?array $body = null,
        ?array $query = null,
        ?array $files = null,
        ?array $headers = null,
        ?string $expand = null,
        ?string $fields = null
    ): array {
        $params = $query ? $query : [];
        if ($expand !== null) {
            $params['expand'] = $params['expand'] ?? $expand;
        }
        if ($fields !== null) {
            $params['fields'] = $params['fields'] ?? $fields;
        }

        return $this->client->send($this->getBaseCrudPath(), [
            'method' => 'POST',
            'body' => $body,
            'query' => $params,
            'headers' => $headers,
            'files' => $files,
        ]);
    }

    public function update(
        string $recordId,
        ?array $body = null,
        ?array $query = null,
        ?array $files = null,
        ?array $headers = null,
        ?string $expand = null,
        ?string $fields = null
    ): array {
        $params = $query ? $query : [];
        if ($expand !== null) {
            $params['expand'] = $params['expand'] ?? $expand;
        }
        if ($fields !== null) {
            $params['fields'] = $params['fields'] ?? $fields;
        }

        $encoded = Utils::encodePathSegment($recordId);
        return $this->client->send($this->getBaseCrudPath() . '/' . $encoded, [
            'method' => 'PATCH',
            'body' => $body,
            'query' => $params,
            'headers' => $headers,
            'files' => $files,
        ]);
    }

    public function delete(
        string $recordId,
        ?array $body = null,
        ?array $query = null,
        ?array $headers = null
    ): void {
        $encoded = Utils::encodePathSegment($recordId);
        $this->client->send($this->getBaseCrudPath() . '/' . $encoded, [
            'method' => 'DELETE',
            'body' => $body,
            'query' => $query,
            'headers' => $headers,
        ]);
    }
}
