<?php

namespace BosBase\Services;

class GraphQLService extends BaseService
{
    public function query(
        string $query,
        ?array $variables = null,
        ?string $operationName = null,
        ?array $queryParams = null,
        ?array $headers = null,
        ?float $timeout = null
    ): array {
        $payload = [
            'query' => $query,
            'variables' => $variables ? $variables : [],
        ];
        if ($operationName !== null) {
            $payload['operationName'] = $operationName;
        }

        return $this->client->send('/api/graphql', [
            'method' => 'POST',
            'query' => $queryParams,
            'headers' => $headers,
            'body' => $payload,
            'timeout' => $timeout,
        ]);
    }
}
