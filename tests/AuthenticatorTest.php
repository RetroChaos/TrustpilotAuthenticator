<?php

namespace Trustpilot\Test\Api\Authenticator;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Trustpilot\Api\Authenticator\AccessToken;
use Trustpilot\Api\Authenticator\Authenticator;
use Trustpilot\Api\Authenticator\AuthenticatorException;

class AuthenticatorTest extends TestCase
{
	private const API_KEY    = 'api-key';
	private const API_SECRET = 'api-secret';
	private const USERNAME   = 'user@example.com';
	private const PASSWORD   = 'secret';
	private const REDIRECT   = 'https://example.com/callback';

	/**
	 * Helper: Authenticator with a single canned response.
	 */
	private function createAuthenticatorWithResponse(int $statusCode, string $body): Authenticator
	{
		$response = new MockResponse($body, ['http_code' => $statusCode]);
		$client   = new MockHttpClient($response);

		return new Authenticator($client);
	}

	public function testRequestPasswordAccessTokenSuccess(): void
	{
		$expiresIn = 3600;

		$body = json_encode([
			'access_token'  => 'abc123',
			'expires_in'    => $expiresIn,
			'refresh_token' => 'refresh123',
		], JSON_THROW_ON_ERROR);

		$authenticator = $this->createAuthenticatorWithResponse(200, $body);

		$start = time();

		$token = $authenticator->requestPasswordAccessToken(
			self::API_KEY,
			self::API_SECRET,
			self::USERNAME,
			self::PASSWORD
		);

		$this->assertInstanceOf(AccessToken::class, $token);
		$this->assertSame('abc123', $token->getToken());

		$expiry = $token->getExpiry();
		$this->assertInstanceOf(\DateTimeInterface::class, $expiry);

		// Expiry should be ~ now + 3600 (allow small drift)
		$this->assertGreaterThanOrEqual($start + $expiresIn - 5, $expiry->getTimestamp());
		$this->assertLessThanOrEqual($start + $expiresIn + 5, $expiry->getTimestamp());

		if (method_exists($token, 'getRefreshToken')) {
			$this->assertSame('refresh123', $token->getRefreshToken());
		}
	}

	public function testRequestClientCredentialsAccessTokenSuccess(): void
	{
		$body = json_encode([
			'access_token' => 'client-token',
			'expires_in'   => 7200,
		], JSON_THROW_ON_ERROR);

		$authenticator = $this->createAuthenticatorWithResponse(200, $body);

		$token = $authenticator->requestClientCredentialsAccessToken(
			self::API_KEY,
			self::API_SECRET
		);

		$this->assertInstanceOf(AccessToken::class, $token);
		$this->assertSame('client-token', $token->getToken());
	}

	public function testUnauthorizedErrorThrowsAuthenticatorException(): void
	{
		$body = '{}';

		$authenticator = $this->createAuthenticatorWithResponse(401, $body);

		$this->expectException(AuthenticatorException::class);
		$this->expectExceptionCode(401);
		$this->expectExceptionMessage('Trustpilot returned HTTP 401 during OAuth request.');

		$authenticator->requestPasswordAccessToken(
			self::API_KEY,
			self::API_SECRET,
			self::USERNAME,
			self::PASSWORD
		);
	}

	public function testServerErrorThrowsAuthenticatorException(): void
	{
		$body = '{}';

		$authenticator = $this->createAuthenticatorWithResponse(500, $body);

		$this->expectException(AuthenticatorException::class);
		$this->expectExceptionCode(500);
		$this->expectExceptionMessage('Trustpilot returned HTTP 500 during OAuth request.');

		$authenticator->requestClientCredentialsAccessToken(
			self::API_KEY,
			self::API_SECRET
		);
	}

	public function testInvalidJsonThrowsAuthenticatorException(): void
	{
		$authenticator = $this->createAuthenticatorWithResponse(200, 'not-json');

		$this->expectException(AuthenticatorException::class);
		$this->expectExceptionMessageMatches(
			'/Failed to decode Trustpilot OAuth JSON response:/'
		);

		$authenticator->requestPasswordAccessToken(
			self::API_KEY,
			self::API_SECRET,
			self::USERNAME,
			self::PASSWORD
		);
	}

	public function testMissingAccessTokenFieldThrowsAuthenticatorException(): void
	{
		$body = json_encode([
			// 'access_token' => 'missing on purpose',
			'expires_in' => 3600,
		], JSON_THROW_ON_ERROR);

		$authenticator = $this->createAuthenticatorWithResponse(200, $body);

		$this->expectException(AuthenticatorException::class);
		$this->expectExceptionMessage('Token response missing required field: access_token');

		$authenticator->requestPasswordAccessToken(
			self::API_KEY,
			self::API_SECRET,
			self::USERNAME,
			self::PASSWORD
		);
	}

	public function testNetworkErrorIsWrappedInAuthenticatorException(): void
	{
		// Mock client that throws a TransportException whenever called
		$client = new MockHttpClient(function () {
			throw new TransportException('Connection timed out');
		});

		$authenticator = new Authenticator($client);

		$this->expectException(AuthenticatorException::class);
		$this->expectExceptionMessage('Network error while contacting Trustpilot OAuth endpoint: Connection timed out');

		$authenticator->requestPasswordAccessToken(
			self::API_KEY,
			self::API_SECRET,
			self::USERNAME,
			self::PASSWORD
		);
	}

	public function testRevokeRefreshTokenSuccess(): void
	{
		$response = new MockResponse('', ['http_code' => 200]);
		$client   = new MockHttpClient($response);

		$authenticator = new Authenticator($client);

		// No exception should be thrown
		$authenticator->revokeRefreshToken('refresh-token');
		$this->addToAssertionCount(1); // just to mark that we got here
	}

	public function testRevokeRefreshTokenFailure(): void
	{
		$response = new MockResponse('', ['http_code' => 500]);
		$client   = new MockHttpClient($response);

		$authenticator = new Authenticator($client);

		$this->expectException(AuthenticatorException::class);
		$this->expectExceptionCode(500);
		$this->expectExceptionMessage(
			'Trustpilot returned HTTP 500 while attempting to revoke token.'
		);

		$authenticator->revokeRefreshToken('refresh-token');
	}

	public function testBuildAuthorizationUrl(): void
	{
		$client        = new MockHttpClient(); // not actually used
		$authenticator = new Authenticator($client);

		$url = $authenticator->buildAuthorizationUrl(
			self::API_KEY,
			self::REDIRECT,
			'state123',
			['scope1', 'scope2']
		);

		$this->assertStringStartsWith('https://authenticate.trustpilot.com?', $url);
		$this->assertStringContainsString('client_id=' . urlencode(self::API_KEY), $url);
		$this->assertStringContainsString('redirect_uri=' . urlencode(self::REDIRECT), $url);
		$this->assertStringContainsString('response_type=code', $url);
		$this->assertStringContainsString('state=state123', $url);
		$this->assertStringContainsString('scope=' . rawurlencode('scope1 scope2'), $url);
	}
}
