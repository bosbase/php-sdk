<?php

namespace BosBase\Services;

use BosBase\AuthStore;
use BosBase\BosBase;
use BosBase\Exceptions\ClientResponseError;
use BosBase\Utils;

class RecordService extends BaseCrudService
{
    private string $collectionIdOrName;

    public function __construct(BosBase $client, string $collectionIdOrName)
    {
        parent::__construct($client);
        $this->collectionIdOrName = $collectionIdOrName;
    }

    protected function getBaseCrudPath(): string
    {
        return $this->getBaseCollectionPath() . '/records';
    }

    private function getBaseCollectionPath(): string
    {
        return '/api/collections/' . Utils::encodePathSegment($this->collectionIdOrName);
    }

    // ------------------------------------------------------------------
    // Realtime
    // ------------------------------------------------------------------
    public function subscribe(
        string $topic,
        callable $callback,
        ?array $query = null,
        ?array $headers = null
    ): callable {
        if ($topic === '') {
            throw new \InvalidArgumentException('topic must be set');
        }

        $fullTopic = $this->collectionIdOrName . '/' . $topic;
        return $this->client->realtime->subscribe($fullTopic, $callback, $query, $headers);
    }

    public function unsubscribe(?string $topic = null): void
    {
        if ($topic) {
            $this->client->realtime->unsubscribe($this->collectionIdOrName . '/' . $topic);
        } else {
            $this->client->realtime->unsubscribeByPrefix($this->collectionIdOrName);
        }
    }

    // ------------------------------------------------------------------
    // CRUD sync with auth store
    // ------------------------------------------------------------------
    public function update(
        string $recordId,
        ?array $body = null,
        ?array $query = null,
        ?array $files = null,
        ?array $headers = null,
        ?string $expand = null,
        ?string $fields = null
    ): array {
        $item = parent::update($recordId, $body, $query, $files, $headers, $expand, $fields);
        $this->maybeUpdateAuthRecord($item);
        return $item;
    }

    public function delete(
        string $recordId,
        ?array $body = null,
        ?array $query = null,
        ?array $headers = null
    ): void {
        parent::delete($recordId, $body, $query, $headers);
        if ($this->isAuthRecord($recordId)) {
            $this->client->authStore->clear();
        }
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------
    public function getCount(
        ?string $filter = null,
        ?string $expand = null,
        ?string $fields = null,
        ?array $query = null,
        ?array $headers = null
    ): int {
        $params = $query ? $query : [];
        if ($filter !== null) {
            $params['filter'] = $params['filter'] ?? $filter;
        }
        if ($expand !== null) {
            $params['expand'] = $params['expand'] ?? $expand;
        }
        if ($fields !== null) {
            $params['fields'] = $params['fields'] ?? $fields;
        }

        $data = $this->client->send($this->getBaseCrudPath() . '/count', [
            'method' => 'GET',
            'query' => $params,
            'headers' => $headers,
        ]);

        return (int) ($data['count'] ?? 0);
    }

    public function listAuthMethods(?array $query = null, ?array $headers = null): array
    {
        $params = $query ? $query : [];
        $params['fields'] = $params['fields'] ?? 'mfa,otp,password,oauth2';

        return $this->client->send($this->getBaseCollectionPath() . '/auth-methods', [
            'method' => 'GET',
            'query' => $params,
            'headers' => $headers,
        ]);
    }

    public function authWithPassword(
        string $identity,
        string $password,
        ?string $expand = null,
        ?string $fields = null,
        ?array $body = null,
        ?array $query = null,
        ?array $headers = null
    ): array {
        $payload = $body ? $body : [];
        $payload['identity'] = $identity;
        $payload['password'] = $password;

        $params = $query ? $query : [];
        if ($expand !== null) {
            $params['expand'] = $expand;
        }
        if ($fields !== null) {
            $params['fields'] = $fields;
        }

        $data = $this->client->send($this->getBaseCollectionPath() . '/auth-with-password', [
            'method' => 'POST',
            'body' => $payload,
            'query' => $params,
            'headers' => $headers,
        ]);

        return $this->authResponse($data);
    }

    public function authWithOAuth2Code(
        string $provider,
        string $code,
        string $codeVerifier,
        string $redirectUrl,
        ?array $createData = null,
        ?array $body = null,
        ?array $query = null,
        ?array $headers = null,
        ?string $expand = null,
        ?string $fields = null
    ): array {
        $payload = $body ? $body : [];
        $payload['provider'] = $payload['provider'] ?? $provider;
        $payload['code'] = $payload['code'] ?? $code;
        $payload['codeVerifier'] = $payload['codeVerifier'] ?? $codeVerifier;
        $payload['redirectURL'] = $payload['redirectURL'] ?? $redirectUrl;
        if ($createData !== null) {
            $payload['createData'] = $payload['createData'] ?? $createData;
        }

        $params = $query ? $query : [];
        if ($expand !== null) {
            $params['expand'] = $params['expand'] ?? $expand;
        }
        if ($fields !== null) {
            $params['fields'] = $params['fields'] ?? $fields;
        }

        $data = $this->client->send($this->getBaseCollectionPath() . '/auth-with-oauth2', [
            'method' => 'POST',
            'body' => $payload,
            'query' => $params,
            'headers' => $headers,
        ]);

        return $this->authResponse($data);
    }

    public function authRefresh(
        ?string $expand = null,
        ?string $fields = null,
        ?array $body = null,
        ?array $query = null,
        ?array $headers = null
    ): array {
        $params = $query ? $query : [];
        if ($expand !== null) {
            $params['expand'] = $expand;
        }
        if ($fields !== null) {
            $params['fields'] = $fields;
        }

        $data = $this->client->send($this->getBaseCollectionPath() . '/auth-refresh', [
            'method' => 'POST',
            'body' => $body,
            'query' => $params,
            'headers' => $headers,
        ]);

        return $this->authResponse($data);
    }

    public function requestPasswordReset(
        string $email,
        ?array $body = null,
        ?array $query = null,
        ?array $headers = null
    ): void {
        $payload = $body ? $body : [];
        $payload['email'] = $email;

        $this->client->send($this->getBaseCollectionPath() . '/request-password-reset', [
            'method' => 'POST',
            'body' => $payload,
            'query' => $query,
            'headers' => $headers,
        ]);
    }

    public function confirmPasswordReset(
        string $token,
        string $password,
        string $passwordConfirm,
        ?array $body = null,
        ?array $query = null,
        ?array $headers = null
    ): void {
        $payload = $body ? $body : [];
        $payload['token'] = $token;
        $payload['password'] = $password;
        $payload['passwordConfirm'] = $passwordConfirm;

        $this->client->send($this->getBaseCollectionPath() . '/confirm-password-reset', [
            'method' => 'POST',
            'body' => $payload,
            'query' => $query,
            'headers' => $headers,
        ]);
    }

    public function requestVerification(
        string $email,
        ?array $body = null,
        ?array $query = null,
        ?array $headers = null
    ): void {
        $payload = $body ? $body : [];
        $payload['email'] = $payload['email'] ?? $email;

        $this->client->send($this->getBaseCollectionPath() . '/request-verification', [
            'method' => 'POST',
            'body' => $payload,
            'query' => $query,
            'headers' => $headers,
        ]);
    }

    public function confirmVerification(
        string $token,
        ?array $body = null,
        ?array $query = null,
        ?array $headers = null
    ): void {
        $payload = $body ? $body : [];
        $payload['token'] = $payload['token'] ?? $token;

        $this->client->send($this->getBaseCollectionPath() . '/confirm-verification', [
            'method' => 'POST',
            'body' => $payload,
            'query' => $query,
            'headers' => $headers,
        ]);

        $this->markVerified($token);
    }

    public function requestEmailChange(
        string $newEmail,
        ?array $body = null,
        ?array $query = null,
        ?array $headers = null
    ): void {
        $payload = $body ? $body : [];
        $payload['newEmail'] = $payload['newEmail'] ?? $newEmail;

        $this->client->send($this->getBaseCollectionPath() . '/request-email-change', [
            'method' => 'POST',
            'body' => $payload,
            'query' => $query,
            'headers' => $headers,
        ]);
    }

    public function confirmEmailChange(
        string $token,
        string $password,
        ?array $body = null,
        ?array $query = null,
        ?array $headers = null
    ): void {
        $payload = $body ? $body : [];
        $payload['token'] = $token;
        $payload['password'] = $password;

        $this->client->send($this->getBaseCollectionPath() . '/confirm-email-change', [
            'method' => 'POST',
            'body' => $payload,
            'query' => $query,
            'headers' => $headers,
        ]);

        $this->clearIfSameToken($token);
    }

    public function requestOTP(
        string $email,
        ?array $body = null,
        ?array $query = null,
        ?array $headers = null
    ): array {
        $payload = $body ? $body : [];
        $payload['email'] = $payload['email'] ?? $email;

        return $this->client->send($this->getBaseCollectionPath() . '/request-otp', [
            'method' => 'POST',
            'body' => $payload,
            'query' => $query,
            'headers' => $headers,
        ]);
    }

    public function authWithOTP(
        string $otpId,
        string $password,
        ?string $expand = null,
        ?string $fields = null,
        ?array $body = null,
        ?array $query = null,
        ?array $headers = null
    ): array {
        $payload = $body ? $body : [];
        $payload['otpId'] = $payload['otpId'] ?? $otpId;
        $payload['password'] = $payload['password'] ?? $password;

        $params = $query ? $query : [];
        if ($expand !== null) {
            $params['expand'] = $params['expand'] ?? $expand;
        }
        if ($fields !== null) {
            $params['fields'] = $params['fields'] ?? $fields;
        }

        $data = $this->client->send($this->getBaseCollectionPath() . '/auth-with-otp', [
            'method' => 'POST',
            'body' => $payload,
            'query' => $params,
            'headers' => $headers,
        ]);

        return $this->authResponse($data);
    }

    public function impersonate(
        string $recordId,
        int $duration,
        ?string $expand = null,
        ?string $fields = null,
        ?array $body = null,
        ?array $query = null,
        ?array $headers = null
    ): BosBase {
        $payload = $body ? $body : [];
        $payload['duration'] = $payload['duration'] ?? $duration;

        $params = $query ? $query : [];
        if ($expand !== null) {
            $params['expand'] = $params['expand'] ?? $expand;
        }
        if ($fields !== null) {
            $params['fields'] = $params['fields'] ?? $fields;
        }

        $enrichedHeaders = $headers ? $headers : [];
        if (!isset($enrichedHeaders['Authorization'])) {
            $enrichedHeaders['Authorization'] = $this->client->authStore->getToken();
        }

        $newClient = new BosBase($this->client->baseUrl, new AuthStore(), $this->client->lang, $this->client->timeout);
        $data = $newClient->send($this->getBaseCollectionPath() . '/impersonate/' . Utils::encodePathSegment($recordId), [
            'method' => 'POST',
            'body' => $payload,
            'query' => $params,
            'headers' => $enrichedHeaders,
        ]);

        $newClient->authStore->save($data['token'] ?? '', $data['record'] ?? null);

        return $newClient;
    }

    // ------------------------------------------------------------------
    // Internal helpers
    // ------------------------------------------------------------------
    private function authResponse(array $data): array
    {
        $token = $data['token'] ?? '';
        $record = $data['record'] ?? null;
        if ($token && $record !== null) {
            $this->client->authStore->save($token, $record);
        }

        return $data;
    }

    private function maybeUpdateAuthRecord(array $item): void
    {
        $current = $this->client->authStore->getRecord();
        if (!$current) {
            return;
        }
        if (($current['id'] ?? null) !== ($item['id'] ?? null)) {
            return;
        }
        $currentCollection = $current['collectionId'] ?? ($current['collectionName'] ?? null);
        if ($currentCollection !== $this->collectionIdOrName) {
            return;
        }

        $merged = array_merge($current, $item);
        if (isset($current['expand']) && isset($item['expand'])) {
            $expand = $current['expand'];
            if (is_array($expand)) {
                $expand = array_merge($expand, $item['expand']);
            }
            $merged['expand'] = $expand;
        }

        $this->client->authStore->save($this->client->authStore->getToken(), $merged);
    }

    private function isAuthRecord(string $recordId): bool
    {
        $current = $this->client->authStore->getRecord();
        $currentCollection = $current['collectionId'] ?? ($current['collectionName'] ?? null);
        return $current && ($current['id'] ?? null) === $recordId && $currentCollection === $this->collectionIdOrName;
    }

    private function markVerified(string $token): void
    {
        $current = $this->client->authStore->getRecord();
        if (!$current) {
            return;
        }
        $payload = $this->decodeTokenPayload($token);
        if (
            $payload &&
            ($current['id'] ?? null) === ($payload['id'] ?? null) &&
            ($current['collectionId'] ?? null) === ($payload['collectionId'] ?? null) &&
            empty($current['verified'])
        ) {
            $current['verified'] = true;
            $this->client->authStore->save($this->client->authStore->getToken(), $current);
        }
    }

    private function clearIfSameToken(string $token): void
    {
        $current = $this->client->authStore->getRecord();
        $payload = $this->decodeTokenPayload($token);
        if (
            $current &&
            $payload &&
            ($current['id'] ?? null) === ($payload['id'] ?? null) &&
            ($current['collectionId'] ?? null) === ($payload['collectionId'] ?? null)
        ) {
            $this->client->authStore->clear();
        }
    }

    private function decodeTokenPayload(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        $payloadPart = $parts[1] . str_repeat('=', -strlen($parts[1]) % 4);
        $decoded = base64_decode($payloadPart, true);
        if ($decoded === false) {
            return null;
        }

        $data = json_decode($decoded, true);
        return is_array($data) ? $data : null;
    }
}
