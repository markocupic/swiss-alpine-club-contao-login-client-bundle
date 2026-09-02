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
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Reject Contao's username/password backend login server side, if
 * sac_oauth2_client.backend.disable_contao_login is enabled.
 *
 * Removing the login form from the template is not enough, because the firewall
 * would still happily accept a hand crafted POST request.
 *
 * The listener has to run before the Symfony firewall listener (priority 8) and
 * after the router listener (priority 32), which sets the "_scope" attribute.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 9)]
readonly class DisableContaoBackendLoginListener
{
    public function __construct(
        private ScopeMatcher $scopeMatcher,
        #[Autowire('%sac_oauth2_client.backend.disable_contao_login%')]
        private bool $disableContaoLogin,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$this->disableContaoLogin || !$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!$this->scopeMatcher->isBackendRequest($request) || !$request->isMethod('POST')) {
            return;
        }

        $formSubmit = $request->request->get('FORM_SUBMIT');

        // Same condition as in
        // Contao\CoreBundle\Security\Authenticator\ContaoLoginAuthenticator::supports()
        if (!\is_string($formSubmit) || !preg_match('/^tl_login(_\d+)?$/', $formSubmit)) {
            return;
        }

        throw new AccessDeniedHttpException('The Contao backend login is disabled. Please use the SAC SSO login instead.');
    }
}
