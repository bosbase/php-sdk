<?php

use BosBase\Utils;
use PHPUnit\Framework\TestCase;

class UtilsTest extends TestCase
{
    public function testNormalizeQueryParams(): void
    {
        $params = [
            'a' => null,
            'b' => [1, '2', null],
            'c' => 'x y',
        ];

        $normalized = Utils::normalizeQueryParams($params);

        $this->assertSame(['b' => ['1', '2'], 'c' => ['x y']], $normalized);
    }

    public function testEncodePathSegment(): void
    {
        $this->assertSame('a%20b%2Fc', Utils::encodePathSegment('a b/c'));
    }

    public function testToSerializableFiltersNulls(): void
    {
        $data = [
            'a' => 1,
            'b' => null,
            'nested' => [
                'c' => 2,
                'd' => null,
            ],
        ];

        $serialized = Utils::toSerializable($data);

        $this->assertSame(['a' => 1, 'nested' => ['c' => 2]], $serialized);
    }

    public function testEnsureFilePayloadCreatesCurlFile(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'bbfile');
        file_put_contents($tmp, 'demo');

        $files = Utils::ensureFilePayload(['file' => $tmp]);

        $this->assertArrayHasKey('file', $files);
        $this->assertInstanceOf(\CURLFile::class, $files['file']);

        @unlink($tmp);
    }
}
