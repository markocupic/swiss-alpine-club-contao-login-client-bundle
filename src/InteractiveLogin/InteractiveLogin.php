<?php

declare(strict_types=1);

/**
 * Swiss Alpine Club (SAC) Contao Login Client Bundle
 * Copyright (c) 2008-2020 Marko Cupic
 * @package swiss-alpine-club-contao-login-client-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/swiss-alpine-club-contao-login-client-bundle
 */

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\InteractiveLogin;

use Contao\BackendUser;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\CoreBundle\Security\User\ContaoUserProvider;
use Contao\CoreBundle\Security\User\UserChecker;
use Contao\FrontendUser;
use Contao\MemberModel;
use Contao\System;
use Contao\User;
use Contao\UserModel;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\User\RemoteUser;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\User\User as UserHelper;

/**
 * Class InteractiveLogin
 * @package Markocupic\SwissAlpineClubContaoLoginClientBundle\InteractiveLogin
 */
class InteractiveLogin
{

    /** @var string provider key for contao frontend secured area */
    public const SECURED_AREA_FRONTEND = 'contao_frontend';

    /** @var string provider key for contao backend secured area */
    public const SECURED_AREA_BACKEND = 'contao_backend';

    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var UserHelper
     */
    private $user;

    /**
     * @var UserChecker
     */
    private $userChecker;

    /**
     * @var Session
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
     * InteractiveLogin constructor.
     * @param ContaoFramework $framework
     * @param UserHelper $user
     * @param UserChecker $userChecker
     * @param Session $session
     * @param TokenStorageInterface $tokenStorage
     * @param EventDispatcherInterface $eventDispatcher
     * @param RequestStack $requestStack
     * @param null|LoggerInterface $logger
     */
    public function __construct(ContaoFramework $framework, UserHelper $user, UserChecker $userChecker, Session $session, TokenStorageInterface $tokenStorage, EventDispatcherInterface $eventDispatcher, RequestStack $requestStack, ?LoggerInterface $logger = null)
    {
        $this->framework = $framework;
        $this->user = $user;
        $this->userChecker = $userChecker;
        $this->session = $session;
        $this->tokenStorage = $tokenStorage;
        $this->eventDispatcher = $eventDispatcher;
        $this->requestStack = $requestStack;
        $this->logger = $logger;

        $this->framework->initialize();
    }

    /**
     * @param RemoteUser $remoteUser
     * @param string $userClass
     * @throws \Exception
     */
    public function login(RemoteUser $remoteUser, string $userClass): void
    {
        $providerKey = $userClass === FrontendUser::class ? static::SECURED_AREA_FRONTEND : static::SECURED_AREA_BACKEND;
        $username = $remoteUser->get('contao_username');

        if (!\is_string($username) && (!\is_object($username) || !method_exists($username, '__toString')))
        {
            throw new BadRequestHttpException(
                sprintf('The username "%s" must be a string, "%s" given.', \gettype($username))
            );
        }

        $username = trim($username);

        // Be sure user exists
        $this->user->checkUserExists($remoteUser, $userClass);

        // Check if username is valid
        // Security::MAX_USERNAME_LENGTH = 4096;
        if (\strlen($username) > Security::MAX_USERNAME_LENGTH)
        {
            throw new \Exception(
                'Invalid username.'
            );
        }

        // Retrieve user by its username
        $userProvider = new ContaoUserProvider($this->framework, $this->session, $userClass, $this->logger);

        $user = $userProvider->loadUserByUsername($username);

        if ($user instanceof FrontendUser)
        {
            if (null !== ($objUser = MemberModel::findByUsername($user->username)))
            {
                $objUser->disable = '';
                $objUser->login = '1';
                $objUser->locked = 0;
                $objUser->save();
            }
        }

        if ($user instanceof BackendUser)
        {
            if (null !== ($objUser = UserModel::findByUsername($user->username)))
            {
                $objUser->locked = 0;
                $objUser->save();
            }
        }

        // Refresh user
        $user = $userProvider->refreshUser($user);

        // Check if account is locked
        // Check if account is disabled
        // Check if Login is allowed
        // Check if account is active
        $this->userChecker->checkPreAuth($user);

        $token = new UsernamePasswordToken($user, null, $providerKey, $user->getRoles());
        $this->tokenStorage->setToken($token);

        // Save the token to the session
        $this->session->set('_security_' . $providerKey, serialize($token));
        $this->session->save();

        // Fire the login event manually
        $event = new InteractiveLoginEvent($this->requestStack->getCurrentRequest(), $token);
        $this->eventDispatcher->dispatch('security.interactive_login', $event);

        if ($user instanceof FrontendUser)
        {
            if (null !== ($objUser = MemberModel::findByUsername($user->username)))
            {
                $objUser->lastLogin = time();
                $objUser->currentLogin = time();
                $objUser->save();
            }
            $logTxt = sprintf('Frontend User "%s" [%s] has logged in with SAC OPENID CONNECT APP.', $remoteUser->get('name'), $remoteUser->get('contact_number'));
        }

        if ($user instanceof BackendUser)
        {
            if (null !== ($objUser = UserModel::findByUsername($user->username)))
            {
                $objUser->lastLogin = time();
                $objUser->currentLogin = time();
                $objUser->save();
            }
            $logTxt = sprintf('Backend User "%s" [%s] has logged in with SAC OPENID CONNECT APP.', $remoteUser->get('name'), $remoteUser->get('contact_number'));
        }

        // Now the user is logged in!
        if ($this->logger && isset($logTxt))
        {
            $this->logger->log(
                LogLevel::INFO,
                $logTxt,
                ['contao' => new ContaoContext(__METHOD__, ContaoContext::ACCESS)]
            );
        }

        // Trigger the Contao post login hook
        $this->triggerPostLoginHook($user);
    }

    /**
     * Trigger the Contao post login hook
     * @param User $user
     */
    private function triggerPostLoginHook(User $user): void
    {
        $this->framework->initialize();

        if (empty($GLOBALS['TL_HOOKS']['postLogin']) || !\is_array($GLOBALS['TL_HOOKS']['postLogin']))
        {
            return;
        }

        @trigger_error('Using the "postLogin" hook has been deprecated and will no longer work in Contao 5.0.', E_USER_DEPRECATED);

        /** @var System $system */
        $system = $this->framework->getAdapter(System::class);

        foreach ($GLOBALS['TL_HOOKS']['postLogin'] as $callback)
        {
            $system->importStatic($callback[0])->{$callback[1]}($user);
        }
    }
}
