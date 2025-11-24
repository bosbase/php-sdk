# OAuth2 Configuration Guide - PHP SDK

This guide explains how to configure OAuth2 authentication providers for auth collections using the BosBase PHP SDK.

## Overview

OAuth2 allows users to authenticate with your application using third-party providers like Google, GitHub, Facebook, etc. Before you can use OAuth2 authentication, you need to:

1. **Create an OAuth2 app** in the provider's dashboard
2. **Obtain Client ID and Client Secret** from the provider
3. **Register a redirect URL** (typically: `https://yourdomain.com/api/oauth2-redirect`)
4. **Configure the provider** in your BosBase auth collection using the SDK

## Prerequisites

- An auth collection in your BosBase instance
- OAuth2 app credentials (Client ID and Client Secret) from your chosen provider
- Admin/superuser authentication to configure collections

## Supported Providers

The following OAuth2 providers are supported:

- **google** - Google OAuth2
- **github** - GitHub OAuth2
- **gitlab** - GitLab OAuth2
- **discord** - Discord OAuth2
- **facebook** - Facebook OAuth2
- **microsoft** - Microsoft OAuth2
- **apple** - Apple Sign In
- **twitter** - Twitter OAuth2
- **spotify** - Spotify OAuth2
- **kakao** - Kakao OAuth2
- **twitch** - Twitch OAuth2
- **strava** - Strava OAuth2
- **vk** - VK OAuth2
- **yandex** - Yandex OAuth2
- **patreon** - Patreon OAuth2
- **linkedin** - LinkedIn OAuth2
- **instagram** - Instagram OAuth2
- **vimeo** - Vimeo OAuth2
- **digitalocean** - DigitalOcean OAuth2
- **bitbucket** - Bitbucket OAuth2
- **dropbox** - Dropbox OAuth2
- **planningcenter** - Planning Center OAuth2
- **notion** - Notion OAuth2
- **linear** - Linear OAuth2
- **oidc**, **oidc2**, **oidc3** - OpenID Connect (OIDC) providers

## Basic Usage

### 1. Enable OAuth2 for a Collection

```php
<?php
require_once 'vendor/autoload.php';

use BosBase\BosBase;

$pb = new BosBase('https://your-instance.com');

// Authenticate as admin
$pb->collection('_superusers')->authWithPassword('admin@example.com', 'password');

// Enable OAuth2 for the "users" collection
$pb->collections->enableOAuth2('users');
```

### 2. Add an OAuth2 Provider

```php
// Add Google OAuth2 provider
$pb->collections->addOAuth2Provider('users', [
    'name' => 'google',
    'clientId' => 'your-google-client-id',
    'clientSecret' => 'your-google-client-secret',
    'authURL' => 'https://accounts.google.com/o/oauth2/v2/auth',
    'tokenURL' => 'https://oauth2.googleapis.com/token',
    'userInfoURL' => 'https://www.googleapis.com/oauth2/v2/userinfo',
    'displayName' => 'Google',
    'pkce' => true, // Optional: enable PKCE if supported
]);
```

### 3. Authenticate with OAuth2

```php
// Get OAuth2 providers
$methods = $pb->collection('users')->listAuthMethods();
print_r($methods['oauth2']['providers']); // Available providers

// Authenticate with OAuth2
// Note: OAuth2 flow typically requires browser redirect
$authData = $pb->collection('users')->authWithOAuth2Code('google', 'authorization_code');
```

## Related Documentation

- [Authentication](./AUTHENTICATION.md) - User authentication
- [Users Collection Guide](./USERS_COLLECTION_GUIDE.md) - Built-in users collection

