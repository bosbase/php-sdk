<?php

namespace BosBase;

use BosBase\Exceptions\ClientResponseError;
use BosBase\Services\BackupService;
use BosBase\Services\BatchService;
use BosBase\Services\CacheService;
use BosBase\Services\CollectionService;
use BosBase\Services\CronService;
use BosBase\Services\FileService;
use BosBase\Services\GraphQLService;
use BosBase\Services\HealthService;
use BosBase\Services\LangChaingoService;
use BosBase\Services\LLMDocumentService;
use BosBase\Services\LogService;
use BosBase\Services\PubSubService;
use BosBase\Services\RealtimeService;
use BosBase\Services\RecordService;
use BosBase\Services\SettingsService;
use BosBase\Services\SQLService;
use BosBase\Services\VectorService;

class BosBase
{
    public const USER_AGENT = 'bosbase-php-sdk/0.1.0';

    public string $baseUrl;
    public string $lang;
    public float $timeout;
    public AuthStore $authStore;
    /** @var callable|null */
    public $beforeSend = null;
    /** @var callable|null */
    public $afterSend = null;

    public CollectionService $collections;
    public FileService $files;
    public LogService $logs;
    public RealtimeService $realtime;
    public PubSubService $pubsub;
    public SettingsService $settings;
    public HealthService $health;
    public BackupService $backups;
    public CronService $crons;
    public VectorService $vectors;
    public LangChaingoService $langchaingo;
    public LLMDocumentService $llmDocuments;
    public CacheService $caches;
    public GraphQLService $graphql;
    public SQLService $sql;

    /** @var array<string, RecordService> */
    private array $recordServices = [];

    public function __construct(
        string $baseUrl = '/',
        ?AuthStore $authStore = null,
        string $lang = 'en-US',
        float $timeout = 30.0
    ) {
        $trimmed = rtrim($baseUrl, '/');
        $this->baseUrl = $trimmed !== '' ? $trimmed : '/';
        $this->lang = $lang;
        $this->timeout = $timeout;
        $this->authStore = $authStore ?: new AuthStore();

        $this->collections = new CollectionService($this);
        $this->files = new FileService($this);
        $this->logs = new LogService($this);
        $this->realtime = new RealtimeService($this);
        $this->pubsub = new PubSubService($this);
        $this->settings = new SettingsService($this);
        $this->health = new HealthService($this);
        $this->backups = new BackupService($this);
        $this->crons = new CronService($this);
        $this->vectors = new VectorService($this);
        $this->langchaingo = new LangChaingoService($this);
        $this->llmDocuments = new LLMDocumentService($this);
        $this->caches = new CacheService($this);
        $this->graphql = new GraphQLService($this);
        $this->sql = new SQLService($this);
    }

    public function __destruct()
    {
        try {
            $this->close();
        } catch (\Throwable $e) {
            // best-effort cleanup
        }
    }

    public function close(): void
    {
        $this->realtime->disconnect();
        $this->pubsub->disconnect();
    }

    public function collection(string $collectionIdOrName): RecordService
    {
        if (!isset($this->recordServices[$collectionIdOrName])) {
            $this->recordServices[$collectionIdOrName] = new RecordService(
                $this,
                $collectionIdOrName
            );
        }

        return $this->recordServices[$collectionIdOrName];
    }

    public function createBatch(): BatchService
    {
        return new BatchService($this);
    }

    public function filter(string $expr, ?array $params = null): string
    {
        if (!$params) {
            return $expr;
        }

        foreach ($params as $key => $value) {
            $placeholder = '{:' . $key . '}';
            if (is_string($value)) {
                $safe = str_replace("'", "\\'", $value);
                $expr = str_replace($placeholder, "'" . $safe . "'", $expr);
            } elseif ($value === null) {
                $expr = str_replace($placeholder, 'null', $expr);
            } elseif (is_bool($value)) {
                $expr = str_replace($placeholder, $value ? 'true' : 'false', $expr);
            } elseif ($value instanceof \DateTimeInterface) {
                $expr = str_replace(
                    $placeholder,
                    "'" . $value->format('Y-m-d H:i:s') . "'",
                    $expr
                );
            } else {
                $json = json_encode($value);
                $json = $json !== false ? $json : (string) $value;
                $expr = str_replace(
                    $placeholder,
                    "'" . str_replace("'", "\\'", $json) . "'",
                    $expr
                );
            }
        }

        return $expr;
    }

    public function buildUrl(string $path, ?array $query = null): string
    {
        $base = $this->baseUrl;
        if (!str_ends_with($base, '/')) {
            $base .= '/';
        }

        $relative = ltrim($path, '/');
        $url = $base . $relative;

        $normalized = Utils::normalizeQueryParams($query);
        if ($normalized) {
            $pairs = [];
            foreach ($normalized as $key => $values) {
                foreach ($values as $val) {
                    $pairs[] = rawurlencode($key) . '=' . rawurlencode($val);
                }
            }
            $separator = str_contains($url, '?') ? '&' : '?';
            $url .= $separator . implode('&', $pairs);
        }

        return $url;
    }

    public function getFileUrl(
        array $record,
        string $filename,
        ?string $thumb = null,
        ?string $token = null,
        ?bool $download = null,
        ?array $query = null
    ): string {
        return $this->files->getUrl($record, $filename, $thumb, $token, $download, $query);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function send(string $path, array $options = []): mixed
    {
        $method = strtoupper($options['method'] ?? 'GET');
        $headers = $options['headers'] ?? [];
        $query = $options['query'] ?? [];
        $body = $options['body'] ?? null;
        $files = $options['files'] ?? [];
        $timeout = isset($options['timeout']) ? (float) $options['timeout'] : $this->timeout;

        $url = $this->buildUrl($path, $query);

        $hookOptions = [
            'method' => $method,
            'headers' => $headers,
            'body' => $body,
            'query' => $query,
            'files' => $files,
            'timeout' => $timeout,
        ];

        if ($this->beforeSend) {
            $result = call_user_func($this->beforeSend, $url, $hookOptions);
            if (is_array($result)) {
                if (isset($result['url'])) {
                    $url = (string) $result['url'];
                }
                if (isset($result['options']) && is_array($result['options'])) {
                    $hookOptions = array_merge($hookOptions, $result['options']);
                } elseif ($result) {
                    $hookOptions = array_merge($hookOptions, $result);
                }
            }

            $method = strtoupper($hookOptions['method'] ?? $method);
            $headers = $hookOptions['headers'] ?? $headers;
            $body = array_key_exists('body', $hookOptions) ? $hookOptions['body'] : $body;
            $query = $hookOptions['query'] ?? $query;
            $files = $hookOptions['files'] ?? $files;
            $timeout = isset($hookOptions['timeout']) ? (float) $hookOptions['timeout'] : $timeout;
            $url = $this->buildUrl($path, $query);
        }

        $requestHeaders = array_merge([
            'Accept-Language' => $this->lang,
            'User-Agent' => self::USER_AGENT,
        ], $headers ?? []);

        if (!isset($requestHeaders['Authorization']) && $this->authStore->isValid()) {
            $requestHeaders['Authorization'] = $this->authStore->getToken();
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $headersPayload = [];
        foreach ($requestHeaders as $name => $value) {
            $headersPayload[] = $name . ': ' . $value;
        }
        if ($headersPayload) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headersPayload);
        }

        $hasFiles = is_array($files) && count($files) > 0;
        if ($hasFiles) {
            $normalizedFiles = Utils::ensureFilePayload($files);
            $jsonPayload = $body !== null ? Utils::toSerializable($body) : [];
            $normalizedFiles['@jsonPayload'] = json_encode($jsonPayload ?? []);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $normalizedFiles);
        } elseif ($body !== null) {
            $payload = Utils::toSerializable($body);
            $json = json_encode($payload);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
            if (!isset($requestHeaders['Content-Type'])) {
                $headersPayload[] = 'Content-Type: application/json';
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headersPayload);
            }
        } elseif ($method === 'GET') {
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        }

        $response = curl_exec($ch);
        if ($response === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new ClientResponseError($url, 0, [], false, new \RuntimeException($err));
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headerStr = substr($response, 0, $headerSize);
        $bodyStr = substr($response, $headerSize);
        curl_close($ch);

        $headerBlocks = preg_split("/\r?\n\r?\n/", trim((string) $headerStr));
        $lastHeaderBlock = $headerBlocks ? end($headerBlocks) : '';
        $headersAssoc = [];
        $contentType = '';
        foreach (explode("\n", (string) $lastHeaderBlock) as $line) {
            $line = trim($line);
            if ($line === '' || stripos($line, 'HTTP/') === 0) {
                continue;
            }
            if (str_contains($line, ':')) {
                [$name, $val] = explode(':', $line, 2);
                $headersAssoc[trim($name)] = trim($val);
                if (stripos($name, 'Content-Type') === 0) {
                    $contentType = trim($val);
                }
            }
        }

        $data = null;
        if ($status !== 204) {
            if (stripos($contentType, 'application/json') !== false) {
                $decoded = json_decode($bodyStr, true);
                $data = is_array($decoded) ? $decoded : [];
            } else {
                $data = $bodyStr;
            }
        }

        if ($status >= 400) {
            throw new ClientResponseError($url, (int) $status, is_array($data) ? $data : []);
        }

        if ($this->afterSend) {
            $responseMeta = [
                'status' => $status,
                'headers' => $headersAssoc,
                'url' => $url,
            ];
            $data = call_user_func($this->afterSend, $responseMeta, $data, $hookOptions);
        }

        return $data;
    }
}
