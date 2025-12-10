<?php

namespace BosBase\Services;

use BosBase\BosBase;
use WebSocket\Client as WSClient;

class PluginSSEStream implements \IteratorAggregate
{
    /** @var resource|null */
    private $handle;

    public function __construct($handle)
    {
        $this->handle = $handle;
    }

    public function getIterator(): \Traversable
    {
        while ($this->handle && !feof($this->handle)) {
            $line = fgets($this->handle);
            if ($line === false) {
                break;
            }
            yield rtrim($line, "\r\n");
        }
    }

    public function close(): void
    {
        if ($this->handle) {
            fclose($this->handle);
        }
        $this->handle = null;
    }
}

class PluginService extends BaseService
{
    /** @var array<string, bool> */
    private array $httpMethods = [
        'GET' => true,
        'POST' => true,
        'PUT' => true,
        'PATCH' => true,
        'DELETE' => true,
        'HEAD' => true,
        'OPTIONS' => true,
    ];
    /** @var array<string, bool> */
    private array $sseMethods = ['SSE' => true];
    /** @var array<string, bool> */
    private array $wsMethods = ['WS' => true, 'WEBSOCKET' => true];

    /**
     * Forward a request to the plugin proxy endpoint.
     *
     * @param array<string, mixed> $options
     */
    public function request(
        string $method,
        string $path,
        array $options = []
    ): mixed {
        $normalizedMethod = strtoupper($method);
        if (
            !isset($this->httpMethods[$normalizedMethod]) &&
            !isset($this->sseMethods[$normalizedMethod]) &&
            !isset($this->wsMethods[$normalizedMethod])
        ) {
            throw new \InvalidArgumentException('Unsupported plugin method ' . $method);
        }

        $normalizedPath = $this->normalizePath($path);
        $query = $this->mergeQuery($path, $options['query'] ?? null);
        $headers = $options['headers'] ?? null;

        if (isset($this->sseMethods[$normalizedMethod])) {
            return $this->openSse($normalizedPath, $query, $headers, $options['timeout'] ?? null);
        }

        if (isset($this->wsMethods[$normalizedMethod])) {
            return $this->openWebSocket(
                $normalizedPath,
                $query,
                $headers,
                $options['websocketProtocols'] ?? null
            );
        }

        $payload = $options;
        $payload['method'] = $normalizedMethod;
        $payload['query'] = $query;
        return $this->client->send($normalizedPath, $payload);
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------
    private function normalizePath(string $path): string
    {
        $trimmed = trim($path);
        if ($trimmed === '') {
            return '/api/plugins';
        }

        $clean = $trimmed;
        if (str_starts_with($clean, '/')) {
            $clean = ltrim($clean, '/');
        }

        $cleanPath = explode('?', $clean, 2)[0];
        if (str_starts_with($cleanPath, 'api/plugins')) {
            return '/' . $cleanPath;
        }

        return '/api/plugins/' . $cleanPath;
    }

    private function mergeQuery(string $path, ?array $query): array
    {
        $merged = $query ? $query : [];
        if (!str_contains($path, '?')) {
            return $merged;
        }

        $inline = explode('?', $path, 2)[1];
        parse_str($inline, $inlineParams);
        if (is_array($inlineParams)) {
            foreach ($inlineParams as $key => $value) {
                if (!array_key_exists($key, $merged)) {
                    $merged[$key] = $value;
                }
            }
        }

        return $merged;
    }

    private function buildUrl(string $path, ?array $query, bool $includeToken = false): string
    {
        $params = $query ? $query : [];
        if ($includeToken && $this->client->authStore->isValid()) {
            $params['token'] = $params['token'] ?? $this->client->authStore->getToken();
        }
        return $this->client->buildUrl($path, $params);
    }

    private function openSse(
        string $path,
        ?array $query = null,
        ?array $headers = null,
        ?float $timeout = null
    ): PluginSSEStream {
        $url = $this->buildUrl($path, $query, true);
        $reqHeaders = [
            'Accept' => 'text/event-stream',
            'Cache-Control' => 'no-store',
            'Accept-Language' => $this->client->lang,
            'User-Agent' => BosBase::USER_AGENT,
        ];
        if ($headers) {
            $reqHeaders = array_merge($reqHeaders, $headers);
        }
        if (!isset($reqHeaders['Authorization']) && $this->client->authStore->isValid()) {
            $reqHeaders['Authorization'] = $this->client->authStore->getToken();
        }

        $headerLines = [];
        foreach ($reqHeaders as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headerLines),
                'timeout' => $timeout ?? 60,
            ],
        ]);

        $handle = @fopen($url, 'r', false, $context);
        if ($handle === false) {
            throw new \RuntimeException('Failed to open plugin SSE stream.');
        }

        return new PluginSSEStream($handle);
    }

    private function openWebSocket(
        string $path,
        ?array $query = null,
        ?array $headers = null,
        ?array $protocols = null
    ): WSClient {
        $httpUrl = $this->buildUrl($path, $query, true);
        if (str_starts_with($httpUrl, 'https://')) {
            $wsUrl = 'wss://' . substr($httpUrl, 8);
        } elseif (str_starts_with($httpUrl, 'http://')) {
            $wsUrl = 'ws://' . substr($httpUrl, 7);
        } else {
            $wsUrl = 'ws://' . ltrim($httpUrl, '/');
        }

        $options = ['timeout' => 5];
        $wsHeaders = $headers ? $headers : [];
        if (!isset($wsHeaders['Authorization']) && $this->client->authStore->isValid()) {
            $wsHeaders['Authorization'] = $this->client->authStore->getToken();
        }
        if ($wsHeaders) {
            $options['headers'] = $wsHeaders;
        }
        if ($protocols) {
            $options['subprotocols'] = $protocols;
        }

        return new WSClient($wsUrl, $options);
    }
}
