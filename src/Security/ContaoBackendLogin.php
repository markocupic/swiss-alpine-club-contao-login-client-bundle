<?php

/**
 * Swiss Alpine Club Login Client Bundle
 * OpenId Connect Login via https://sac-cas.ch for Contao Frontend and Backend
 *
 * @package Markocupic\SwissAlpineClubContaoLoginClientBundle
 * @author    Marko Cupic, Oberkirch
 * @license   MIT
 * @copyright 2020 Marko Cupic
 */
namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Security;

use Markocupic\SwissAlpineClubContaoLoginClientBundle\User\RemoteUserInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Contao\CoreBundle\Security\User\ContaoUserProvider;
use Contao\CoreBundle\Framework\ContaoFrameworkInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

class ContaoBackendLogin
{
    protected const SECURED_AREA = 'contao_backend';
    protected $contaoUserProvider;
    protected $session;
    protected $tokenStorage;
    protected $authenticationManager;
    protected $eventDispatcher;
    protected $requestStack;

    /**
     * ContaoBackendLogin constructor.
     * @param ContaoUserProvider $contaoUserProvider
     * @param SessionInterface $session
     * @param ContaoFramework $contaoFramework
     * @param TokenStorageInterface $tokenStorage
     * @param AuthenticationManagerInterface $authenticationManager
     * @param EventDispatcherInterface $eventDispatcher
     * @param RequestStack $requestStack
     */
    public function __construct(ContaoUserProvider $contaoUserProvider, SessionInterface $session, ContaoFramework $contaoFramework, TokenStorageInterface $tokenStorage, AuthenticationManagerInterface $authenticationManager, EventDispatcherInterface $eventDispatcher, RequestStack $requestStack)
    {
        $this->contaoUserProvider = $contaoUserProvider;
        $this->session = $session;
        $this->tokenStorage = $tokenStorage;
        $this->authenticationManager = $authenticationManager;
        $this->eventDispatcher = $eventDispatcher;
        $this->requestStack = $requestStack;

        if (!$contaoFramework->isInitialized())
        {
            $contaoFramework->initialize();
        }
    }

    /**
     * @param RemoteUserInterface $remoteUser
     */
    public function login(RemoteUserInterface $remoteUser)
    {
        $user = $this->contaoUserProvider->loadUserByUsername($remoteUser->getUsername());

        $token = new UsernamePasswordToken($user, null, self::SECURED_AREA, $user->getRoles());
        $this->tokenStorage->setToken($token);

        $this->session->set('_security_' . self::SECURED_AREA, serialize($token));
        $this->session->save();

        $event = new InteractiveLoginEvent($this->requestStack->getCurrentRequest(), $token);
        $this->eventDispatcher->dispatch('security.interactive_login', $event);
    }
}
