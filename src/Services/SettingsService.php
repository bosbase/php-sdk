<?php

namespace BosBase\Services;

class SettingsService extends BaseService
{
    public function getAll(?array $query = null, ?array $headers = null): array
    {
        return $this->client->send('/api/settings', [
            'query' => $query,
            'headers' => $headers,
        ]);
    }

    public function update(?array $body = null, ?array $query = null, ?array $headers = null): array
    {
        return $this->client->send('/api/settings', [
            'method' => 'PATCH',
            'body' => $body,
            'query' => $query,
            'headers' => $headers,
        ]);
    }

    public function testS3(
        string $filesystem = 'storage',
        ?array $body = null,
        ?array $query = null,
        ?array $headers = null
    ): void {
        $payload = $body ? $body : [];
        $payload['filesystem'] = $payload['filesystem'] ?? $filesystem;

        $this->client->send('/api/settings/test/s3', [
            'method' => 'POST',
            'body' => $payload,
            'query' => $query,
            'headers' => $headers,
        ]);
    }

    public function testEmail(
        string $toEmail,
        string $template,
        ?string $collection = null,
        ?array $body = null,
        ?array $query = null,
        ?array $headers = null
    ): void {
        $payload = $body ? $body : [];
        $payload['email'] = $payload['email'] ?? $toEmail;
        $payload['template'] = $payload['template'] ?? $template;
        if ($collection) {
            $payload['collection'] = $payload['collection'] ?? $collection;
        }

        $this->client->send('/api/settings/test/email', [
            'method' => 'POST',
            'body' => $payload,
            'query' => $query,
            'headers' => $headers,
        ]);
    }

    public function generateAppleClientSecret(
        string $clientId,
        string $teamId,
        string $keyId,
        string $privateKey,
        int $duration,
        ?array $body = null,
        ?array $query = null,
        ?array $headers = null
    ): array {
        $payload = $body ? $body : [];
        $payload['clientId'] = $payload['clientId'] ?? $clientId;
        $payload['teamId'] = $payload['teamId'] ?? $teamId;
        $payload['keyId'] = $payload['keyId'] ?? $keyId;
        $payload['privateKey'] = $payload['privateKey'] ?? $privateKey;
        $payload['duration'] = $payload['duration'] ?? $duration;

        return $this->client->send('/api/settings/apple/generate-client-secret', [
            'method' => 'POST',
            'body' => $payload,
            'query' => $query,
            'headers' => $headers,
        ]);
    }

    public function getCategory(string $category, ?array $query = null, ?array $headers = null): mixed
    {
        $settings = $this->getAll($query, $headers);
        return $settings[$category] ?? null;
    }

    public function updateMeta(
        ?string $appName = null,
        ?string $appUrl = null,
        ?string $senderName = null,
        ?string $senderAddress = null,
        ?bool $hideControls = null,
        ?array $query = null,
        ?array $headers = null
    ): array {
        $meta = array_filter(
            [
                'appName' => $appName,
                'appURL' => $appUrl,
                'senderName' => $senderName,
                'senderAddress' => $senderAddress,
                'hideControls' => $hideControls,
            ],
            fn($v) => $v !== null
        );

        return $this->update(['meta' => $meta], $query, $headers);
    }

    public function getApplicationSettings(?array $query = null, ?array $headers = null): array
    {
        $settings = $this->getAll($query, $headers);
        return [
            'meta' => $settings['meta'] ?? null,
            'trustedProxy' => $settings['trustedProxy'] ?? null,
            'rateLimits' => $settings['rateLimits'] ?? null,
            'batch' => $settings['batch'] ?? null,
        ];
    }

    public function updateApplicationSettings(
        ?array $meta = null,
        ?array $trustedProxy = null,
        ?array $rateLimits = null,
        ?array $batch = null,
        ?array $query = null,
        ?array $headers = null
    ): array {
        $payload = [];
        if ($meta !== null) {
            $payload['meta'] = $meta;
        }
        if ($trustedProxy !== null) {
            $payload['trustedProxy'] = $trustedProxy;
        }
        if ($rateLimits !== null) {
            $payload['rateLimits'] = $rateLimits;
        }
        if ($batch !== null) {
            $payload['batch'] = $batch;
        }

        return $this->update($payload, $query, $headers);
    }
}
