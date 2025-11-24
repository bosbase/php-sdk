<?php

namespace BosBase\Services;

use BosBase\Utils;

class CronService extends BaseService
{
    public function getFullList(?array $query = null, ?array $headers = null): array
    {
        $data = $this->client->send('/api/crons', [
            'query' => $query,
            'headers' => $headers,
        ]);

        return $data ? (array) $data : [];
    }

    public function run(
        string $jobId,
        ?array $body = null,
        ?array $query = null,
        ?array $headers = null
    ): void {
        $this->client->send('/api/crons/' . Utils::encodePathSegment($jobId), [
            'method' => 'POST',
            'body' => $body,
            'query' => $query,
            'headers' => $headers,
        ]);
    }
}
