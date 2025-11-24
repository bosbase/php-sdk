<?php

namespace BosBase;

/**
 * In-memory authentication store shared across services.
 */
class AuthStore
{
    private string $token = '';
    private ?array $record = null;
    /** @var callable[] */
    private array $listeners = [];

    public function getToken(): string
    {
        return $this->token;
    }

    public function getRecord(): ?array
    {
        return $this->record;
    }

    public function isValid(): bool
    {
        if ($this->token === '') {
            return false;
        }

        $parts = explode('.', $this->token);
        if (count($parts) !== 3) {
            return false;
        }

        $payloadPart = $parts[1] . str_repeat('=', -strlen($parts[1]) % 4);
        $decoded = base64_decode($payloadPart, true);
        if ($decoded === false) {
            return false;
        }

        $payload = json_decode($decoded, true);
        if (!is_array($payload) || !isset($payload['exp'])) {
            return false;
        }

        $exp = (int) $payload['exp'];
        return $exp > time();
    }

    /**
     * Register listener called on token changes.
     *
     * @param callable(string, ?array):void $callback
     */
    public function addListener(callable $callback): void
    {
        $this->listeners[] = $callback;
    }

    /**
     * Remove a previously registered listener.
     *
     * @param callable(string, ?array):void $callback
     */
    public function removeListener(callable $callback): void
    {
        foreach ($this->listeners as $idx => $listener) {
            if ($listener === $callback) {
                unset($this->listeners[$idx]);
                $this->listeners = array_values($this->listeners);
                break;
            }
        }
    }

    public function save(string $token, ?array $record): void
    {
        $this->token = $token;
        $this->record = $record;

        foreach ($this->listeners as $listener) {
            try {
                $listener($this->token, $this->record);
            } catch (\Throwable $e) {
                // best-effort notification
            }
        }
    }

    public function clear(): void
    {
        $this->save('', null);
    }
}
