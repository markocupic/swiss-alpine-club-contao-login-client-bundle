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

use Contao\CoreBundle\ContaoCoreBundle;
use League\OAuth2\Client\Provider\AbstractProvider;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\OAuth2\Client\Exception\InvalidOAuth2ProviderConfigurationException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

readonly class ProviderFactory
{
    public function __construct(
        private RouterInterface $router,
        private ProviderConfiguration $providerConfiguration,
    ) {
    }

    public function createProvider(Request $request): AbstractProvider
    {
        $redirectRoute = match ($request->attributes->get('_scope')) {
            ContaoCoreBundle::SCOPE_BACKEND => $this->providerConfiguration->getBackendRedirectRoute(),
            ContaoCoreBundle::SCOPE_FRONTEND => $this->providerConfiguration->getFrontendRedirectRoute(),
            default => null,
        };

        if (null === $redirectRoute) {
            throw new \Exception(sprintf('Scope must be "%s" or "%s".', ContaoCoreBundle::SCOPE_BACKEND, ContaoCoreBundle::SCOPE_FRONTEND));
        }

        $providerConfig = [
            // The client ID assigned to you by the provider
            'clientId' => $this->providerConfiguration->getClientId() ?? '',
            // The client password assigned to you by the provider
            'clientSecret' => $this->providerConfiguration->getClientSecret() ?? '',
            // Absolute url to the "authorize" endpoint
            'urlAuthorize' => $this->providerConfiguration->getAuthorizeEndpoint() ?? '',
            // Absolute url to the "get access token" endpoint
            'urlAccessToken' => $this->providerConfiguration->getTokenEndpoint() ?? '',
            // Absolute url to the "get resource owner details endpoint"
            'urlResourceOwnerDetails' => $this->providerConfiguration->getUserinfoEndpoint() ?? '',
            // Absolute callback url to your login route (must be registered by the service provider.)
            'redirectUri' => $this->router->generate(
                $redirectRoute,
                [],
                UrlGeneratorInterface::ABSOLUTE_URL,
            ),
            'scopes' => ['openid'],
        ];

        $this->checkProviderConfiguration($providerConfig);

        return new SwissAlpineClub($providerConfig, []);
    }

    /**
     * Check if all required options have been set.
     */
    private function checkProviderConfiguration(array $providerConfig): void
    {
        foreach ($providerConfig as $key => $value) {
            if (empty($value)) {
                throw new InvalidOAuth2ProviderConfigurationException(sprintf('Please check your oauth2 provider configuration. The key "%s" can not be empty.', $key));
            }
        }
    }
}
