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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\EventSubscriber;

use Markocupic\SwissAlpineClubContaoLoginClientBundle\Event\OAuth2SuccessEvent;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\Authentication\Authenticator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class Oauth2SuccessSubscriber implements EventSubscriberInterface
{
    private const PRIORITY = 1000;

    public function __construct(
        private readonly Authenticator $authenticator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [OAuth2SuccessEvent::NAME => ['onOAuth2Success', self::PRIORITY]];
    }

    /**
     * @throws \Exception
     */
    public function onOAuth2Success(OAuth2SuccessEvent $event): void
    {
        $request = $event->getRequest();
        $oAuth2Client = $event->getOAuth2Client();

        // Get the user from resource owner and login to contao firewall
        $this->authenticator->authenticateContaoUser($request, $oAuth2Client);
    }
}
