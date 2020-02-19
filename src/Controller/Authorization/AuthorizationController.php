<?php

declare(strict_types=1);

/**
 * Swiss Alpine Club (SAC) Contao Login Client Bundle
 * Copyright (c) 2008-2020 Marko Cupic
 * @package swiss-alpine-club-contao-login-client-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/swiss-alpine-club-contao-login-client-bundle
 */

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Controller\Authorization;

use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\System;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\InteractiveLogin\InteractiveLogin;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Oidc\Oidc;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\User\RemoteUser;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\User\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class AuthorizationController
 * @package Markocupic\SwissAlpineClubContaoLoginClientBundle\Controller\Authorization
 */
class AuthorizationController extends AbstractController
{

    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var Session
     */
    private $session;

    /**
     * @var RemoteUser
     */
    private $remoteUser;

    /**
     * @var User
     */
    private $user;

    /**
     * @var InteractiveLogin
     */
    private $interactiveLogin;

    /**
     * @var Oidc
     */
    private $oidc;

    /**
     * AuthorizationController constructor.
     * @param ContaoFramework $framework
     * @param Session $session
     * @param RemoteUser $remoteUser
     * @param User $user
     * @param InteractiveLogin $interactiveLogin
     * @param Oidc $oidc
     */
    public function __construct(ContaoFramework $framework, Session $session, RemoteUser $remoteUser, User $user, InteractiveLogin $interactiveLogin, Oidc $oidc)
    {
        $this->framework = $framework;
        $this->session = $session;
        $this->remoteUser = $remoteUser;
        $this->user = $user;
        $this->interactiveLogin = $interactiveLogin;
        $this->oidc = $oidc;

        $this->framework->initialize();
    }

    /**
     * Login frontend user
     * @throws \Markocupic\SwissAlpineClubContaoLoginClientBundle\Exception\AppCheckFailedException
     * @throws \Markocupic\SwissAlpineClubContaoLoginClientBundle\Exception\InvalidRequestTokenException
     * @Route("/ssoauth/frontend", name="swiss_alpine_club_sso_login_frontend", defaults={"_scope" = "frontend", "_token_check" = false})
     */
    public function frontendUserAuthenticationAction()
    {
        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        $contaoScope = 'frontend';

        $bagName = System::getContainer()->getParameter('swiss_alpine_club_contao_login_client.session.attribute_bag_name');

        /** @var Session $session */
        $session = $this->session->getBag($bagName);

        // Set redirect uri
        $this->oidc->setProviderData(['redirectUri' => Config::get('SAC_SSO_LOGIN_REDIRECT_URI_FRONTEND')]);

        // Run the authorisation code flow
        if ($this->oidc->runOpenIdConnectFlow())
        {
            $arrData = $session->get('arrData');

            $this->remoteUser->create($arrData);
            //$this->remoteUser->create($this->remoteUser->getMockUserData(false)); // Should end in an error message

            // Check if uuid/sub is set
            $this->remoteUser->checkHasUuid();

            // Check if user is SAC member
            $this->remoteUser->checkIsSacMember();

            // Check if user is member of an allowed section
            $this->remoteUser->checkIsMemberInAllowedSection();

            // Check has valid email address
            // This test should be always positive,
            // because email is mandatory
            $this->remoteUser->checkHasValidEmail();

            // Initialize user
            $this->user->initialize($this->remoteUser, $contaoScope);

            // Create User if it not exists
            $this->user->createIfNotExists();

            // Check if user exists
            $this->user->checkUserExists();

            // Allow login: set tl_member.disable = ''
            $this->user->enableLogin();

            // Set tl_member.locked=0
            $this->user->unlock();

            // Set tl_member.login='1'
            $this->user->activateLogin();

            // Update user
            $this->user->updateUser();

            // Check if tl_member.disable == '' & tl_member.locked == 0 & tl_member.login == '1'
            $this->user->checkIsLoginAllowed();

            // Log in user
            $this->interactiveLogin->login($this->user);

            $jumpToPath = $session->get('targetPath');
            $session->clear();

            // All ok. User has logged in
            // Let's redirect to target page now
            $controllerAdapter->redirect($jumpToPath);
        }
        else
        {
            $errorPage = $session->get('failurePath');
            $arrError = $session->get('lastOidcError', []);
            $flashBagKey = $systemAdapter->getContainer()->getParameter('swiss_alpine_club_contao_login_client.session.flash_bag_key');
            $this->session->getFlashBag()->add($flashBagKey, $arrError);
            $controllerAdapter->redirect($errorPage);
        }
    }

    /**
     * Login backend user
     * @throws \Markocupic\SwissAlpineClubContaoLoginClientBundle\Exception\AppCheckFailedException
     * @throws \Markocupic\SwissAlpineClubContaoLoginClientBundle\Exception\InvalidRequestTokenException
     * @Route("/ssoauth/backend", name="swiss_alpine_club_sso_login_backend", defaults={"_scope" = "backend", "_token_check" = false})
     */
    public function backendUserAuthenticationAction()
    {
        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        $contaoScope = 'backend';

        $bagName = System::getContainer()->getParameter('swiss_alpine_club_contao_login_client.session.attribute_bag_name');

        /** @var Session $session */
        $session = $this->session->getBag($bagName);

        // Set redirect uri
        $this->oidc->setProviderData(['redirectUri' => Config::get('SAC_SSO_LOGIN_REDIRECT_URI_BACKEND')]);

        // Run the authorisation code flow
        if ($this->oidc->runOpenIdConnectFlow())
        {
            $arrData = $session->get('arrData');

            $this->remoteUser->create($arrData);

            // Check if uuid/sub is set
            $this->remoteUser->checkHasUuid();

            // Check if user is SAC member
            $this->remoteUser->checkIsSacMember();

            // Check if user is member of an allowed section
            $this->remoteUser->checkIsMemberInAllowedSection();

            // Check has valid email address
            // This test should be always positive,
            // because email is mandatory
            $this->remoteUser->checkHasValidEmail();

            // Initialize user
            $this->user->initialize($this->remoteUser, $contaoScope);

            // Create User if it not exists is yet not allowed!
            //$this->user->createIfNotExists();

            // Check if user exists
            $this->user->checkUserExists();

            // Allow login: set tl_user.disable = ''
            //$this->user->enableLogin();

            // Set tl_user.locked=0
            $this->user->unlock();

            // Update user
            $this->user->updateUser();

            // Check if tl_user.disable == '' & tl_user.locked == 0
            $this->user->checkIsLoginAllowed();

            // Log in user
            $this->interactiveLogin->login($this->user);

            $jumpToPath = $session->get('targetPath');
            $session->clear();

            // All ok. User has logged in
            // Let's redirect to target page now
            $controllerAdapter->redirect($jumpToPath);
        }
        else
        {
            $errorPage = $session->get('failurePath');
            $arrError = $session->get('lastOidcError', []);
            $flashBagKey = $systemAdapter->getContainer()->getParameter('swiss_alpine_club_contao_login_client.session.flash_bag_key');
            $this->session->getFlashBag()->add($flashBagKey, $arrError);
            $controllerAdapter->redirect($errorPage);
        }
    }

    /**
     * @return JsonResponse
     * @Route("/ssoauth/send_logout_endpoint", name="swiss_alpine_club_sso_login_send_logout_endpoint")
     */
    public function sendLogoutEndpointAction(): JsonResponse
    {
        $data = [
            'success'             => 'true',
            'logout_endpoint_url' => Config::get('SAC_SSO_LOGIN_URL_LOGOUT'),
        ];

        return new JsonResponse($data);
    }

}
