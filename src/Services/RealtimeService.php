<?php

namespace BosBase\Services;

use BosBase\Exceptions\ClientResponseError;

class RealtimeService extends BaseService
{
    public $onDisconnect = null;
    public string $clientId = '';

    /** @var array<string, array<int, callable>> */
    private array $subscriptions = [];
    private bool $shouldStop = false;
    private ?\CurlHandle $currentHandle = null;
    private string $buffer = '';

    public function subscribe(
        string $topic,
        callable $callback,
        ?array $query = null,
        ?array $headers = null
    ): callable {
        if ($topic === '') {
            throw new \InvalidArgumentException('topic must be set');
        }

        $key = $this->buildSubscriptionKey($topic, $query, $headers);
        $this->subscriptions[$key] = $this->subscriptions[$key] ?? [];
        $this->subscriptions[$key][] = $callback;

        // refresh server subscriptions on next connect
        if ($this->clientId !== '') {
            $this->submitSubscriptions();
        }

        return function () use ($topic, $callback): void {
            $this->unsubscribeByTopicAndListener($topic, $callback);
        };
    }

    public function unsubscribe(?string $topic = null): void
    {
        if ($topic === null) {
            $changed = !empty($this->subscriptions);
            $this->subscriptions = [];
        } else {
            $keys = $this->keysForTopic($topic);
            $changed = !empty($keys);
            foreach ($keys as $key) {
                unset($this->subscriptions[$key]);
            }
        }

        if ($changed && $this->clientId !== '') {
            $this->submitSubscriptions();
        }

        if (!$this->hasSubscriptions()) {
            $this->disconnect();
        }
    }

    public function unsubscribeByPrefix(string $prefix): void
    {
        foreach (array_keys($this->subscriptions) as $key) {
            if (str_starts_with($key, $prefix)) {
                unset($this->subscriptions[$key]);
            }
        }
        if ($this->clientId !== '') {
            $this->submitSubscriptions();
        }
        if (!$this->hasSubscriptions()) {
            $this->disconnect();
        }
    }

    public function unsubscribeByTopicAndListener(string $topic, callable $listener): void
    {
        $keys = $this->keysForTopic($topic);
        foreach ($keys as $key) {
            $listeners = $this->subscriptions[$key] ?? [];
            foreach ($listeners as $idx => $cb) {
                if ($cb === $listener) {
                    unset($listeners[$idx]);
                }
            }
            if ($listeners) {
                $this->subscriptions[$key] = array_values($listeners);
            } else {
                unset($this->subscriptions[$key]);
            }
        }

        if ($this->clientId !== '') {
            $this->submitSubscriptions();
        }
        if (!$this->hasSubscriptions()) {
            $this->disconnect();
        }
    }

    public function disconnect(): void
    {
        $this->shouldStop = true;
        if ($this->currentHandle) {
            curl_close($this->currentHandle);
            $this->currentHandle = null;
        }
        $this->clientId = '';
    }

    /**
     * Blocking loop that maintains the SSE connection and dispatches events.
     */
    public function run(): void
    {
        $backoff = [0.2, 0.5, 1, 2, 5];
        $attempt = 0;
        $this->shouldStop = false;

        while (!$this->shouldStop && $this->hasSubscriptions()) {
            try {
                $this->listen();
                $attempt = 0;
            } catch (\Throwable $e) {
                $this->clientId = '';
                $this->buffer = '';
                if ($this->onDisconnect) {
                    try {
                        ($this->onDisconnect)($this->getActiveSubscriptions());
                    } catch (\Throwable $ignore) {
                        // ignore callback errors
                    }
                }
                if ($this->shouldStop || !$this->hasSubscriptions()) {
                    break;
                }

                $delay = $backoff[min($attempt, count($backoff) - 1)];
                $attempt++;
                usleep((int) ($delay * 1_000_000));
            }
        }
    }

    /**
     * Lightweight helper to pump the connection for a short duration without blocking forever.
     */
    public function poll(float $durationSeconds = 0.1): void
    {
        $deadline = microtime(true) + $durationSeconds;
        if (!$this->hasSubscriptions()) {
            return;
        }

        if (!$this->currentHandle) {
            $this->listen(true, $durationSeconds);
            return;
        }

        while (microtime(true) < $deadline && !$this->shouldStop) {
            usleep(10_000);
        }
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------
    private function listen(bool $nonBlocking = false, ?float $maxDuration = null): void
    {
        $url = $this->client->buildUrl('/api/realtime');
        $headers = [
            'Accept: text/event-stream',
            'Cache-Control: no-store',
            'Accept-Language: ' . $this->client->lang,
        ];
        if ($this->client->authStore->isValid()) {
            $headers[] = 'Authorization: ' . $this->client->authStore->getToken();
        }

        $this->buffer = '';
        $this->currentHandle = curl_init($url);
        curl_setopt($this->currentHandle, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($this->currentHandle, CURLOPT_WRITEFUNCTION, function ($ch, $chunk) {
            $this->handleChunk($chunk);
            return strlen($chunk);
        });
        curl_setopt($this->currentHandle, CURLOPT_NOPROGRESS, false);
        curl_setopt($this->currentHandle, CURLOPT_PROGRESSFUNCTION, function () {
            return $this->shouldStop ? 1 : 0;
        });
        curl_setopt($this->currentHandle, CURLOPT_TIMEOUT, $nonBlocking ? max($maxDuration ?? 1, 1) : 0);
        curl_setopt($this->currentHandle, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->currentHandle, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($this->currentHandle);
        $errorNo = curl_errno($this->currentHandle);
        $errorMsg = curl_error($this->currentHandle);
        $this->currentHandle = null;

        if ($this->shouldStop) {
            return;
        }

        if ($errorNo === CURLE_ABORTED_BY_CALLBACK) {
            return;
        }

        if ($result === false) {
            throw new \RuntimeException('Realtime connection failed: ' . $errorMsg);
        }
    }

    private function handleChunk(string $chunk): void
    {
        $this->buffer .= $chunk;

        while (true) {
            $pos = strpos($this->buffer, "\n\n");
            $delimLen = 2;
            if ($pos === false) {
                $pos = strpos($this->buffer, "\r\n\r\n");
                $delimLen = 4;
            }
            if ($pos === false) {
                break;
            }

            $rawEvent = substr($this->buffer, 0, $pos);
            $this->buffer = substr($this->buffer, $pos + $delimLen);
            $this->dispatchRawEvent($rawEvent);
        }
    }

    private function dispatchRawEvent(string $rawEvent): void
    {
        $event = [
            'event' => 'message',
            'data' => '',
            'id' => '',
        ];

        $lines = preg_split('/\r?\n/', $rawEvent);
        foreach ($lines as $line) {
            if ($line === '' || str_starts_with($line, ':')) {
                continue;
            }
            if (str_contains($line, ':')) {
                [$field, $value] = explode(':', $line, 2);
                $value = ltrim($value);
            } else {
                $field = $line;
                $value = '';
            }

            if ($field === 'event') {
                $event['event'] = $value ?: 'message';
            } elseif ($field === 'data') {
                $event['data'] .= $value . "\n";
            } elseif ($field === 'id') {
                $event['id'] = $value;
            }
        }

        $event['data'] = rtrim($event['data'], "\n");
        $this->dispatchEvent($event);
    }

    private function dispatchEvent(array $event): void
    {
        $name = $event['event'] ?? 'message';
        $dataStr = $event['data'] ?? '';
        $payload = [];

        if ($dataStr !== '') {
            $decoded = json_decode($dataStr, true);
            $payload = is_array($decoded) ? $decoded : ['raw' => $dataStr];
        }

        if ($name === 'PB_CONNECT') {
            $this->clientId = $payload['clientId'] ?? ($event['id'] ?? '');
            $this->submitSubscriptions();
            if ($this->onDisconnect) {
                try {
                    ($this->onDisconnect)([]);
                } catch (\Throwable $ignore) {
                }
            }
            return;
        }

        $listeners = $this->subscriptions[$name] ?? [];
        foreach ($listeners as $listener) {
            try {
                $listener($payload);
            } catch (\Throwable $e) {
                // best-effort delivery
            }
        }
    }

    private function submitSubscriptions(): void
    {
        if ($this->clientId === '' || !$this->hasSubscriptions()) {
            return;
        }

        $payload = [
            'clientId' => $this->clientId,
            'subscriptions' => $this->getActiveSubscriptions(),
        ];

        try {
            $this->client->send('/api/realtime', [
                'method' => 'POST',
                'body' => $payload,
            ]);
        } catch (ClientResponseError $err) {
            if ($err->isAbort) {
                return;
            }
            throw $err;
        }
    }

    private function buildSubscriptionKey(string $topic, ?array $query, ?array $headers): string
    {
        $key = $topic;
        $options = [];
        if ($query) {
            $options['query'] = $query;
        }
        if ($headers) {
            $options['headers'] = $headers;
        }
        if ($options) {
            $serialized = json_encode($options, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $key .= (str_contains($key, '?') ? '&' : '?') . 'options=' . rawurlencode((string) $serialized);
        }
        return $key;
    }

    private function keysForTopic(string $topic): array
    {
        $result = [];
        $prefix = $topic . '?';
        foreach (array_keys($this->subscriptions) as $key) {
            if ($key === $topic || str_starts_with($key, $prefix)) {
                $result[] = $key;
            }
        }
        return $result;
    }

    private function hasSubscriptions(): bool
    {
        foreach ($this->subscriptions as $listeners) {
            if (!empty($listeners)) {
                return true;
            }
        }
        return false;
    }

    public function getActiveSubscriptions(): array
    {
        return array_keys($this->subscriptions);
    }
}
