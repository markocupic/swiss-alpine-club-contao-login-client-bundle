<?php

declare(strict_types=1);

/*
 * This file is part of Swiss Alpine Club Contao Login Client Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/swiss-alpine-club-contao-login-client-bundle
 */

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\EventSubscriber;

use Contao\CoreBundle\Routing\ScopeMatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class KernelRequestSubscriber implements EventSubscriberInterface
{
    private ScopeMatcher $scopeMatcher;

    public function __construct(ScopeMatcher $scopeMatcher)
    {
        $this->scopeMatcher = $scopeMatcher;
    }

    public static function getSubscribedEvents()
    {
        return [KernelEvents::REQUEST => 'onKernelRequest'];
    }

    public function onKernelRequest(RequestEvent $e): void
    {
        $request = $e->getRequest();

        $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/markocupicswissalpineclubcontaologinclient/js/ids-kill-session.js|static';

        if ($this->scopeMatcher->isBackendRequest($request)) {
            if (false !== strpos($request->getUri(), '/contao/login')) {
                $GLOBALS['TL_CSS'][] = 'bundles/markocupicswissalpineclubcontaologinclient/css/sac_login_button.min.css|static';
                $GLOBALS['TL_CSS'][] = 'bundles/markocupicswissalpineclubcontaologinclient/css/backend.min.css|static';
            }
        }
    }
}
