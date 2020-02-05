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
     * InteractiveLogin constructor.
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
     * @param string $username
     * @param string $userClass
     * @param string $providerKey
     * @throws \Exception
     */
    public function login(string $username, string $userClass, string $providerKey): void
    {
        if (!\is_string($username) && (!\is_object($username) || !method_exists($username, '__toString')))
        {
            throw new BadRequestHttpException(
                sprintf('The username "%s" must be a string, "%s" given.', \gettype($username))
            );
        }

        $username = trim($username);

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
        if (!$user instanceof $userClass)
        {
            throw new \Exception(
                'Username does not exists.'
            );
        }

        if ($user instanceof FrontendUser)
        {
            if (null !== ($objMember = MemberModel::findByUsername($user->username)))
            {
                $objMember->disable = '';
                $objMember->login = '1';
                $objMember->locked = 0;
                $objMember->save();
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

        // Now the user is logged in!
        if ($this->logger)
        {
            $this->logger->log(
                LogLevel::INFO,
                sprintf('User "%s" has logged in with openid connect.', $username),
                ['contao' => new ContaoContext(__METHOD__, ContaoContext::ACCESS)]
            );
        }
    }
}
