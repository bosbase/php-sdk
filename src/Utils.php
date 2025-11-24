<?php

namespace BosBase;

class Utils
{
    public static function toSerializable(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            $result = [];
            foreach ($value as $k => $v) {
                if ($v === null) {
                    continue;
                }
                $result[$k] = self::toSerializable($v);
            }
            return $result;
        }

        if (is_object($value)) {
            if (method_exists($value, 'toArray')) {
                return self::toSerializable($value->toArray());
            }
            if (method_exists($value, 'toDict')) {
                return self::toSerializable($value->toDict());
            }
            if (method_exists($value, 'toJson')) {
                $json = $value->toJson();
                if (is_string($json)) {
                    $decoded = json_decode($json, true);
                    return $decoded ?? $json;
                }
                return self::toSerializable($json);
            }
            if ($value instanceof \JsonSerializable) {
                return $value->jsonSerialize();
            }
        }

        return $value;
    }

    /**
        * Normalize query params to array<string, array<string>>.
        */
    public static function normalizeQueryParams(?array $params): array
    {
        if (!$params) {
            return [];
        }

        $normalized = [];
        foreach ($params as $key => $value) {
            if ($value === null) {
                continue;
            }

            $values = is_array($value) ? $value : [$value];
            $bucket = [];
            foreach ($values as $item) {
                if ($item === null) {
                    continue;
                }
                $bucket[] = (string) $item;
            }

            if ($bucket) {
                $normalized[(string) $key] = $bucket;
            }
        }

        return $normalized;
    }

    public static function encodePathSegment(mixed $value): string
    {
        return rawurlencode((string) $value);
    }

    public static function buildRelativeUrl(string $path, ?array $query = null): string
    {
        $rel = '/' . ltrim($path, '/');
        $normalized = self::normalizeQueryParams($query);
        if (!$normalized) {
            return $rel;
        }

        $pairs = [];
        foreach ($normalized as $key => $values) {
            foreach ($values as $val) {
                $pairs[] = rawurlencode($key) . '=' . rawurlencode($val);
            }
        }

        return $rel . '?' . implode('&', $pairs);
    }

    /**
     * Normalize files payload to an array suitable for CURLOPT_POSTFIELDS.
     *
     * Accepted shapes:
     * - ['field' => new \CURLFile(...)]
     * - ['field' => '/absolute/path/to/file.ext']
     * - ['field' => ['filename.ext', '/path/to/file.ext', 'mime/type']]
     *
     * @return array<string, mixed>
     */
    public static function ensureFilePayload(array $files): array
    {
        $normalized = [];

        foreach ($files as $key => $value) {
            $field = (string) $key;

            if ($value instanceof \CURLFile) {
                $normalized[$field] = $value;
                continue;
            }

            if (is_string($value)) {
                $normalized[$field] = new \CURLFile($value);
                continue;
            }

            if (is_array($value) && count($value) >= 2) {
                [$filename, $fileRef, $mime] = $value + [null, null, 'application/octet-stream'];
                $filePath = is_string($fileRef) ? $fileRef : null;

                if (!$filePath && is_resource($fileRef)) {
                    $meta = stream_get_meta_data($fileRef);
                    if (!empty($meta['uri']) && is_string($meta['uri'])) {
                        $filePath = $meta['uri'];
                    }
                }

                if ($filePath && file_exists($filePath)) {
                    $normalized[$field] = new \CURLFile($filePath, (string) $mime, (string) $filename);
                    continue;
                }
            }

            throw new \InvalidArgumentException('Unsupported file payload for key ' . $field);
        }

        return $normalized;
    }
}
