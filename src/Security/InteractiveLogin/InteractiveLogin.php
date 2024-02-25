<?php

declare(strict_types=1);

/*
 * This file is part of Swiss Alpine Club Contao Login Client Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/swiss-alpine-club-contao-login-client-bundle
 */

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\InteractiveLogin;

use Contao\BackendUser;
use Contao\CoreBundle\ContaoCoreBundle;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Security\User\ContaoUserProvider;
use Contao\FrontendUser;
use Contao\MemberModel;
use Contao\System;
use Contao\User;
use Contao\UserModel;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Event\PreInteractiveLoginEvent;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\User\ContaoUser;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

class InteractiveLogin
{
    public const SECURED_AREA_FRONTEND = 'contao_frontend';
    public const SECURED_AREA_BACKEND = 'contao_backend';

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly RequestStack $requestStack,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    /**
     * @throws \Exception
     */
    public function login(ContaoUser $contaoUser): void
    {
        $session = $this->requestStack->getCurrentRequest()->getSession();

        /** @var MemberModel $memberModelAdapter */
        $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);

        /** @var UserModel $userModelAdapter */
        $userModelAdapter = $this->framework->getAdapter(UserModel::class);

        $firewallName = ContaoCoreBundle::SCOPE_FRONTEND === $contaoUser->getContaoScope() ? static::SECURED_AREA_FRONTEND : static::SECURED_AREA_BACKEND;

        $userIdentifier = $contaoUser->getIdentifier();

        if (empty($userIdentifier) || !\is_string($userIdentifier)) {
            throw new \Exception(sprintf('The username must be a string, "%s" given.', \gettype($userIdentifier)));
        }

        $userIdentifier = trim($userIdentifier);

        // Be sure user exists
        if (!$contaoUser->checkUserExists()) {
            throw new \Exception('Could not find user with user identifier '.$userIdentifier.'.');
        }

        // Check if username is valid
        // Security::MAX_USERNAME_LENGTH = 4096;
        if (\strlen($userIdentifier) > Security::MAX_USERNAME_LENGTH) {
            throw new \Exception('Invalid username.');
        }

        // Load user by identifier (sac member id)
        $userClass = ContaoCoreBundle::SCOPE_FRONTEND === $contaoUser->getContaoScope() ? FrontendUser::class : BackendUser::class;
        $userProvider = new ContaoUserProvider($this->framework, $userClass);

        // Dispatch the PreInteractiveLoginEvent
        $event = new PreInteractiveLoginEvent($userIdentifier, $userClass, $userProvider, $contaoUser->getResourceOwner());
        $this->eventDispatcher->dispatch($event, PreInteractiveLoginEvent::NAME);

        $user = $userProvider->loadUserByIdentifier($userIdentifier);

        $token = new UsernamePasswordToken($user, $firewallName, $user->getRoles());
        $this->tokenStorage->setToken($token);

        // Save the token to the session
        $session->set('_security_'.$firewallName, serialize($token));
        $session->save();

        // Fire the login event manually
        $event = new InteractiveLoginEvent($this->requestStack->getCurrentRequest(), $token);
        $this->eventDispatcher->dispatch($event, 'security.interactive_login');

        if ($user instanceof FrontendUser) {
            if (null !== ($objUser = $memberModelAdapter->findByUsername($user->username))) {
                $objUser->lastLogin = $objUser->currentLogin;
                $objUser->currentLogin = time();
                $objUser->save();
            }
        }

        if ($user instanceof BackendUser) {
            if (null !== ($objUser = $userModelAdapter->findByUsername($user->username))) {
                $objUser->lastLogin = $objUser->currentLogin;
                $objUser->currentLogin = time();
                $objUser->save();
            }
        }

        // Contao user is logged in now!

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
