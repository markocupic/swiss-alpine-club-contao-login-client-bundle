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

use Contao\BackendUser;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FrontendUser;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericProvider;
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
     * @param User $user
     * @param InteractiveLogin $interactiveLogin
     * @param AppChecker $appChecker
     * @param AuthorizationHelper $authorizationHelper
     * @param PrintErrorMessage $printErrorMessage
     * @throws \Markocupic\SwissAlpineClubContaoLoginClientBundle\Exception\AppCheckFailedException
     */
    public function __construct(ContaoFramework $framework, RequestStack $requestStack, User $user, InteractiveLogin $interactiveLogin, AppChecker $appChecker, AuthorizationHelper $authorizationHelper, PrintErrorMessage $printErrorMessage)
    {
        $this->framework = $framework;
        $this->requestStack = $requestStack;
        $this->user = $user;
        $this->interactiveLogin = $interactiveLogin;
        $this->framework->initialize();
        $this->appChecker = $appChecker;
        $this->authorizationHelper = $authorizationHelper;
        $this->printErrorMessage = $printErrorMessage;

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
        // Ruwic&Aviyu926
        // Kill session https://ids02.sac-cas.ch/AvintisSSO/index.jsp

        $userClass = FrontendUser::class;

        $provider = new GenericProvider($this->authorizationHelper->getProviderData());

        $request = $this->requestStack->getCurrentRequest();

        // If we don't have an authorization code then get one
        if (!$request->query->has('code'))
        {
            // Validate query params
            $this->authorizationHelper->checkQueryParams();

            $this->authorizationHelper->sessionSet('targetPath', $request->query->get('targetPath'));
            $this->authorizationHelper->sessionSet('errorPath', $request->query->get('errorPath'));
            $this->authorizationHelper->sessionSet('moduleId', $request->query->get('moduleId'));

            // Fetch the authorization URL from the provider; this returns the urlAuthorize option and generates and applies any necessary parameters
            // (e.g. state).
            $authorizationUrl = $provider->getAuthorizationUrl();

            // Get the state and store it to the session.
            $this->authorizationHelper->sessionSet('oauth2state', $provider->getState());

            // Redirect the user to the authorization URL.
            Controller::redirect($authorizationUrl);
            exit;
        }
        elseif (empty($request->query->get('state')) || ($request->query->get('state') !== $this->authorizationHelper->sessionGet('oauth2state')))
        {
            // Check given state against previously stored one to mitigate CSRF attack
            $this->authorizationHelper->sessionRemove('oauth2state');

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

                // Check if user is SAC member
                $this->authorizationHelper->checkIsSacMember($arrData);

                // Check if user is member of an allowed section
                //$this->authorizationHelper->checkIsMemberInAllowedSection($this->user->getMockUserData(false), $userClass); // Should end in an error message
                $this->authorizationHelper->checkIsMemberInAllowedSection($arrData);

                // Check if username is valid
                $this->authorizationHelper->checkHasValidUsername($arrData);

                // Check has valid email address
                $this->authorizationHelper->checkHasValidEmail($arrData);

                // Create User if it not exists (Mock test user!!!!)
                $this->user->createIfNotExists($this->user->getMockUserData(), $userClass);
                $this->user->createIfNotExists($arrData, $userClass);

                // Update user (Mock test user!!!!)
                $this->user->updateUser($this->user->getMockUserData(false), $userClass);
                $this->user->updateUser($arrData, $userClass);

                // Check if user exists
                $this->authorizationHelper->checkUserExists($arrData, $userClass);

                // Set tl_member.login='1'
                $this->user->activateLogin($arrData['contact_number'], $userClass);

                // Set tl_member.locked=0 or tl_user.locked=0
                $this->user->unlock($arrData['contact_number'], $userClass);

                // log in user
                $this->interactiveLogin->login($arrData['contact_number'], $userClass, InteractiveLogin::SECURED_AREA_FRONTEND);

                $jumpToPath = $this->authorizationHelper->sessionGet('targetPath');
                $this->authorizationHelper->sessionDestroy();

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
                $this->authorizationHelper->addFlashBagMessage($arrError);
                Controller::redirect($this->authorizationHelper->sessionGet('errorPath'));
            }
        }

        $arrError = [
            'matter'   => 'Die Überprüfung Ihrer Daten vom Identity Provider hat fehlgeschlagen.',
            'howToFix' => 'Bitte überprüfen Sie die Schreibweise Ihrer Benutzereingaben.',
            'explain'  => '',
        ];
        $this->authorizationHelper->addFlashBagMessage($arrError);
        Controller::redirect($this->authorizationHelper->sessionGet('errorPath'));
    }

    /**
     * @return Response
     * @throws \Exception
     * @Route("/ssoauth/backend", name="sac_ch_sso_auth_backend", defaults={"_scope" = "backend", "_token_check" = false})
     */
    public function backendUserAuthenticationAction(): Response
    {
        return new Response('This extension is under construction.', 200);

        // Retrieve the username from openid connect
        $username = 'xxxxxxxxxxxx';

        $userClass = BackendUser::class;

        // Authenticate user
        $this->interactiveLogin->login($username, $userClass, InteractiveLogin::SECURED_AREA_BACKEND);

        /** @var  Controller $controllerAdapter */
        $controllerAdapter = $this->framework->getAdapter(Controller::class);
        $controllerAdapter->redirect('contao');

        return new Response(
            'Successfully logged in.',
            Response::HTTP_UNAUTHORIZED
        );
    }

}
