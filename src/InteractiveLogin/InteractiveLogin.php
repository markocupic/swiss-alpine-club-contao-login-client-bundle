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
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Controller\Authentication\AuthenticationController;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\User\RemoteUser;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\User\User as OidcUser;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

/**
 * Class InteractiveLogin.
 */
class InteractiveLogin
{
    /**
     * @var string provider key for contao frontend secured area
     */
    public const SECURED_AREA_FRONTEND = 'contao_frontend';

    /**
     * @var string provider key for contao backend secured area
     */
    public const SECURED_AREA_BACKEND = 'contao_backend';

    private ContaoFramework $framework;
    private UserChecker $userChecker;
    private TokenStorageInterface $tokenStorage;
    private EventDispatcherInterface $eventDispatcher;
    private RequestStack $requestStack;
    private ?LoggerInterface $logger = null;

    /**
     * InteractiveLogin constructor.
     */
    public function __construct(ContaoFramework $framework, UserChecker $userChecker, TokenStorageInterface $tokenStorage, EventDispatcherInterface $eventDispatcher, RequestStack $requestStack, ?LoggerInterface $logger = null)
    {
        $this->framework = $framework;
        $this->userChecker = $userChecker;
        $this->tokenStorage = $tokenStorage;
        $this->eventDispatcher = $eventDispatcher;
        $this->requestStack = $requestStack;
        $this->logger = $logger;
    }

    /**
     * Service method call.
     */
    public function initializeFramework(): void
    {
        // Initialize Contao framework
        $this->framework->initialize();
    }

    /**
     * @throws \Exception
     */
    public function login(OidcUser $oidcUser): void
    {
        /** @var MemberModel $memberModelAdapter */
        $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);

        /** @var UserModel $userModelAdapter */
        $userModelAdapter = $this->framework->getAdapter(UserModel::class);

        $providerKey = AuthenticationController::CONTAO_SCOPE_FRONTEND === $oidcUser->getContaoScope() ? static::SECURED_AREA_FRONTEND : static::SECURED_AREA_BACKEND;

        $username = $oidcUser->getModel()->username;

        if (!\is_string($username) && (!\is_object($username) || !method_exists($username, '__toString'))) {
            throw new \Exception(sprintf('The username "%s" must be a string, "%s" given.', \gettype($username)));
        }

        $username = trim((string) $username);

        // Be sure user exists
        if (!$oidcUser->checkUserExists()) {
            throw new \Exception('Could not found user with username '.$username.'.');
        }

        // Check if username is valid
        // Security::MAX_USERNAME_LENGTH = 4096;
        if (\strlen($username) > Security::MAX_USERNAME_LENGTH) {
            throw new \Exception('Invalid username.');
        }

        $userClass = AuthenticationController::CONTAO_SCOPE_FRONTEND === $oidcUser->getContaoScope() ? FrontendUser::class : BackendUser::class;

        $session = $this->requestStack->getCurrentRequest()->getSession();

        // Retrieve user by its username
        $userProvider = new ContaoUserProvider($this->framework, $session, $userClass, $this->logger);

        $user = $userProvider->loadUserByUsername($username);

        $token = new UsernamePasswordToken($user, null, $providerKey, $user->getRoles());
        $this->tokenStorage->setToken($token);

        // Save the token to the session
        $session->set('_security_'.$providerKey, serialize($token));
        $session->save();

        // Fire the login event manually
        $event = new InteractiveLoginEvent($this->requestStack->getCurrentRequest(), $token);
        $this->eventDispatcher->dispatch($event, 'security.interactive_login', );

        /** @var RemoteUser $remoteUser */
        $remoteUser = $oidcUser->remoteUser;

        if ($user instanceof FrontendUser) {
            if (null !== ($objUser = $memberModelAdapter->findByUsername($user->username))) {
                $objUser->lastLogin = $objUser->currentLogin;
                $objUser->currentLogin = time();
                $objUser->save();
            }
            $logTxt = sprintf('Frontend User "%s" [%s] has logged in with SAC OPENID CONNECT APP.', $remoteUser->get('name'), $remoteUser->get('contact_number'));
        }

        if ($user instanceof BackendUser) {
            if (null !== ($objUser = $userModelAdapter->findByUsername($user->username))) {
                $objUser->lastLogin = $objUser->currentLogin;
                $objUser->currentLogin = time();
                $objUser->save();
            }
            $logTxt = sprintf('Backend User "%s" [%s] has logged in with SAC OPENID CONNECT APP.', $remoteUser->get('name'), $remoteUser->get('contact_number'));
        }

        // Now the user is logged in!
        if ($this->logger && isset($logTxt)) {
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
     * Trigger the Contao post login hook.
     */
    private function triggerPostLoginHook(User $user): void
    {
        $this->framework->initialize();

        if (empty($GLOBALS['TL_HOOKS']['postLogin']) || !\is_array($GLOBALS['TL_HOOKS']['postLogin'])) {
            return;
        }

        @trigger_error('Using the "postLogin" hook has been deprecated and will no longer work in Contao 5.0.', E_USER_DEPRECATED);

        /** @var System $system */
        $systemAdapter = $this->framework->getAdapter(System::class);

        foreach ($GLOBALS['TL_HOOKS']['postLogin'] as $callback) {
            $systemAdapter->importStatic($callback[0])->{$callback[1]}($user);
        }
    }
}
