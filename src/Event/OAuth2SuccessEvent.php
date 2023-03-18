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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Event;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Token\AccessToken;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

class OAuth2SuccessEvent extends Event
{
    public const NAME = 'sac_oauth2_client.oauth2_success';

    public function __construct(
        private readonly Request $request,
        private readonly AbstractProvider $provider,
        private readonly AccessToken $accessToken,
        private readonly string $scope,
    ) {
    }

    public function getAccessToken(): AccessToken
    {
        return $this->accessToken;
    }

    public function getProvider(): AbstractProvider
    {
        return $this->provider;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getScope(): string // backend or frontend
    {
        return $this->scope;
    }
}
