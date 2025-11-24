<?php

namespace BosBase\Services;

use BosBase\Utils;

class FileService extends BaseService
{
    public function getUrl(
        array $record,
        string $filename,
        ?string $thumb = null,
        ?string $token = null,
        ?bool $download = null,
        ?array $query = null
    ): string {
        $recordId = $record['id'] ?? '';
        if (!$recordId || !$filename) {
            return '';
        }

        $collection = $record['collectionId'] ?? ($record['collectionName'] ?? '');

        $params = $query ? $query : [];
        if ($thumb !== null) {
            $params['thumb'] = $params['thumb'] ?? $thumb;
        }
        if ($token !== null) {
            $params['token'] = $params['token'] ?? $token;
        }
        if ($download) {
            $params['download'] = '';
        }

        $path = '/api/files/' .
            Utils::encodePathSegment($collection) . '/' .
            Utils::encodePathSegment($recordId) . '/' .
            Utils::encodePathSegment($filename);

        return $this->client->buildUrl($path, $params);
    }

    public function getToken(
        ?array $body = null,
        ?array $query = null,
        ?array $headers = null
    ): string {
        $data = $this->client->send('/api/files/token', [
            'method' => 'POST',
            'body' => $body,
            'query' => $query,
            'headers' => $headers,
        ]);

        return is_array($data) ? ($data['token'] ?? '') : '';
    }
}
