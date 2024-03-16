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
        private array $providerConfig,
    ) {
    }

    public function createProvider(Request $request): AbstractProvider
    {
        $redirectRoute = match ($request->attributes->get('_scope')) {
            ContaoCoreBundle::SCOPE_BACKEND => $this->providerConfig['redirectRouteBackend'],
            ContaoCoreBundle::SCOPE_FRONTEND => $this->providerConfig['redirectRouteFrontend'],
            default => null,
        };

        if (null === $redirectRoute) {
            throw new \Exception('Scope must be "backend" or "frontend".');
        }

        $providerConfig = [
            // The client ID assigned to you by the provider
            'clientId' => $this->providerConfig['clientId'] ?? '',
            // The client password assigned to you by the provider
            'clientSecret' => $this->providerConfig['clientSecret'] ?? '',
            // Absolute url to the "authorize" endpoint
            'urlAuthorize' => $this->providerConfig['urlAuthorize'] ?? '',
            // Absolute url to the "get access token" endpoint
            'urlAccessToken' => $this->providerConfig['urlAccessToken'] ?? '',
            // Absolute url to the "get resource owner details endpoint"
            'urlResourceOwnerDetails' => $this->providerConfig['urlResourceOwnerDetails'] ?? '',
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
