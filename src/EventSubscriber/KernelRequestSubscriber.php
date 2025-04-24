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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\EventSubscriber;

use Contao\CoreBundle\Routing\ScopeMatcher;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\Asset\Packages;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

readonly class KernelRequestSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ScopeMatcher $scopeMatcher,
        private UrlGeneratorInterface $router,
        private Packages $packages,
    ) {
    }

    #[ArrayShape([KernelEvents::REQUEST => 'string'])]
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => 'loadAssets'];
    }

    public function loadAssets(RequestEvent $e): void
    {
        $request = $e->getRequest();

        $GLOBALS['TL_JAVASCRIPT'][] = $this->packages->getUrl('js/ids-kill-session.js', 'markocupic_swiss_alpine_club_contao_login_client');
        $GLOBALS['TL_JAVASCRIPT'][] = $this->packages->getUrl('js/login-button-animation.js', 'markocupic_swiss_alpine_club_contao_login_client');

        if ($this->scopeMatcher->isBackendRequest($request)) {
            if (str_contains($request->getUri(), $this->router->generate('contao_backend_login'))) {
                $GLOBALS['TL_CSS'][] = $this->packages->getUrl('styles/backend.css', 'markocupic_swiss_alpine_club_contao_login_client');
                $GLOBALS['TL_CSS'][] = $this->packages->getUrl('styles/sac_login_button.css', 'markocupic_swiss_alpine_club_contao_login_client');
            }
        }
    }
}
