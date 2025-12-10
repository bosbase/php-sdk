<?php

use BosBase\AuthStore;
use PHPUnit\Framework\TestCase;

class AuthStoreTest extends TestCase
{
    private function makeToken(array $payload): string
    {
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'none', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $body = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        return $header . '.' . $body . '.sig';
    }

    public function testIsValidChecksExpiration(): void
    {
        $store = new AuthStore();

        $expired = $this->makeToken(['exp' => time() - 10, 'type' => 'auth']);
        $store->save($expired, null);
        $this->assertFalse($store->isValid());

        $valid = $this->makeToken(['exp' => time() + 3600, 'type' => 'auth']);
        $store->save($valid, null);
        $this->assertTrue($store->isValid());
    }

    public function testSuperuserDetection(): void
    {
        $store = new AuthStore();
        $token = $this->makeToken([
            'exp' => time() + 3600,
            'type' => 'auth',
            'collectionId' => 'pbc_3142635823',
        ]);

        $store->save($token, null);
        $this->assertTrue($store->isSuperuser());

        $record = ['collectionName' => '_superusers', 'id' => '123'];
        $store->save($token, $record);
        $this->assertTrue($store->isSuperuser());
        $this->assertFalse($store->isAuthRecord());
    }

    public function testAuthRecordDetection(): void
    {
        $store = new AuthStore();
        $token = $this->makeToken([
            'exp' => time() + 3600,
            'type' => 'auth',
            'collectionId' => 'users',
        ]);

        $record = ['collectionName' => 'users', 'id' => 'u1'];
        $store->save($token, $record);

        $this->assertTrue($store->isAuthRecord());
        $this->assertFalse($store->isSuperuser());
    }

    public function testListenersAreInvokedOnSave(): void
    {
        $store = new AuthStore();
        $invoked = 0;

        $store->addListener(function ($token, $record) use (&$invoked) {
            $invoked++;
            $this->assertNotEmpty($token);
            $this->assertIsArray($record);
        });

        $store->save($this->makeToken(['exp' => time() + 100, 'type' => 'auth']), ['id' => '1']);

        $this->assertSame(1, $invoked);
    }
}
