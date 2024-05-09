<?php

declare(strict_types=1);

/*
 * This file is part of Swiss Alpine Club Contao Login Client Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/swiss-alpine-club-contao-login-client-bundle
 */

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\OAuth2\Client\Provider;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class ProviderConfiguration
{
    public function __construct(
        #[Autowire('%sac_oauth2_client.oidc.client_id%')]
        private string $clientId,
        #[Autowire('%sac_oauth2_client.oidc.client_secret%')]
        private string $clientSecret,
        #[Autowire('%sac_oauth2_client.oidc.auth_provider_endpoint_authorize%')]
        private string $authorizeEndpoint,
        #[Autowire('%sac_oauth2_client.oidc.auth_provider_endpoint_token%')]
        private string $tokenEndpoint,
        #[Autowire('%sac_oauth2_client.oidc.auth_provider_endpoint_userinfo%')]
        private string $userinfoEndpoint,
        #[Autowire('%sac_oauth2_client.oidc.client_auth_endpoint_backend_route%')]
        private string $backendRedirectRoute,
        #[Autowire('%sac_oauth2_client.oidc.client_auth_endpoint_frontend_route%')]
        private string $frontendRedirectRoute,
    ) {
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function getClientSecret(): string
    {
        return $this->clientSecret;
    }

    public function getAuthorizeEndpoint(): string
    {
        return $this->authorizeEndpoint;
    }

    public function getTokenEndpoint(): string
    {
        return $this->tokenEndpoint;
    }

    public function getUserinfoEndpoint(): string
    {
        return $this->userinfoEndpoint;
    }

    public function getBackendRedirectRoute(): string
    {
        return $this->backendRedirectRoute;
    }

    public function getFrontendRedirectRoute(): string
    {
        return $this->frontendRedirectRoute;
    }
}
