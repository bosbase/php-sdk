<?php

namespace BosBase\Services;

use BosBase\Exceptions\ClientResponseError;

class LogService extends BaseService
{
    public function getList(
        int $page = 1,
        int $perPage = 30,
        ?string $filter = null,
        ?string $sort = null,
        ?array $query = null,
        ?array $headers = null
    ): array {
        $params = $query ? $query : [];
        $params['page'] = $params['page'] ?? $page;
        $params['perPage'] = $params['perPage'] ?? $perPage;
        if ($filter !== null) {
            $params['filter'] = $params['filter'] ?? $filter;
        }
        if ($sort !== null) {
            $params['sort'] = $params['sort'] ?? $sort;
        }

        return $this->client->send('/api/logs', [
            'query' => $params,
            'headers' => $headers,
        ]);
    }

    public function getOne(
        string $logId,
        ?array $query = null,
        ?array $headers = null
    ): array {
        if ($logId === '') {
            throw new ClientResponseError(
                $this->client->buildUrl('/api/logs/'),
                404,
                [
                    'code' => 404,
                    'message' => 'Missing required log id.',
                    'data' => [],
                ]
            );
        }

        return $this->client->send('/api/logs/' . $logId, [
            'query' => $query,
            'headers' => $headers,
        ]);
    }

    public function getStats(?array $query = null, ?array $headers = null): array
    {
        $data = $this->client->send('/api/logs/stats', [
            'query' => $query,
            'headers' => $headers,
        ]);

        return $data ? (array) $data : [];
    }
}
