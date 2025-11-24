<?php

namespace BosBase\Services;

class HealthService extends BaseService
{
    public function check(?array $query = null, ?array $headers = null): array
    {
        return $this->client->send('/api/health', [
            'query' => $query,
            'headers' => $headers,
        ]);
    }
}
