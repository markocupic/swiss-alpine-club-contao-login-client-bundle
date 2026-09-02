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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\EventListener;

use Contao\CoreBundle\Routing\ScopeMatcher;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Event\ParseAccessTokenEvent;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Session\IdTokenSessionKeys;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Keep the id_token of the token response, so it can be sent as the
 * id_token_hint when the user logs out at the identity provider.
 */
#[AsEventListener]
readonly class ParseAccessTokenListener
{
    public function __construct(
        private RequestStack $requestStack,
        private ScopeMatcher $scopeMatcher,
    ) {
    }

    public function __invoke(ParseAccessTokenEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request || !$request->hasSession()) {
            return;
        }

        $idToken = $event->getResponse()['id_token'] ?? null;

        // The id_token is optional, e.g. if the "openid" scope has not been requested.
        if (!\is_string($idToken) || '' === $idToken) {
            return;
        }

        $request->getSession()->set(
            IdTokenSessionKeys::forScope($this->scopeMatcher->isBackendRequest($request)),
            $idToken,
        );
    }
}
