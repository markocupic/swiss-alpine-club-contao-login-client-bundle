<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\EventListener;

use Contao\Config;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Exception\InvalidRequestTokenException;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\PageError404;
use Contao\StringUtil;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\AcceptHeader;
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

class PrettyErrorScreenListener
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
     * @var Security
     */
    private $security;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(bool $prettyErrorScreens, Environment $twig, ContaoFramework $framework, Security $security, LoggerInterface $logger = null)
    {
        $this->prettyErrorScreens = $prettyErrorScreens;
        $this->twig = $twig;
        $this->framework = $framework;
        $this->security = $security;
        $this->logger = $logger;
    }

    /**
     * Map an exception to an error screen.
     */
    public function onKernelException(GetResponseForExceptionEvent $event): void
    {
        if (!$event->isMasterRequest())
        {
            return;
        }

        $request = $event->getRequest();

        if ('html' !== $request->getRequestFormat())
        {
            return;
        }

        if (!AcceptHeader::fromString($request->headers->get('Accept'))->has('text/html'))
        {
            return;
        }

        $this->handleException($event);
    }

    private function handleException(GetResponseForExceptionEvent $event): void
    {
        $exception = $event->getException();
        $statusCode = $this->getStatusCodeForException($exception);
        $template = null;

        if ($exception instanceof AppCheckFailedException)
        {
            $template = 'app_check_failed';
        }
        elseif ($exception instanceof InvalidRequestTokenException)
        {
            $template = 'invalid_request_token';
        }

        // Look for a template
        if (null !== $template)
        {
            //$this->logException($exception);
            $this->renderTemplate($template ?: 'error', $statusCode, $event);
        }
    }

    private function renderTemplate(string $template, int $statusCode, GetResponseForExceptionEvent $event): void
    {
        if (!$this->prettyErrorScreens)
        {
            return;
        }

        $view = '@MarkocupicSwissAlpineClubContaoLoginClient/Error/' . $template . '.html.twig';
        $parameters = $this->getTemplateParameters($view, $statusCode, $event);
        try
        {
            $event->setResponse(new Response($this->twig->render($view, $parameters), $statusCode));
        } catch (Error $e)
        {
            $event->setResponse(new Response($this->twig->render('@ContaoCore/Error/error.html.twig'), 500));
        }
    }

    /**
     * @return array<string,string|int>
     */
    private function getTemplateParameters(string $view, int $statusCode, GetResponseForExceptionEvent $event): array
    {
        /** @var Config $config */
        $config = $this->framework->getAdapter(Config::class);
        $encoded = StringUtil::encodeEmail($config->get('adminEmail'));

        return [
            'statusCode' => $statusCode,
            'statusName' => Response::$statusTexts[$statusCode],
            'template'   => $view,
            'base'       => $event->getRequest()->getBasePath(),
            'language'   => $event->getRequest()->getLocale(),
            'adminEmail' => '&#109;&#97;&#105;&#108;&#116;&#111;&#58;' . $encoded,
            'exception'  => $event->getException()->getMessage(),
        ];
    }

    private function logException(\Exception $exception): void
    {
        if (Kernel::VERSION_ID >= 40100 || null === $this->logger || !$this->isLoggable($exception))
        {
            return;
        }

        $this->logger->critical('An exception occurred.', ['exception' => $exception]);
    }

    private function isLoggable(\Exception $exception): bool
    {
        do
        {
            if ($exception instanceof InvalidRequestTokenException)
            {
                return false;
            }
        } while (null !== ($exception = $exception->getPrevious()));

        return true;
    }

    private function getStatusCodeForException(\Exception $exception): int
    {
        if ($exception instanceof HttpException)
        {
            return (int) $exception->getStatusCode();
        }

        return 500;
    }
}
