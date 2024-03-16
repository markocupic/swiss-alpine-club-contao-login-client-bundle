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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\OAuth2\Client;

use Markocupic\SwissAlpineClubContaoLoginClientBundle\OAuth2\Client\Provider\ProviderFactory;
use Symfony\Component\HttpFoundation\Request;

readonly class OAuth2ClientFactory
{
    public function __construct(
        private ProviderFactory $providerFactory,
    ) {
    }

    public function createOAuth2Client(Request $request): OAuth2Client
    {
        return new OAuth2Client($this->providerFactory, $request);
    }
}
