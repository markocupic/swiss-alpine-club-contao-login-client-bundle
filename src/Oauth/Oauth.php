<?php

declare(strict_types=1);

/**
 * Swiss Alpine Club (SAC) Contao Login Client Bundle
 * Copyright (c) 2008-2020 Marko Cupic
 * @package swiss-alpine-club-contao-login-client-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/swiss-alpine-club-contao-login-client-bundle
 */

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Oauth;

use Contao\BackendUser;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\CoreBundle\Security\User\ContaoUserProvider;
use Contao\CoreBundle\Security\User\UserChecker;
use Contao\FrontendUser;
use Contao\MemberModel;
use Contao\UserModel;
use Psr\Log\LogLevel;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

/**
 * Class Oauth
 * @package Markocupic\SwissAlpineClubContaoLoginClientBundle\Oauth
 */
class Oauth
{

    /** @var string provider key for contao frontend secured area */
    public const ERROR_SESSION_FLASHBAG_KEY = 'swiss_alpine_club_contao_login_client_err_session_flashbag';

    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var UserChecker
     */
    private $userChecker;

    /**
     * @var SessionInterface
     */
    private $session;

    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var LoggerInterface|null
     */
    private $logger;

    /**
     * Authentication constructor.
     * @param ContaoFramework $framework
     * @param UserChecker $userChecker
     * @param SessionInterface $session
     * @param TokenStorageInterface $tokenStorage
     * @param EventDispatcherInterface $eventDispatcher
     * @param RequestStack $requestStack
     * @param null|LoggerInterface $logger
     */
    public function __construct(ContaoFramework $framework, UserChecker $userChecker, SessionInterface $session, TokenStorageInterface $tokenStorage, EventDispatcherInterface $eventDispatcher, RequestStack $requestStack, ?LoggerInterface $logger = null)
    {
        $this->framework = $framework;
        $this->userChecker = $userChecker;
        $this->session = $session;
        $this->tokenStorage = $tokenStorage;
        $this->eventDispatcher = $eventDispatcher;
        $this->requestStack = $requestStack;
        $this->logger = $logger;

        $this->framework->initialize();
    }

    /**
     * @return bool
     */
    public function hasFlashBagMessage(): bool
    {
        if ($this->session->isStarted())
        {
            if ($this->session->getFlashBag()->has(static::ERROR_SESSION_FLASHBAG_KEY))
            {
                return true;
            }
        }
        return false;
    }

    /**
     * @param null $index
     * @return array
     */
    public function getFlashBagMessage($index = null): array
    {
        if ($this->session->isStarted())
        {
            if ($this->hasFlashBagMessage())
            {
                $arrMessages = $this->session->getFlashBag()->get(static::ERROR_SESSION_FLASHBAG_KEY);
                if (null === $index)
                {
                    return $arrMessages;
                }

                if (isset($arrMessages[$index]))
                {
                    return $arrMessages[$index];
                }
            }
        }
        return [];
    }

    /**
     * @param $arrMsg
     */
    public function addFlashBagMessage(array $arrMsg): void
    {
        if ($this->session->isStarted())
        {
            $flashBag = $this->session->getFlashBag();
            $flashBag->add(static::ERROR_SESSION_FLASHBAG_KEY, $arrMsg);
        }
    }
}
