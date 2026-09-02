<?php

declare(strict_types=1);

/*
 * This file is part of Swiss Alpine Club Contao Login Client Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/swiss-alpine-club-contao-login-client-bundle
 */

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\OAuth2\Client\Provider;

use Contao\CoreBundle\Routing\ScopeMatcher;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

readonly class ProviderConfiguration
{
    /**
     * @var list<OAuthScope>
     */
    private array $oauthScopes;

    private PkceMethod $pkceMethod;

    /**
     * @param list<string> $oauthScopes
     */
    public function __construct(
        private RequestStack $requestStack,
        private RouterInterface $router,
        private ScopeMatcher $scopeMatcher,
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
        #[Autowire('%sac_oauth2_client.oidc.oauth_scopes%')]
        array $oauthScopes,
        #[Autowire('%sac_oauth2_client.oidc.pkce_method%')]
        string $pkceMethod,
    ) {
        // The container parameters are plain strings, this is where they become enums.
        $this->oauthScopes = array_map(OAuthScope::from(...), array_values($oauthScopes));
        $this->pkceMethod = PkceMethod::from($pkceMethod);
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

    public function getRedirectUrl(): string
    {
        $request = $this->requestStack->getCurrentRequest();

        $route = $this->getBackendRedirectRoute();

        if ($this->scopeMatcher->isFrontendRequest($request)) {
            $route = $this->getFrontendRedirectRoute();
        }

        return $this->router->generate($route, [], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /**
     * @return list<OAuthScope>
     */
    public function getScopes(): array
    {
        return $this->oauthScopes;
    }

    public function getPkceMethod(): PkceMethod
    {
        return $this->pkceMethod;
    }

    public function all(): array
    {
        return [
            'clientId' => $this->getClientId(),
            // The client password assigned to you by the provider
            'clientSecret' => $this->getClientSecret() ?? '',
            // Absolute url to the "authorize" endpoint
            'urlAuthorize' => $this->getAuthorizeEndpoint() ?? '',
            // Absolute url to the 'get access token" endpoint
            'urlAccessToken' => $this->getTokenEndpoint() ?? '',
            // Absolute url to the "get resource owner details endpoint"
            'urlResourceOwnerDetails' => $this->getUserinfoEndpoint() ?? '',
            // Absolute callback url to your login route (must be registered by the
            // service provider.)
            'redirectUri' => $this->getRedirectUrl(),
            'scopes' => $this->getScopes(),
            // Proof Key for Code Exchange (RFC 7636)
            'pkceMethod' => $this->getPkceMethod(),
        ];
    }
}
