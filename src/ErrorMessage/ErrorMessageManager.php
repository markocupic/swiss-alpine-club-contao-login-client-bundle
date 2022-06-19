<?php

declare(strict_types=1);

/*
 * This file is part of Swiss Alpine Club Contao Login Client Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/swiss-alpine-club-contao-login-client-bundle
 */

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\ErrorMessage;

use Contao\CoreBundle\Framework\ContaoFramework;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Security;
use Twig\Environment;

class ErrorMessageManager
{
    private bool $prettyErrorScreens;
    private string $flashBagKey;
    private Environment $twig;
    private ContaoFramework $framework;
    private RequestStack $requestStack;
    private Security $security;
    private LoggerInterface|null $logger = null;

    public function __construct(bool $prettyErrorScreens, string $flashBagKey, Environment $twig, ContaoFramework $framework, RequestStack $requestStack, Security $security, LoggerInterface $logger = null)
    {
        $this->prettyErrorScreens = $prettyErrorScreens;
        $this->flashBagKey = $flashBagKey;
        $this->twig = $twig;
        $this->framework = $framework;
        $this->requestStack = $requestStack;
        $this->security = $security;
        $this->logger = $logger;
    }

    /**
     * Add an error message to the session flash bag.
     */
    public function add2Flash(ErrorMessage $objErrorMsg): void
    {
        $this->getSession()->getFlashBag()->add($this->flashBagKey, $objErrorMsg->get());
    }

    private function getSession()
    {
        $request = $this->requestStack->getCurrentRequest();

        return $request->getSession();
    }
}
