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

use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\User\RemoteUser;
use Contao\FrontendUser;
use Contao\System;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericProvider;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\AppChecker\AppChecker;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\InteractiveLogin\InteractiveLogin;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\ErrorMessage\PrintErrorMessage;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Authorization\AuthorizationHelper;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\User\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
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
     * @var RequestStack
     */
    private $requestStack;

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
     * @var AppChecker
     */
    private $appChecker;

    /**
     * @var AuthorizationHelper
     */
    private $authorizationHelper;

    /**
     * @var PrintErrorMessage
     */
    private $printErrorMessage;

    /**
     * AuthorizationController constructor.
     * @param ContaoFramework $framework
     * @param RequestStack $requestStack
     * @param SessionInterface $session
     * @param RemoteUser $remoteUser
     * @param User $user
     * @param InteractiveLogin $interactiveLogin
     * @param AppChecker $appChecker
     * @param AuthorizationHelper $authorizationHelper
     * @param PrintErrorMessage $printErrorMessage
     * @throws \Markocupic\SwissAlpineClubContaoLoginClientBundle\Exception\AppCheckFailedException
     */
    public function __construct(ContaoFramework $framework, RequestStack $requestStack, SessionInterface $session, RemoteUser $remoteUser, User $user, InteractiveLogin $interactiveLogin, AppChecker $appChecker, AuthorizationHelper $authorizationHelper, PrintErrorMessage $printErrorMessage)
    {
        $this->framework = $framework;
        $this->requestStack = $requestStack;
        $this->session = $session;
        $this->remoteUser = $remoteUser;
        $this->user = $user;
        $this->interactiveLogin = $interactiveLogin;
        $this->appChecker = $appChecker;
        $this->authorizationHelper = $authorizationHelper;
        $this->printErrorMessage = $printErrorMessage;

        $this->framework->initialize();

        // Check app configuration in the contao backend settings (tl_settings)
        $this->appChecker->checkConfiguration();
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

        /** @var GenericProvider $provider */
        $provider = new GenericProvider($this->authorizationHelper->getProviderData());

        /** @var Symfony\Component\HttpFoundation\Request $request */
        $request = $this->requestStack->getCurrentRequest();

        $bagName = System::getContainer()->getParameter('swiss_alpine_club_contao_login_client_session_attribute_bag_name');

        /** @var SessionInterface $session */
        $session = $this->session->getBag($bagName);

        // If we don't have an authorization code then get one
        if (!$request->query->has('code'))
        {
            // Validate query params
            $this->authorizationHelper->checkQueryParams();

            $session->set('targetPath', $request->query->get('targetPath'));
            $session->set('errorPath', $request->query->get('errorPath'));
            $session->set('moduleId', $request->query->get('moduleId'));

            // Fetch the authorization URL from the provider; this returns the urlAuthorize option and generates and applies any necessary parameters
            // (e.g. state).
            $authorizationUrl = $provider->getAuthorizationUrl();

            // Get the state and store it to the session.
            $session->set('oauth2state', $provider->getState());

            // Redirect the user to the authorization URL.
            $controllerAdapter->redirect($authorizationUrl);
            exit;
        }
        elseif (empty($request->query->get('state')) || ($request->query->get('state') !== $session->get('oauth2state')))
        {
            // Check given state against previously stored one to mitigate CSRF attack
            $session->remove('oauth2state');

            // Invalid username or user does not exists
            $arrError = [
                'matter'   => 'Die Überprüfung Ihrer Daten vom Identity Provider hat fehlgeschlagen. Fehlercode: ungültiger state!',
                'howToFix' => 'Bitte überprüfen Sie die Schreibweise Ihrer Benutzereingaben.',
                'explain'  => '',
            ];
            $flashBagKey = $systemAdapter->getContainer()->getParameter('swiss_alpine_club_contao_login_client_session_flash_bag_key');
            $this->session->getFlashBag()->add($flashBagKey, $arrError);
            $controllerAdapter->redirect($this->session->getBag($bagName)->get('errorPath'));
        }
        else
        {
            try
            {
                // Try to get an access token using the authorization code grant.
                $accessToken = $provider->getAccessToken('authorization_code', [
                    'code' => $request->query->get('code')
                ]);

                // Get details about the resource owner
                $resourceOwner = $provider->getResourceOwner($accessToken);
                $arrData = $resourceOwner->toArray();

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
            } catch (IdentityProviderException $e)
            {
                // Failed to get the access token or user details.
                //exit($e->getMessage());

                $arrError = [
                    'matter'   => 'Die Überprüfung Ihrer Daten vom Identity Provider hat fehlgeschlagen.',
                    'howToFix' => 'Bitte überprüfen Sie die Schreibweise Ihrer Benutzereingaben.',
                    'explain'  => '',
                ];
                $flashBagKey = $systemAdapter->getContainer()->getParameter('swiss_alpine_club_contao_login_client_session_flash_bag_key');
                $this->session->getFlashBag()->add($flashBagKey, $arrError);
                $controllerAdapter->redirect($this->session->getBag($bagName)->get('errorPath'));
            }
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
