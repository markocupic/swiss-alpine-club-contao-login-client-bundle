<?php

declare(strict_types=1);

/**
 * Swiss Alpine Club (SAC) Contao Login Client Bundle
 * Copyright (c) 2008-2020 Marko Cupic
 * @package swiss-alpine-club-contao-login-client-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/swiss-alpine-club-contao-login-client-bundle
 */

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\ErrorMessage;

use Contao\CoreBundle\Framework\ContaoFramework;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Security;
use Twig\Environment;

class PrintErrorMessage
{
    /**
     * @var bool
     */
    private $prettyErrorScreens;

    /**
     * @var Environment
     */
    private $twig;

    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var Security
     */
    private $security;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(bool $prettyErrorScreens, Environment $twig, ContaoFramework $framework, RequestStack $requestStack, Security $security, LoggerInterface $logger = null)
    {
        $this->prettyErrorScreens = $prettyErrorScreens;
        $this->twig = $twig;
        $this->framework = $framework;
        $this->requestStack = $requestStack;
        $this->security = $security;
        $this->logger = $logger;
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
            'template'   => '',
            'base'       => $request->getBasePath(),
            'language'   => $request->getLocale(),
            //'adminEmail' => '&#109;&#97;&#105;&#108;&#116;&#111;&#58;' . $encoded,
            //'exception'  => $event->getException()->getMessage(),
        ];

        $parameters = array_merge($defaults, $arrMessage);

        $view = '@MarkocupicSwissAlpineClubContaoLoginClient/Error/login_error.html.twig';
        $response = new Response($this->twig->render($view, $parameters));
        $response->send();
        exit();
    }

}
