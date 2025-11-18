<?php

namespace Trustpilot\Api\Authenticator;

class AccessToken
{
	private string $token;
	private \DateTimeInterface $expiry;
	private ?string $refreshToken;

	public function __construct(string $token, \DateTimeInterface $expiry, ?string $refreshToken = null) {
		$this->token        = $token;
		$this->expiry       = $expiry;
		$this->refreshToken = $refreshToken;
	}

	public function getToken(): string
	{
		return $this->token;
	}

	public function getExpiry(): \DateTimeInterface
	{
		return $this->expiry;
	}

	public function getRefreshToken(): ?string
	{
		return $this->refreshToken;
	}

	public function isExpired(?\DateTimeInterface $reference = null): bool
	{
		$reference = $reference ?? new \DateTimeImmutable('now');

		return $this->expiry <= $reference;
	}
}
