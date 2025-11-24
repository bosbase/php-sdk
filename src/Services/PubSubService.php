<?php

namespace BosBase\Services;

use BosBase\Exceptions\ClientResponseError;
use WebSocket\Client as WSClient;

class PubSubService extends BaseService
{
    private ?WSClient $socket = null;
    /** @var array<string, array<int, callable>> */
    private array $subscriptions = [];
    private bool $shouldStop = false;
    private float $ackTimeout = 10.0;

    public function isConnected(): bool
    {
        return $this->socket !== null;
    }

    public function publish(string $topic, mixed $data): array
    {
        if ($topic === '') {
            throw new \InvalidArgumentException('topic must be set.');
        }

        $requestId = $this->nextRequestId();
        $this->sendEnvelope([
            'type' => 'publish',
            'topic' => $topic,
            'data' => $data,
            'requestId' => $requestId,
        ]);

        try {
            $ack = $this->waitForAck($requestId, $this->ackTimeout);
            return [
                'id' => $ack['id'] ?? $requestId,
                'topic' => $ack['topic'] ?? $topic,
                'created' => $ack['created'] ?? '',
            ];
        } catch (\Throwable $e) {
            throw new ClientResponseError(null, 0, ['message' => $e->getMessage()], false, $e);
        }
    }

    public function subscribe(string $topic, callable $callback): callable
    {
        if ($topic === '') {
            throw new \InvalidArgumentException('topic must be set.');
        }

        $this->subscriptions[$topic] = $this->subscriptions[$topic] ?? [];
        $this->subscriptions[$topic][] = $callback;

        $requestId = $this->nextRequestId();
        $this->sendEnvelope([
            'type' => 'subscribe',
            'topic' => $topic,
            'requestId' => $requestId,
        ]);
        $this->waitForAck($requestId, $this->ackTimeout, false);

        return function () use ($topic, $callback): void {
            $listeners = $this->subscriptions[$topic] ?? [];
            foreach ($listeners as $idx => $cb) {
                if ($cb === $callback) {
                    unset($listeners[$idx]);
                }
            }
            $listeners = array_values($listeners);
            if ($listeners) {
                $this->subscriptions[$topic] = $listeners;
            } else {
                unset($this->subscriptions[$topic]);
                $this->sendEnvelope([
                    'type' => 'unsubscribe',
                    'topic' => $topic,
                    'requestId' => $this->nextRequestId(),
                ]);
            }

            if (empty($this->subscriptions)) {
                $this->disconnect();
            }
        };
    }

    public function unsubscribe(?string $topic = null): void
    {
        if ($topic !== null) {
            unset($this->subscriptions[$topic]);
            $this->sendEnvelope([
                'type' => 'unsubscribe',
                'topic' => $topic,
                'requestId' => $this->nextRequestId(),
            ]);
        } else {
            $this->subscriptions = [];
            $this->sendEnvelope(['type' => 'unsubscribe']);
            $this->disconnect();
        }
    }

    public function disconnect(): void
    {
        $this->shouldStop = true;
        if ($this->socket) {
            try {
                $this->socket->close();
            } catch (\Throwable $e) {
            }
        }
        $this->socket = null;
    }

    /**
     * Blocking loop that dispatches incoming messages to listeners.
     */
    public function run(float $pollInterval = 0.1): void
    {
        $this->shouldStop = false;
        $this->ensureSocket();

        while (!$this->shouldStop && $this->socket) {
            try {
                $payload = $this->socket->receive();
                $this->handleMessage($payload);
            } catch (\Throwable $e) {
                // retry connect on errors
                $this->reconnectWithBackoff();
            }
            usleep((int) ($pollInterval * 1_000_000));
        }
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------
    private function ensureSocket(): void
    {
        if ($this->socket) {
            return;
        }

        $url = $this->buildWebSocketUrl();
        try {
            $this->socket = new WSClient($url, [
                'timeout' => 5,
            ]);
            $this->shouldStop = false;
        } catch (\Throwable $e) {
            throw new ClientResponseError(null, 0, ['message' => 'WebSocket connection failed'], false, $e);
        }
    }

    private function buildWebSocketUrl(): string
    {
        $query = [];
        if ($this->client->authStore->isValid()) {
            $query['token'] = $this->client->authStore->getToken();
        }
        $raw = $this->client->buildUrl('/api/pubsub', $query);
        if (str_starts_with($raw, 'https://')) {
            return 'wss://' . substr($raw, 8);
        }
        if (str_starts_with($raw, 'http://')) {
            return 'ws://' . substr($raw, 7);
        }
        return 'ws://' . ltrim($raw, '/');
    }

    private function sendEnvelope(array $data): void
    {
        $this->ensureSocket();
        if (!$this->socket) {
            throw new \RuntimeException('WebSocket not available');
        }
        $this->socket->send(json_encode($data));
    }

    private function waitForAck(string $requestId, float $timeout, bool $throwOnTimeout = true): array
    {
        $start = microtime(true);
        while (microtime(true) - $start < $timeout) {
            $payload = null;
            try {
                $payload = $this->socket?->receive();
            } catch (\Throwable $e) {
                $this->reconnectWithBackoff();
                continue;
            }

            if ($payload === null) {
                continue;
            }

            $data = $this->decodePayload($payload);
            if (!$data) {
                continue;
            }

            if (isset($data['requestId']) && $data['requestId'] === $requestId) {
                return $data;
            }

            $this->handleDecodedMessage($data);
        }

        if ($throwOnTimeout) {
            throw new \RuntimeException('Timed out waiting for pubsub response.');
        }

        return [];
    }

    private function handleMessage(?string $payload): void
    {
        $data = $this->decodePayload($payload);
        if (!$data) {
            return;
        }
        $this->handleDecodedMessage($data);
    }

    private function handleDecodedMessage(array $data): void
    {
        $type = $data['type'] ?? null;
        switch ($type) {
            case 'message':
                $topic = $data['topic'] ?? '';
                $listeners = $this->subscriptions[$topic] ?? [];
                $msg = [
                    'id' => $data['id'] ?? '',
                    'topic' => $topic,
                    'created' => $data['created'] ?? '',
                    'data' => $data['data'] ?? null,
                ];
                foreach ($listeners as $listener) {
                    try {
                        $listener($msg);
                    } catch (\Throwable $e) {
                    }
                }
                break;
            case 'ready':
                // on reconnect re-subscribe existing topics
                foreach (array_keys($this->subscriptions) as $topic) {
                    $this->sendEnvelope([
                        'type' => 'subscribe',
                        'topic' => $topic,
                        'requestId' => $this->nextRequestId(),
                    ]);
                }
                break;
            case 'published':
            case 'subscribed':
            case 'unsubscribed':
            case 'pong':
            case 'error':
            default:
                // non-dispatch control messages are ignored here
                break;
        }
    }

    private function decodePayload(?string $payload): ?array
    {
        if (!is_string($payload) || $payload === '') {
            return null;
        }
        $data = json_decode($payload, true);
        return is_array($data) ? $data : null;
    }

    private function nextRequestId(): string
    {
        return bin2hex(random_bytes(6)) . dechex((int) (microtime(true) * 1000));
    }

    private function reconnectWithBackoff(): void
    {
        $this->disconnect();
        $intervals = [0.2, 0.5, 1.0, 1.5, 2.0];
        foreach ($intervals as $delay) {
            if ($this->shouldStop) {
                return;
            }
            usleep((int) ($delay * 1_000_000));
            try {
                $this->ensureSocket();
                foreach (array_keys($this->subscriptions) as $topic) {
                    $this->sendEnvelope([
                        'type' => 'subscribe',
                        'topic' => $topic,
                        'requestId' => $this->nextRequestId(),
                    ]);
                }
                return;
            } catch (\Throwable $e) {
                // retry next interval
            }
        }
    }
}
