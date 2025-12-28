<?php

declare(strict_types=1);

namespace Samushi\Domion\Support\Traits;

use Laravel\Sanctum\NewAccessToken;

/**
 * Trait for building consistent token responses
 *
 * This trait provides methods to build standardized token responses
 * for both access and refresh tokens.
 */
trait HasTokenResponse
{
    /**
     * Build a standardized token response
     *
     * @param NewAccessToken $accessToken
     * @param mixed $refreshToken
     * @param mixed $user
     * @param string|null $tenantId Optional tenant ID for multi-tenancy
     * @return array
     */
    protected function buildTokenResponse(
        NewAccessToken $accessToken,
        mixed $refreshToken,
        mixed $user,
        ?string $tenantId = null
    ): array {
        $response = [
            'access_token' => $accessToken->plainTextToken,
            'refresh_token' => $refreshToken->token ?? $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => config('sanctum.expiration', 60) * 60,
            'user' => $this->formatUserResponse($user),
        ];

        if ($tenantId !== null) {
            $response['tenant_id'] = $tenantId;
            $response['permissions'] = $this->getUserPermissions($user);
        }

        return $response;
    }

    /**
     * Format user data for response
     *
     * @param mixed $user
     * @return array
     */
    protected function formatUserResponse(mixed $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }

    /**
     * Get user permissions if using Spatie Permissions
     *
     * @param mixed $user
     * @return array
     */
    protected function getUserPermissions(mixed $user): array
    {
        if (method_exists($user, 'getAllPermissions')) {
            return $user->getAllPermissions()->pluck('name')->toArray();
        }

        return [];
    }

    /**
     * Get user roles if using Spatie Permissions
     *
     * @param mixed $user
     * @return array
     */
    protected function getUserRoles(mixed $user): array
    {
        if (method_exists($user, 'getRoleNames')) {
            return $user->getRoleNames()->toArray();
        }

        return [];
    }
}
