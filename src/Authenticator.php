<?php

namespace Trustpilot\Api\Authenticator;

use DateTime;
use Exception;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use GuzzleHttp\Exception\GuzzleException;

class Authenticator {
    const ENDPOINT = 'https://api.trustpilot.com/v1/oauth/oauth-business-users-for-applications/accesstoken';

    /**
     * @var GuzzleClientInterface
     */
    private GuzzleClientInterface $guzzle;

  /**
   * @param GuzzleClientInterface|null $guzzle
   */
    public function __construct(GuzzleClientInterface $guzzle = null) {
        $this->guzzle = (null !== $guzzle) ? $guzzle : new GuzzleClient();
    }

  /**
   * @param string $apiKey
   * @param string $apiSecret
   * @param string $username
   * @param string $password
   *
   * @return AccessToken
   * @throws GuzzleException
   * @throws Exception
   */
    public function getAccessToken(string $apiKey, string $apiSecret, string $username, string $password): AccessToken {
        $response = $this->guzzle->request('POST', self::ENDPOINT, [
            'auth' => [$apiKey, $apiSecret],
            'form_params' => [
                'grant_type' => 'password',
                'username' => $username,
                'password' => $password,
            ],
        ]);

        $data = json_decode((string) $response->getBody(), true);

        $token = $data['access_token'];
        $expiry = new DateTime('@' . (time() + $data['expires_in']));

        return new AccessToken($token, $expiry);
    }
}
