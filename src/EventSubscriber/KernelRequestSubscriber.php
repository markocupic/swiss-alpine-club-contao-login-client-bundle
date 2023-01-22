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

use Contao\CoreBundle\Routing\ScopeMatcher;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class KernelRequestSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ScopeMatcher $scopeMatcher,
        private readonly UrlGeneratorInterface $router,
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

        $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/markocupicswissalpineclubcontaologinclient/js/ids-kill-session.min.js|static';

        if ($this->scopeMatcher->isBackendRequest($request)) {
            if (str_contains($request->getUri(), $this->router->generate('contao_backend_login'))) {
                $GLOBALS['TL_CSS'][] = 'bundles/markocupicswissalpineclubcontaologinclient/css/sac_login_button.min.css|static';
                $GLOBALS['TL_CSS'][] = 'bundles/markocupicswissalpineclubcontaologinclient/css/backend.min.css';
            }
        }
    }
}
