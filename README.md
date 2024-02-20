# Trustpilot Authenticator

A PHP library for obtaining [Trustpilot Business User API](https://developers.trustpilot.com/authentication) access tokens.

This library has been developed and open sourced by [moneymaxim](https://www.moneymaxim.co.uk).

Updated the code to use Guzzle7

## Install

Install using [composer](https://getcomposer.org/):

```sh
composer install moneymaxim/trustpilot-authenticator
```

## Usage

```php
$authenticator = new Trustpilot\Api\Authenticator\Authenticator();

$accessToken = $authenticator->getAccessToken($apiKey, $apiToken, $username, $password);

// $accessToken->getToken(): string
// $accessToken->hasExpired(): bool
// $accessToken->getExpiry(): \DateTimeImmutable
// $accessToken->serialize(): string
```
