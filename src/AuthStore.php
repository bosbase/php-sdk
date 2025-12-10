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

    public function getTokenPayload(): ?array
    {
        if ($this->token === '') {
            return null;
        }

        $parts = explode('.', $this->token);
        if (count($parts) !== 3) {
            return null;
        }

        $payloadPart = $parts[1];
        $padLen = (4 - (strlen($payloadPart) % 4)) % 4;
        if ($padLen) {
            $payloadPart .= str_repeat('=', $padLen);
        }
        $decoded = base64_decode($payloadPart, true);
        if ($decoded === false) {
            return null;
        }

        $payload = json_decode($decoded, true);
        return is_array($payload) ? $payload : null;
    }

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
        $payload = $this->getTokenPayload();
        if (!$payload || !isset($payload['exp'])) {
            return false;
        }

        return (int) $payload['exp'] > time();
    }

    public function isSuperuser(): bool
    {
        $payload = $this->getTokenPayload();
        if (!$payload || ($payload['type'] ?? null) !== 'auth') {
            return false;
        }

        $collection = $this->record['collectionId'] ?? ($this->record['collectionName'] ?? null);

        if ($collection === '_superusers' || $collection === '_pbc_2773867675') {
            return true;
        }

        $collectionId = $payload['collectionId'] ?? null;
        if ($collectionId === 'pbc_3142635823') {
            return true;
        }

        return false;
    }

    public function isAuthRecord(): bool
    {
        $payload = $this->getTokenPayload();
        return $payload && ($payload['type'] ?? null) === 'auth' && !$this->isSuperuser();
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
