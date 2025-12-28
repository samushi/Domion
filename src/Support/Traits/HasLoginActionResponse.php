<?php

declare(strict_types=1);

namespace Samushi\Domion\Support\Traits;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Lang;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;
use Samushi\Domion\Support\Repositories;

/**
 * Trait for Login Actions (Central and Tenant)
 *
 * This trait handles login logic for both Central and Tenant contexts.
 * It provides a clean, reusable interface for authentication.
 *
 * @example
 * ```php
 * class LoginAction extends ActionFactory
 * {
 *     use HasLoginActionResponse;
 *
 *     protected function handle(LoginDto $dto): array
 *     {
 *         return $this
 *             ->withRepository(UserRepository::class)
 *             ->withRefreshTokenRepository(RefreshTokenRepository::class)
 *             ->responseToLogin($dto->email, $dto->password);
 *     }
 * }
 * ```
 */
trait HasLoginActionResponse
{
    use HasTokenResponse;

    /**
     * User Repository
     */
    private ?Repositories $repository = null;

    /**
     * Refresh Token Repository
     */
    private ?Repositories $refreshTokenRepository = null;

    /**
     * Token name for Sanctum
     */
    private string $tokenName = 'api-token';

    /**
     * Set the user repository
     */
    protected function withRepository(Repositories|string $repository): static
    {
        $this->repository = is_string($repository) ? app($repository) : $repository;
        return $this;
    }

    /**
     * Set the refresh token repository
     */
    protected function withRefreshTokenRepository(Repositories|string $repository): static
    {
        $this->refreshTokenRepository = is_string($repository) ? app($repository) : $repository;
        return $this;
    }

    /**
     * Set custom token name
     */
    protected function withTokenName(string $name): static
    {
        $this->tokenName = $name;
        return $this;
    }

    /**
     * Get the user repository
     */
    protected function getRepository(): Repositories
    {
        if (!$this->repository) {
            throw new \RuntimeException('Repository not set. Call withRepository() first.');
        }
        return $this->repository;
    }

    /**
     * Get the refresh token repository
     */
    protected function getRefreshTokenRepository(): ?Repositories
    {
        return $this->refreshTokenRepository;
    }

    /**
     * Response to login (Standard context)
     *
     * @param string $email
     * @param string $password
     * @param bool $remember
     * @return array
     */
    protected function responseToLogin(string $email, string $password, bool $remember = false): array
    {
        $user = $this->login($email, $password);
        $accessToken = $this->createToken($user);
        $refreshToken = $this->createRefreshToken($user);

        return $this->buildTokenResponse($accessToken, $refreshToken, $user);
    }

    /**
     * Response to login for web sessions (Inertia/Blade/Livewire)
     *
     * @param string $email
     * @param string $password
     * @param bool $remember
     * @return mixed The authenticated user
     */
    protected function responseToSessionLogin(string $email, string $password, bool $remember = false): mixed
    {
        $user = $this->login($email, $password);

        // Regenerate session
        request()->session()->regenerate();

        // Login using Laravel's auth
        auth()->login($user, $remember);

        return $user;
    }

    /**
     * Response to login (Tenant context)
     *
     * @param string $email
     * @param string $password
     * @param string $tenantId
     * @return array
     */
    protected function responseToTenantLogin(string $email, string $password, string $tenantId): array
    {
        $user = $this->login($email, $password);
        $accessToken = $this->createTenantToken($user, $tenantId);
        $refreshToken = $this->createRefreshToken($user);

        return $this->buildTokenResponse($accessToken, $refreshToken, $user, $tenantId);
    }

    /**
     * Attempt to authenticate user
     *
     * @param string $email
     * @param string $password
     * @return mixed
     * @throws ValidationException
     */
    protected function login(string $email, string $password): mixed
    {
        $user = $this->findUserByEmail($email);

        if (!$user || !Hash::check($password, $user->password)) {
            $this->throwInvalidCredentials();
        }

        return $user;
    }

    /**
     * Find user by email
     *
     * @param string $email
     * @return mixed
     */
    protected function findUserByEmail(string $email): mixed
    {
        $repository = $this->getRepository();

        if (method_exists($repository, 'findByEmail')) {
            return $repository->findByEmail($email);
        }

        return $repository->findWhere(['email' => $email])->first();
    }

    /**
     * Throw invalid credentials exception
     *
     * @throws ValidationException
     */
    protected function throwInvalidCredentials(): never
    {
        throw ValidationException::withMessages([
            'email' => [Lang::get('auth.failed', [], 'en') ?? 'These credentials do not match our records.']
        ]);
    }

    /**
     * Create access token
     *
     * @param mixed $user
     * @return NewAccessToken
     */
    protected function createToken(mixed $user): NewAccessToken
    {
        return $user->createToken($this->tokenName);
    }

    /**
     * Create access token with tenant ID embedded
     *
     * @param mixed $user
     * @param string $tenantId
     * @return NewAccessToken
     */
    protected function createTenantToken(mixed $user, string $tenantId): NewAccessToken
    {
        return $user->createToken("tenant_{$tenantId}");
    }

    /**
     * Create refresh token
     *
     * @param mixed $user
     * @return mixed
     */
    protected function createRefreshToken(mixed $user): mixed
    {
        if (!$this->refreshTokenRepository) {
            return (object)['token' => bin2hex(random_bytes(32))];
        }

        return $this->refreshTokenRepository->create([
            'user_id' => $user->id,
            'token' => bin2hex(random_bytes(32)),
            'expires_at' => now()->addDays(30),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
