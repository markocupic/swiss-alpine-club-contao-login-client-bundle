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

use Contao\CoreBundle\Security\User\ContaoUserProvider;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\OAuth\OAuthUser;
use Symfony\Contracts\EventDispatcher\Event;

final class PreInteractiveLoginEvent extends Event
{
    public const NAME = 'sac_oauth2_client.pre_interactive_login';

    public function __construct(
        private readonly string $userIdentifier,
        private readonly string $userClass,
        private readonly ContaoUserProvider $userProvider,
        private readonly OAuthUser $resourceOwner,
    ) {
    }

    public function getUserIdentifier(): string
    {
        return $this->userIdentifier;
    }

    public function getUserClass(): string
    {
        return $this->userClass;
    }

    public function getUserProvider(): ContaoUserProvider
    {
        return $this->userProvider;
    }

    public function getResourceOwner(): OAuthUser
    {
        return $this->resourceOwner;
    }
}
