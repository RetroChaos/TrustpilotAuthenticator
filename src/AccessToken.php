<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Trustpilot\Api\Authenticator;

use DateTimeImmutable;
use DateTimeInterface;
use Exception;

class AccessToken implements \Serializable
{
    /**
     * @var string
     */
    private string $token;

    /**
     * @var DateTimeImmutable
     */
    private DateTimeImmutable $expiry;

  /**
   * @param string $token
   * @param DateTimeInterface $expiry
   * @throws Exception
   */
    public function __construct(string $token, DateTimeInterface $expiry)
    {
        $this->token = $token;

        if ($expiry instanceof DateTimeImmutable) {
            $this->expiry = $expiry;
        } else {
            $this->expiry = new DateTimeImmutable('@' .$expiry->getTimestamp());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function serialize(): ?string {
        return serialize([
            'token'  => $this->token,
            'expiry' => $this->expiry,
        ]);
    }

    public function __serialize() {
      return serialize([
                         'token'  => $this->token,
                         'expiry' => $this->expiry,
                       ]);
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized) {
        list($this->token, $this->expiry) = unserialize($serialized, ['allowed_classes' => [DateTimeImmutable::class]]);
    }

    public function __unserialize($serialized) {
      list($this->token, $this->expiry) = unserialize($serialized, ['allowed_classes' => [DateTimeImmutable::class]]);
    }

    /**
     * @return bool
     */
    public function hasExpired(): bool {
        return $this->expiry->getTimestamp() < time();
    }

    /**
     * @return string
     */
    public function getToken(): string {
        return $this->token;
    }

    /**
     * @return DateTimeImmutable
     */
    public function getExpiry(): DateTimeImmutable {
        return $this->expiry;
    }
}
