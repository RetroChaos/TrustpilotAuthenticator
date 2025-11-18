<?php

namespace Trustpilot\Api\Authenticator;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class Authenticator
{
	public const TOKEN_ENDPOINT   = 'https://api.trustpilot.com/v1/oauth/oauth-business-users-for-applications/accesstoken';
	public const REFRESH_ENDPOINT = 'https://api.trustpilot.com/v1/oauth/oauth-business-users-for-applications/refresh';
	public const REVOKE_ENDPOINT  = 'https://api.trustpilot.com/v1/oauth/oauth-business-users-for-applications/revoke';
	public const AUTH_ENDPOINT    = 'https://authenticate.trustpilot.com';

	private HttpClientInterface $httpClient;

	public function __construct(?HttpClientInterface $httpClient = null)
	{
		$this->httpClient = $httpClient ?? HttpClient::create();
	}

	/**
	 * BACKWARDS-COMPAT method: password grant.
	 *
	 * @deprecated Use requestPasswordAccessToken() instead.
	 *
	 * @throws AuthenticatorException
	 */
	public function getAccessToken(
		string $apiKey,
		string $apiSecret,
		string $username,
		string $password
	): AccessToken {
		return $this->requestPasswordAccessToken($apiKey, $apiSecret, $username, $password);
	}

	/**
	 * PASSWORD GRANT (legacy).
	 * @throws AuthenticatorException
	 */
	public function requestPasswordAccessToken(
		string $apiKey,
		string $apiSecret,
		string $username,
		string $password
	): AccessToken {
		$data = $this->requestTokenWithBasicAuth(
			self::TOKEN_ENDPOINT,
			$apiKey,
			$apiSecret,
			[
				'grant_type' => 'password',
				'username'   => $username,
				'password'   => $password,
			]
		);

		return $this->createAccessTokenFromResponse($data);
	}

	/**
	 * CLIENT CREDENTIALS GRANT.
	 * @throws AuthenticatorException
	 */
	public function requestClientCredentialsAccessToken(
		string $apiKey,
		string $apiSecret
	): AccessToken {
		$data = $this->requestTokenWithBasicAuth(
			self::TOKEN_ENDPOINT,
			$apiKey,
			$apiSecret,
			['grant_type' => 'client_credentials']
		);

		return $this->createAccessTokenFromResponse($data);
	}

	/**
	 * AUTHORIZATION CODE GRANT.
	 * @throws AuthenticatorException
	 */
	public function requestAuthorizationCodeAccessToken(
		string $apiKey,
		string $apiSecret,
		string $code,
		string $redirectUri
	): AccessToken {
		$data = $this->requestTokenWithBasicAuth(
			self::TOKEN_ENDPOINT,
			$apiKey,
			$apiSecret,
			[
				'grant_type'   => 'authorization_code',
				'code'         => $code,
				'redirect_uri' => $redirectUri,
			]
		);

		return $this->createAccessTokenFromResponse($data);
	}

	/**
	 * REFRESH TOKEN GRANT.
	 * @throws AuthenticatorException
	 */
	public function refreshAccessToken(
		string $apiKey,
		string $apiSecret,
		string $refreshToken
	): AccessToken {
		$data = $this->requestTokenWithBasicAuth(
			self::REFRESH_ENDPOINT,
			$apiKey,
			$apiSecret,
			[
				'grant_type'    => 'refresh_token',
				'refresh_token' => $refreshToken,
			]
		);

		return $this->createAccessTokenFromResponse($data);
	}

	/**
	 * REVOKE refresh token.
	 * @throws AuthenticatorException
	 */
	public function revokeRefreshToken(string $refreshToken): void
	{
		try {
			$response = $this->httpClient->request('POST', self::REVOKE_ENDPOINT, [
				'body' => ['token' => $refreshToken],
			]);

			$status = $response->getStatusCode();
		} catch (TransportExceptionInterface $e) {
			throw new AuthenticatorException(
				'Network error while revoking Trustpilot refresh token: ' . $e->getMessage(),
				0,
				$e
			);
		}

		if ($status < 200 || $status >= 300) {
			throw new AuthenticatorException(
				"Trustpilot returned HTTP $status while attempting to revoke token.",
				$status
			);
		}
	}

	public function buildAuthorizationUrl(
		string $apiKey,
		string $redirectUri,
		?string $state = null,
		array $scopes = []
	): string {
		$query = [
			'client_id'     => $apiKey,
			'redirect_uri'  => $redirectUri,
			'response_type' => 'code',
		];

		if ($state !== null) {
			$query['state'] = $state;
		}

		if (!empty($scopes)) {
			$query['scope'] = implode(' ', $scopes);
		}

		return self::AUTH_ENDPOINT . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
	}

	/**
	 * Performs POST with Basic auth and handles **all** Trustpilot errors.
	 * @throws AuthenticatorException
	 */
	private function requestTokenWithBasicAuth(
		string $url,
		string $apiKey,
		string $apiSecret,
		array $payload
	): array {
		try {
			$response = $this->httpClient->request('POST', $url, [
				'auth_basic' => [$apiKey, $apiSecret],
				'body'       => $payload,
			]);

			$status = $response->getStatusCode();
		} catch (TransportExceptionInterface $e) {
			throw new AuthenticatorException(
				'Network error while contacting Trustpilot OAuth endpoint: ' . $e->getMessage(),
				0,
				$e
			);
		}

		// Read body safely using stream (even though Trustpilot rarely sends one)
		// Type-cast to string to suppress random PHPStorm error
		$rawContent = (string)'';
		foreach ($this->httpClient->stream($response) as $chunk) {
			try {
				$rawContent .= $chunk->getContent();
			} catch (TransportExceptionInterface $e) {
				throw new AuthenticatorException(
					'Network error while steaming content: ' . $e->getMessage(),
					0,
					$e
				);
			}
		}

		if ($status >= 400) {
			throw new AuthenticatorException(
				sprintf("Trustpilot returned HTTP %d during OAuth request.", $status),
				$status
			);
		}

		$data = json_decode($rawContent, true);

		if (json_last_error() !== JSON_ERROR_NONE) {
			throw new AuthenticatorException(
				'Failed to decode Trustpilot OAuth JSON response: ' . json_last_error_msg()
			);
		}

		if (!is_array($data)) {
			throw new AuthenticatorException('Unexpected OAuth response format from Trustpilot.');
		}

		if (!isset($data['access_token'])) {
			throw new AuthenticatorException('Token response missing required field: access_token');
		}

		return $data;
	}

	/**
	 * Turns token JSON into AccessToken object.
	 * @throws AuthenticatorException
	 */
	private function createAccessTokenFromResponse(array $data): AccessToken
	{
		try {
			$token = (string) $data['access_token'];

			if (!empty($data['expires_in'])) {
				$expiry = new \DateTime('@' . (time() + (int)$data['expires_in']));
			} else {
				$expiry = new \DateTime('+100 hours');
			}

			$refreshToken = $data['refresh_token'] ?? null;

			return new AccessToken($token, $expiry, $refreshToken);
		} catch (\Throwable $e) {
			throw new AuthenticatorException(
				'Could not create AccessToken from Trustpilot data: ' . $e->getMessage(),
				0,
				$e
			);
		}
	}
}
