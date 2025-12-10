<?php

use BosBase\AuthStore;
use BosBase\BosBase;
use BosBase\Services\FileService;
use PHPUnit\Framework\TestCase;

class StubClient extends BosBase
{
    public array $sent = [];
    public array $nextResponses = [];

    public function __construct()
    {
        // Ensure BaseService file is loaded so BaseCrudService is available (both live in the same file).
        class_exists(\BosBase\Services\BaseService::class);
        parent::__construct('http://api.test');
    }

    public function queueResponse(mixed $value): void
    {
        $this->nextResponses[] = $value;
    }

    public function send(string $path, array $options = []): mixed
    {
        $this->sent[] = ['path' => $path, 'options' => $options];
        return array_shift($this->nextResponses);
    }
}

class ServicesTest extends TestCase
{
    public function testCollectionSqlHelpers(): void
    {
        $client = new StubClient();
        $client->queueResponse(['created' => []]);
        $client->collections->registerSqlTables(['t1', 't2'], ['q' => 1], ['X' => 'Y']);

        $client->queueResponse(['created' => []]);
        $client->collections->importSqlTables([['name' => 'legacy', 'sql' => 'select 1']]);

        $this->assertSame('/api/collections/sql/tables', $client->sent[0]['path']);
        $this->assertSame('/api/collections/sql/import', $client->sent[1]['path']);
        $this->assertSame(['tables' => ['t1', 't2']], $client->sent[0]['options']['body']);
    }

    public function testRecordCustomTokenAndExternalAuth(): void
    {
        $client = new StubClient();

        // bind and unbind
        $client->queueResponse(true);
        $this->assertTrue($client->collection('users')->bindCustomToken('e', 'p', 'tok'));
        $client->queueResponse(true);
        $this->assertTrue($client->collection('users')->unbindCustomToken('e', 'p', 'tok'));

        // auth with token updates store
        $client->queueResponse(['token' => 'abc', 'record' => ['id' => '1', 'collectionName' => 'users']]);
        $auth = $client->collection('users')->authWithToken('tok');
        $this->assertSame('abc', $client->authStore->getToken());
        $this->assertSame($auth['record'], $client->authStore->getRecord());

        // external auth listing and unlink
        $client->queueResponse(['items' => [['id' => 'ea1']]]); // list
        $client->queueResponse(['items' => [['id' => 'ea1', 'provider' => 'google']]]); // first list item
        $client->queueResponse(null); // delete response
        $list = $client->collection('users')->listExternalAuths('rid');
        $this->assertNotEmpty($list);
        $this->assertTrue($client->collection('users')->unlinkExternalAuth('rid', 'google'));

        $paths = array_column($client->sent, 'path');
        $this->assertContains('/api/collections/users/bind-token', $paths);
        $this->assertContains('/api/collections/users/unbind-token', $paths);
        $this->assertContains('/api/collections/users/auth-with-token', $paths);
        $this->assertContains('/api/collections/_externalAuths/records', $paths);
    }

    public function testPluginHttpProxy(): void
    {
        $client = new StubClient();
        $client->queueResponse(['ok' => true]);

        $resp = $client->plugins('GET', '/health', ['query' => ['a' => 1]]);

        $this->assertSame(['ok' => true], $resp);
        $this->assertSame('/api/plugins/health', $client->sent[0]['path']);
        $this->assertSame('GET', $client->sent[0]['options']['method']);
    }

    public function testFileUrlBuilder(): void
    {
        $client = new StubClient();
        $service = new FileService($client);
        $record = ['id' => '1', 'collectionName' => 'posts'];

        $url = $service->getUrl($record, 'pic.png', thumb: '100x100', token: 't', download: true);

        $this->assertStringContainsString('/api/files/posts/1/pic.png', $url);
        $this->assertStringContainsString('thumb=100x100', $url);
        $this->assertStringContainsString('token=t', $url);
        $this->assertStringContainsString('download=', $url);
    }
}
