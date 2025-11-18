# Trustpilot Authenticator

A PHP library for obtaining access tokens for the [Trustpilot Business User OAuth API](https://developers.trustpilot.com/authentication).

Originally developed and open-sourced by [moneymaxim](https://www.moneymaxim.co.uk).  

Fully modernised to:
- Use **Symfony HTTP Client**
- Support **all current Trustpilot OAuth grant types**
- Provide **type-safe error handling**
- Provide **refresh & revoke** support

## Install

Install via [Composer](https://getcomposer.org/):

```sh
composer require retrochaos/trustpilot-authenticator
```

The package has no external dependencies other than Symfony’s HTTP client (automatically installed).

## Supported OAuth Flows

This library supports all Trustpilot Business OAuth grant types:

| Flow | Method |
|------|--------|
| Password (legacy, deprecated but still works for old apps) | `requestPasswordAccessToken()` |
| Client Credentials (server-to-server) | `requestClientCredentialsAccessToken()` |
| Authorization Code (interactive user login) | `requestAuthorizationCodeAccessToken()` |
| Refresh Token | `refreshAccessToken()` |
| Revoke Refresh Token | `revokeRefreshToken()` |
| Build Authorization URL | `buildAuthorizationUrl()` |

## Usage

### Create the authenticator

```php
use Trustpilot\Api\Authenticator\Authenticator;

$authenticator = new Authenticator();
```

### 1. Password Grant (Deprecated by Trustpilot)

Useful only for older Trustpilot applications pre-February 2025.

```php
$token = $authenticator->requestPasswordAccessToken(
    $apiKey,
    $apiSecret,
    $username,
    $password
);

$token->getToken();           // string
$token->getExpiry();          // DateTimeInterface
$token->getRefreshToken();    // ?string
```

The alias `getAccessToken()` still works for backwards compatibility.

### 2. Client Credentials Grant (Recommended)

Best for server-to-server integrations.

```php
$token = $authenticator->requestClientCredentialsAccessToken(
    $apiKey,
    $apiSecret
);
```

### 3. Authorization Code Grant (Interactive User Login)

#### Step 1 — Redirect the user to login

```php
$url = $authenticator->buildAuthorizationUrl(
    $apiKey,
    $redirectUri,
    $state = 'optional-state',
    ['business-user-access-read', 'another-scope']
);

// Redirect the browser:
header("Location: $url");
exit;
```

#### Step 2 — Exchange the returned `code` for a real access token

```php
$token = $authenticator->requestAuthorizationCodeAccessToken(
    $apiKey,
    $apiSecret,
    $_GET['code'],
    $redirectUri
);
```

### 4. Refresh an Access Token

```php
$newToken = $authenticator->refreshAccessToken(
    $apiKey,
    $apiSecret,
    $oldToken->getRefreshToken()
);
```

### 5. Revoke a Refresh Token

```php
$authenticator->revokeRefreshToken($refreshToken);
```

### 6. buildAuthorizationUrl()

`buildAuthorizationUrl()` constructs a Trustpilot-compatible login URL for the **Authorization Code** OAuth flow.

#### Signature

```php
public function buildAuthorizationUrl(
    string $clientId,
    string $redirectUri,
    ?string $state = null,
    array $scopes = []
): string
```

#### Parameters

| Parameter | Description |
|----------|-------------|
| `clientId` | Your Trustpilot API key |
| `redirectUri` | URL that Trustpilot redirects the user back to |
| `state` | Optional anti-CSRF or session identifier |
| `scopes` | Optional list of OAuth scopes as an array of strings |

#### Example

```php
$url = $authenticator->buildAuthorizationUrl(
    $apiKey,
    'https://example.com/oauth/callback',
    'session-123',
    ['business-user-access-read']
);

echo $url;
```

Example output:

```
https://authenticate.trustpilot.com?client_id=YOUR_KEY&redirect_uri=https%3A%2F%2Fexample.com%2Foauth%2Fcallback&response_type=code&state=session-123&scope=business-user-access-read
```

#### What you do with it

- Redirect the user to the URL
- Trustpilot handles login
- Trustpilot redirects them back to your app with `?code=...`
- Exchange that code using `requestAuthorizationCodeAccessToken()`

## Error Handling

All errors throw a single exception type:

```php
use Trustpilot\Api\Authenticator\AuthenticatorException;

try {
    $token = $authenticator->requestClientCredentialsAccessToken($apiKey, $apiSecret);
} catch (AuthenticatorException $e) {
    // Network errors
    // Invalid credentials
    // Invalid OAuth grant
    // HTTP 4xx/5xx
    // Malformed responses
    echo $e->getMessage();
}
```

### AccessToken Object

Every method returns an `AccessToken` instance:

```php
$token->getToken();        // string
$token->getExpiry();       // DateTimeInterface
$token->getRefreshToken(); // ?string
$token->isExpired();       // bool
```

## Tests

A full PHPUnit test harness is included and covers:

- Successful authentication flows
- Error codes (400/401/403/500)
- Network exceptions
- Refresh & revoke
- Authorization URL generation

Run tests with:

```sh
vendor/bin/phpunit
```

## License
MIT License — feel free to use, modify, and contribute.