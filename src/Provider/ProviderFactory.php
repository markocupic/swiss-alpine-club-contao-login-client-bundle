<?php

declare(strict_types=1);

/*
 * This file is part of Swiss Alpine Club Contao Login Client Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/swiss-alpine-club-contao-login-client-bundle
 */

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Provider;

use Contao\CoreBundle\ContaoCoreBundle;
use League\OAuth2\Client\Provider\AbstractProvider;

class ProviderFactory
{
    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $urlAuthorize,
        private readonly string $urlAccessToken,
        private readonly string $urlResourceOwnerDetails,
        private readonly string $redirectUriBackend,
        private readonly string $redirectUriFrontend,
    ) {
    }

    public function createProvider(string $contaoScope): AbstractProvider
    {
        $redirectUri = ContaoCoreBundle::SCOPE_BACKEND === $contaoScope ? $this->redirectUriBackend : $this->redirectUriFrontend;

        $providerConfig = [
            // The client ID assigned to you by the provider
            'clientId' => $this->clientId,
            // The client password assigned to you by the provider
            'clientSecret' => $this->clientSecret,
            // Absolute callback url to your system (must be registered by service provider.)
            'urlAuthorize' => $this->urlAuthorize,
            'urlAccessToken' => $this->urlAccessToken,
            'urlResourceOwnerDetails' => $this->urlResourceOwnerDetails,
            'redirectUri' => $redirectUri,
            'scopes' => ['openid'],
        ];

        return new SwissAlpineClub($providerConfig, []);
    }
}
