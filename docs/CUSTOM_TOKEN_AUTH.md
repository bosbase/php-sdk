# Custom Token Binding and Login (PHP SDK)

Bind custom tokens to auth records (`users` or `_superusers`) and authenticate with those tokens. Tokens are stored hashed in the `_token_bindings` table (created automatically on first bind).

- `POST /api/collections/{collection}/bind-token`
- `POST /api/collections/{collection}/unbind-token`
- `POST /api/collections/{collection}/auth-with-token`

## Binding a token

```php
<?php
require __DIR__ . '/../vendor/autoload.php';

use BosBase\BosBase;

$pb = new BosBase('http://127.0.0.1:8090');

// regular user
$pb->collection('users')->bindCustomToken(
    'user@example.com',
    'user-password',
    'my-app-token',
);

// superuser
$pb->collection('_superusers')->bindCustomToken(
    'admin@example.com',
    'admin-password',
    'admin-app-token',
);
```

## Unbinding a token

```php
// remove binding for the user
$pb->collection('users')->unbindCustomToken(
    'user@example.com',
    'user-password',
    'my-app-token',
);

// remove binding for the superuser
$pb->collection('_superusers')->unbindCustomToken(
    'admin@example.com',
    'admin-password',
    'admin-app-token',
);
```

## Logging in with a token

```php
// login with the previously bound token
$auth = $pb->collection('users')->authWithToken('my-app-token');

echo $auth['token'];      // BosBase auth token
print_r($auth['record']); // authenticated record

// superuser token login
$superAuth = $pb->collection('_superusers')->authWithToken('admin-app-token');
echo $superAuth['token'];
```

Notes:
- Binding/unbinding require the account email + password.
- Tokens are scoped per collection; reuse the same token value for users and superusers if you want both.
- MFA and auth rules still apply when authenticating with a token.
