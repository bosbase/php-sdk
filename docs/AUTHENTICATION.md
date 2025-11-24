# Authentication - PHP SDK Documentation

## Overview

Authentication in BosBase is stateless and token-based. A client is considered authenticated as long as it sends a valid `Authorization: YOUR_AUTH_TOKEN` header with requests.

**Key Points:**
- **No sessions**: BosBase APIs are fully stateless (tokens are not stored in the database)
- **No logout endpoint**: To "logout", simply clear the token from your local state (`$pb->authStore->clear()`)
- **Token generation**: Auth tokens are generated through auth collection Web APIs or programmatically
- **Admin users**: `_superusers` collection works like regular auth collections but with full access (API rules are ignored)
- **OAuth2 limitation**: OAuth2 is not supported for `_superusers` collection

## Authentication Methods

BosBase supports multiple authentication methods that can be configured individually for each auth collection:

1. **Password Authentication** - Email/username + password
2. **OTP Authentication** - One-time password via email
3. **OAuth2 Authentication** - Google, GitHub, Microsoft, etc.
4. **Multi-factor Authentication (MFA)** - Requires 2 different auth methods

## Authentication Store

The SDK maintains an `authStore` that automatically manages the authentication state:

```php
<?php
require_once 'vendor/autoload.php';

use BosBase\BosBase;

$pb = new BosBase('http://localhost:8090');

// Check authentication status
echo $pb->authStore->isValid() ? 'true' : 'false';      // true/false
echo $pb->authStore->getToken();        // current auth token
print_r($pb->authStore->getRecord());       // authenticated user record

// Clear authentication (logout)
$pb->authStore->clear();
```

## Password Authentication

Authenticate using email/username and password. The identity field can be configured in the collection options (default is email).

**Backend Endpoint:** `POST /api/collections/{collection}/auth-with-password`

### Basic Usage

```php
<?php
require_once 'vendor/autoload.php';

use BosBase\BosBase;

$pb = new BosBase('http://localhost:8090');

// Authenticate with email and password
$authData = $pb->collection('users')->authWithPassword(
    'test@example.com',
    'password123'
);

// Auth data is automatically stored in pb.authStore
echo $pb->authStore->isValid() ? 'true' : 'false';  // true
echo $pb->authStore->getToken();    // JWT token
echo $pb->authStore->getRecord()['id']; // user record ID
```

### Response Format

```php
[
    'token' => 'eyJhbGciOiJIUzI1NiJ9...',
    'record' => [
        'id' => 'record_id',
        'email' => 'test@example.com',
        // ... other user fields
    ]
]
```

### Error Handling with MFA

```php
try {
    $pb->collection('users')->authWithPassword('test@example.com', 'pass123');
} catch (\BosBase\Exceptions\ClientResponseError $err) {
    // Check for MFA requirement
    $data = $err->getData();
    if (isset($data['mfaId'])) {
        $mfaId = $data['mfaId'];
        // Handle MFA flow (see Multi-factor Authentication section)
    } else {
        echo 'Authentication failed: ' . $err->getMessage() . "\n";
    }
}
```

## OTP Authentication

One-time password authentication via email.

**Backend Endpoints:**
- `POST /api/collections/{collection}/request-otp` - Request OTP
- `POST /api/collections/{collection}/auth-with-otp` - Authenticate with OTP

### Request OTP

```php
// Send OTP to user's email
$result = $pb->collection('users')->requestOTP('test@example.com');
echo $result['otpId'];  // OTP ID to use in authWithOTP
```

### Authenticate with OTP

```php
// Step 1: Request OTP
$result = $pb->collection('users')->requestOTP('test@example.com');

// Step 2: User enters OTP from email
$authData = $pb->collection('users')->authWithOTP(
    $result['otpId'],
    '123456'  // OTP code from email
);
```

## OAuth2 Authentication

**Backend Endpoint:** `POST /api/collections/{collection}/auth-with-oauth2`

### Manual Code Exchange

```php
// Get auth methods
$authMethods = $pb->collection('users')->listAuthMethods();
$provider = null;
foreach ($authMethods['oauth2']['providers'] as $p) {
    if ($p['name'] === 'google') {
        $provider = $p;
        break;
    }
}

// Exchange code for token (after OAuth2 redirect)
$authData = $pb->collection('users')->authWithOAuth2Code(
    $provider['name'],
    $code,
    $provider['codeVerifier'],
    $redirectUrl
);
```

## Multi-Factor Authentication (MFA)

Requires 2 different auth methods.

```php
$mfaId = null;

try {
    // First auth method (password)
    $pb->collection('users')->authWithPassword('test@example.com', 'pass123');
} catch (\BosBase\Exceptions\ClientResponseError $err) {
    $data = $err->getData();
    if (isset($data['mfaId'])) {
        $mfaId = $data['mfaId'];
        
        // Second auth method (OTP)
        $otpResult = $pb->collection('users')->requestOTP('test@example.com');
        $pb->collection('users')->authWithOTP(
            $otpResult['otpId'],
            '123456',
            ['mfaId' => $mfaId]  // Pass mfaId in body
        );
    }
}
```

## User Impersonation

Superusers can impersonate other users.

**Backend Endpoint:** `POST /api/collections/{collection}/impersonate/{id}`

```php
// Authenticate as superuser
$pb->collection('_superusers')->authWithPassword('admin@example.com', 'adminpass');

// Impersonate a user
$impersonateClient = $pb->collection('users')->impersonate(
    'USER_RECORD_ID',
    3600  // Optional: token duration in seconds
);

// Use impersonate client
$data = $impersonateClient->collection('posts')->getFullList();
```

## Auth Token Verification

Verify token by calling `authRefresh()`.

**Backend Endpoint:** `POST /api/collections/{collection}/auth-refresh`

```php
try {
    $authData = $pb->collection('users')->authRefresh();
    echo 'Token is valid' . "\n";
} catch (\Exception $err) {
    echo 'Token verification failed: ' . $err->getMessage() . "\n";
    $pb->authStore->clear();
}
```

## List Available Auth Methods

**Backend Endpoint:** `GET /api/collections/{collection}/auth-methods`

```php
$authMethods = $pb->collection('users')->listAuthMethods();
echo $authMethods['password']['enabled'] ? 'true' : 'false';
print_r($authMethods['oauth2']['providers']);
echo $authMethods['mfa']['enabled'] ? 'true' : 'false';
```

## Complete Examples

### Example 1: Complete Authentication Flow with Error Handling

```php
<?php
require_once 'vendor/autoload.php';

use BosBase\BosBase;
use BosBase\Exceptions\ClientResponseError;

$pb = new BosBase('http://localhost:8090');

function authenticateUser($pb, $email, $password) {
    try {
        // Try password authentication
        $authData = $pb->collection('users')->authWithPassword($email, $password);
        
        echo 'Successfully authenticated: ' . $authData['record']['email'] . "\n";
        return $authData;
        
    } catch (ClientResponseError $err) {
        // Check if MFA is required
        if ($err->getStatus() === 401) {
            $data = $err->getData();
            if (isset($data['mfaId'])) {
                echo 'MFA required, proceeding with second factor...' . "\n";
                return handleMFA($pb, $email, $data['mfaId']);
            }
        }
        
        // Handle other errors
        if ($err->getStatus() === 400) {
            throw new \Exception('Invalid credentials');
        } else if ($err->getStatus() === 403) {
            throw new \Exception('Password authentication is not enabled for this collection');
        } else {
            throw $err;
        }
    }
}

function handleMFA($pb, $email, $mfaId) {
    // Request OTP for second factor
    $otpResult = $pb->collection('users')->requestOTP($email);
    
    // In a real app, show a modal/form for the user to enter OTP
    // For this example, we'll simulate getting the OTP
    $userEnteredOTP = getUserOTPInput(); // Your UI function
    
    try {
        // Authenticate with OTP and MFA ID
        $authData = $pb->collection('users')->authWithOTP(
            $otpResult['otpId'],
            $userEnteredOTP,
            ['mfaId' => $mfaId]  // Pass mfaId in body
        );
        
        echo 'MFA authentication successful' . "\n";
        return $authData;
    } catch (ClientResponseError $err) {
        if ($err->getStatus() === 429) {
            throw new \Exception('Too many OTP attempts, please request a new OTP');
        }
        throw new \Exception('Invalid OTP code');
    }
}

// Usage
try {
    authenticateUser($pb, 'user@example.com', 'password123');
    echo 'User is authenticated: ' . json_encode($pb->authStore->getRecord()) . "\n";
} catch (\Exception $err) {
    echo 'Authentication failed: ' . $err->getMessage() . "\n";
}
```

### Example 2: Token Management and Refresh

```php
<?php
require_once 'vendor/autoload.php';

use BosBase\BosBase;

$pb = new BosBase('http://localhost:8090');

// Check if user is already authenticated
function checkAuth($pb) {
    if ($pb->authStore->isValid()) {
        echo 'User is authenticated: ' . $pb->authStore->getRecord()['email'] . "\n";
        
        // Verify token is still valid and refresh if needed
        try {
            $pb->collection('users')->authRefresh();
            echo 'Token refreshed successfully' . "\n";
            return true;
        } catch (\Exception $err) {
            echo 'Token expired or invalid, clearing auth' . "\n";
            $pb->authStore->clear();
            return false;
        }
    }
    return false;
}

// Usage
if (!checkAuth($pb)) {
    // Redirect to login
    echo 'Please login' . "\n";
}
```

### Example 3: Admin Impersonation for Support

```php
<?php
require_once 'vendor/autoload.php';

use BosBase\BosBase;

$pb = new BosBase('http://localhost:8090');

function impersonateUserForSupport($pb, $userId) {
    // Authenticate as admin
    $pb->collection('_superusers')->authWithPassword('admin@example.com', 'adminpassword');
    
    // Impersonate the user (1 hour token)
    $userClient = $pb->collection('users')->impersonate($userId, 3600);
    
    echo 'Impersonating user: ' . $userClient->authStore->getRecord()['email'] . "\n";
    
    // Use the impersonated client to test user experience
    $userRecords = $userClient->collection('posts')->getFullList();
    echo 'User can see ' . count($userRecords) . ' posts' . "\n";
    
    // Check what the user sees
    $userView = $userClient->collection('posts')->getList(1, 10, [
        'filter' => 'published = true'
    ]);
    
    return [
        'canAccess' => count($userView['items']),
        'totalPosts' => count($userRecords)
    ];
}

// Usage in support dashboard
try {
    $result = impersonateUserForSupport($pb, 'user_record_id');
    echo 'User access check: ' . json_encode($result) . "\n";
} catch (\Exception $err) {
    echo 'Impersonation failed: ' . $err->getMessage() . "\n";
}
```

## Best Practices

1. **Secure Token Storage**: Never expose tokens in client-side code or logs
2. **Token Refresh**: Implement automatic token refresh before expiration
3. **Error Handling**: Always handle MFA requirements and token expiration
4. **OAuth2 Security**: Always validate the `state` parameter in OAuth2 callbacks
5. **API Keys**: Use impersonation tokens for server-to-server communication only
6. **Superuser Tokens**: Never expose superuser impersonation tokens in client code
7. **OTP Security**: Use OTP with MFA for security-critical applications
8. **Rate Limiting**: Be aware of rate limits on authentication endpoints

## Troubleshooting

### Token Expired
If you get 401 errors, check if the token has expired:
```php
try {
    $pb->collection('users')->authRefresh();
} catch (\Exception $err) {
    // Token expired, require re-authentication
    $pb->authStore->clear();
    // Redirect to login
}
```

### MFA Required
If authentication returns 401 with mfaId:
```php
$data = $err->getData();
if (isset($data['mfaId'])) {
    // Proceed with second authentication factor
}
```

## Related Documentation

- [Collections](./COLLECTIONS.md)
- [API Rules](./API_RULES_AND_FILTERS.md)

