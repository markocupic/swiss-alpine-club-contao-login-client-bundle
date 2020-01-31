<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\ErrorMessage;

use Contao\Config;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Exception\InvalidRequestTokenException;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\PageError404;
use Contao\StringUtil;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\AcceptHeader;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Symfony\Component\Security\Core\Security;
use Twig\Environment;
use Twig\Error\Error;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Exception\AppCheckFailedException;

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
        $defaults =[
            'statusCode' => $statusCode,
            'statusName' => Response::$statusTexts[$statusCode],
            'template'   => $view,
            'base'       => $request->getBasePath(),
            'language'   => $request->getLocale(),
            //'adminEmail' => '&#109;&#97;&#105;&#108;&#116;&#111;&#58;' . $encoded,
            //'exception'  => $event->getException()->getMessage(),
        ];

        $parameters = array_merge($defaults,$arrMessage);

        $view = '@MarkocupicSwissAlpineClubContaoLoginClient/Error/login_error.html.twig';
        $response = new Response($this->twig->render($view, $parameters));
        $response->send();
        exit();

    }

}
