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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\ErrorMessage;

use Contao\CoreBundle\Framework\ContaoFramework;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
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
    private ?LoggerInterface $logger = null;

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

    /**
     * Map an exception to an error screen.
     */
    public function printErrorMessage($arrMessage): Response
    {
        $request = $this->requestStack->getCurrentRequest();
        $defaults = [
            'statusCode' => '',
            'statusName' => '',
            'template' => '',
            'base' => $request->getBasePath(),
            'language' => $request->getLocale(),
            //'adminEmail' => '&#109;&#97;&#105;&#108;&#116;&#111;&#58;' . $encoded,
            //'exception'  => $event->getException()->getMessage(),
        ];

        $parameters = array_merge($defaults, $arrMessage);

        $view = '@MarkocupicSwissAlpineClubContaoLoginClient/Error/login_error.html.twig';
        $response = new Response($this->twig->render($view, $parameters));
        $response->send();
        exit();
    }

    private function getSession()
    {
        $request = $this->requestStack->getCurrentRequest();
        return $request->getSession();
    }
}
