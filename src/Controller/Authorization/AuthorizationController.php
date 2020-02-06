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
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericProvider;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Session\Session;
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
     * @param RemoteUser $remoteUser
     * @param User $user
     * @param InteractiveLogin $interactiveLogin
     * @param AppChecker $appChecker
     * @param AuthorizationHelper $authorizationHelper
     * @param PrintErrorMessage $printErrorMessage
     * @throws \Markocupic\SwissAlpineClubContaoLoginClientBundle\Exception\AppCheckFailedException
     */
    public function __construct(ContaoFramework $framework, RequestStack $requestStack, Session $session, RemoteUser $remoteUser, User $user, InteractiveLogin $interactiveLogin, AppChecker $appChecker, AuthorizationHelper $authorizationHelper, PrintErrorMessage $printErrorMessage)
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

        $userClass = FrontendUser::class;

        $provider = new GenericProvider($this->authorizationHelper->getProviderData());

        $request = $this->requestStack->getCurrentRequest();

        // If we don't have an authorization code then get one
        if (!$request->query->has('code'))
        {
            // Validate query params
            $this->authorizationHelper->checkQueryParams();

            $this->session->sessionSet('targetPath', $request->query->get('targetPath'));
            $this->session->sessionSet('errorPath', $request->query->get('errorPath'));
            $this->session->sessionSet('moduleId', $request->query->get('moduleId'));

            // Fetch the authorization URL from the provider; this returns the urlAuthorize option and generates and applies any necessary parameters
            // (e.g. state).
            $authorizationUrl = $provider->getAuthorizationUrl();

            // Get the state and store it to the session.
            $this->session->sessionSet('oauth2state', $provider->getState());

            // Redirect the user to the authorization URL.
            Controller::redirect($authorizationUrl);
            exit;
        }
        elseif (empty($request->query->get('state')) || ($request->query->get('state') !== $this->session->sessionGet('oauth2state')))
        {
            // Check given state against previously stored one to mitigate CSRF attack
            $this->session->sessionRemove('oauth2state');

            // Invalid username or user does not exists
            return new Response(
                'Login Error: Invalid state.',
                Response::HTTP_UNAUTHORIZED
            );
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

                // Check if user is SAC member
                $this->remoteUser->checkIsSacMember();

                // Check if user is member of an allowed section
                //$this->remoteUser->create($this->user->getMockUserData(false)); // Should end in an error message
                $this->remoteUser->checkIsMemberInAllowedSection();

                // Check if username is valid
                $this->remoteUser->checkHasValidUsername();

                // Check has valid email address
                $this->remoteUser->checkHasValidEmail();

                // Create User if it not exists (Mock test user!!!!)
                //$this->user->createIfNotExists($this->user->getMockUserData(), $userClass);
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

                $jumpToPath = $this->session->sessionGet('targetPath');
                $this->session->sessionDestroy();

                // All ok. User is logged in redirect to target page!!!

                /** @var  Controller $controllerAdapter */
                $controllerAdapter = $this->framework->getAdapter(Controller::class);
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
                $this->session->addFlashBagMessage($arrError);
                Controller::redirect($this->session->sessionGet('errorPath'));
            }
        }

        $arrError = [
            'matter'   => 'Die Überprüfung Ihrer Daten vom Identity Provider hat fehlgeschlagen.',
            'howToFix' => 'Bitte überprüfen Sie die Schreibweise Ihrer Benutzereingaben.',
            'explain'  => '',
        ];
        $this->session->addFlashBagMessage($arrError);
        Controller::redirect($this->session->sessionGet('errorPath'));
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
