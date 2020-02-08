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
use Contao\FrontendUser;
use Contao\System;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\InteractiveLogin\InteractiveLogin;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Oidc\Oidc;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\User\RemoteUser;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\User\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
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
     * @param SessionInterface $session
     * @param RemoteUser $remoteUser
     * @param User $user
     * @param InteractiveLogin $interactiveLogin
     * @param Oidc $oidc
     */
    public function __construct(ContaoFramework $framework, SessionInterface $session, RemoteUser $remoteUser, User $user, InteractiveLogin $interactiveLogin, Oidc $oidc)
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
     * @return Response
     * @throws \Exception
     * @Route("/ssoauth/frontend", name="sac_ch_sso_auth_frontend", defaults={"_scope" = "frontend", "_token_check" = false})
     */
    public function frontendUserAuthenticationAction(): Response
    {
        /** @var System $controllerAdapter */
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        $userClass = FrontendUser::class;

        $bagName = System::getContainer()->getParameter('swiss_alpine_club_contao_login_client_session_attribute_bag_name');

        /** @var SessionInterface $session */
        $session = $this->session->getBag($bagName);

        // Set redirect uri
        $this->oidc->setProviderData(['redirectUri' => Config::get('SAC_SSO_LOGIN_REDIRECT_URI_FRONTEND')]);

        // Run the authorisation code flow
        if ($this->oidc->runAuthorisation())
        {
            $arrData = $session->get('arrData');

            $this->remoteUser->create($arrData);
            //$this->remoteUser->create($this->remoteUser->getMockUserData(false)); // Should end in an error message

            // Check if user is SAC member
            $this->remoteUser->checkIsSacMember();

            // Check if user is member of an allowed section
            $this->remoteUser->checkIsMemberInAllowedSection();

            // Check if username is valid
            $this->remoteUser->checkHasValidUsername();

            // Check has valid email address
            $this->remoteUser->checkHasValidEmail();

            // Create User if it not exists
            $this->user->createIfNotExists($this->remoteUser, $userClass);

            // Update user
            $this->user->updateUser($this->remoteUser, $userClass);

            // Check if user exists
            $this->user->checkUserExists($this->remoteUser, $userClass);

            // Set tl_member.login='1'
            $this->user->activateLogin($this->remoteUser, $userClass);

            // Set tl_member.locked=0 or tl_user.locked=0
            $this->user->unlock($this->remoteUser, $userClass);

            // log in user
            $this->interactiveLogin->login($this->remoteUser, $userClass, InteractiveLogin::SECURED_AREA_FRONTEND);

            $jumpToPath = $session->get('targetPath');
            $session->clear();

            // All ok. User is logged in redirect to target page!!!

            /** @var  Controller $controllerAdapter */
            $controllerAdapter->redirect($jumpToPath);
        }
        else
        {
            $errorPage = $session->get('errorPath');
            $arrError = $session->get('lastOidcError', []);
            $flashBagKey = $systemAdapter->getContainer()->getParameter('swiss_alpine_club_contao_login_client_session_flash_bag_key');
            $this->session->getFlashBag()->add($flashBagKey, $arrError);
            $controllerAdapter->redirect($errorPage);
        }
    }

    /**
     * @return Response
     * @throws \Exception
     * @Route("/ssoauth/backend", name="sac_ch_sso_auth_backend", defaults={"_scope" = "backend", "_token_check" = false})
     */
    public function backendUserAuthenticationAction(): Response
    {
        return new Response('This extension is under construction.', 200);
    }

}
