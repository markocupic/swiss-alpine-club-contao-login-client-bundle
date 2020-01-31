<?php

declare(strict_types=1);

/**
 * Swiss Alpine Club Contao Login Client Bundle
 * Copyright (c) 2008-2020 Marko Cupic
 * @package swiss-alpine-club-contao-login-client-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2020
 * @link https://github.com/markocupic/swiss-alpine-club-contao-login-client-bundle
 */

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Controller\Oauth;

use Contao\Config;
use Contao\Controller;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\ErrorMessage\PrintErrorMessage;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Exception\InvalidRequestTokenException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FrontendUser;
use Contao\BackendUser;
use Contao\PageModel;
use Contao\RequestToken;
use Contao\System;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Authentication\Authentication;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericProvider;
use Symfony\Component\Routing\Annotation\Route;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\User\User;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\AppChecker\AppChecker;
use GuzzleHttp\Psr7\Request;

/**
 * Class OauthController
 * @package Markocupic\SwissAlpineClubContaoLoginClientBundle\Controller\Oauth
 */
class OauthController extends AbstractController
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
     * @var Authentication
     */
    private $authentication;

    /**
     * @var AppChecker
     */
    private $appChecker;

    /**
     * @var PrintErrorMessage
     */
    private $printErrorMessage;

    /**
     * OauthController constructor.
     * @param ContaoFramework $framework
     * @param Authentication $authentication
     */
    public function __construct(ContaoFramework $framework, RequestStack $requestStack, User $user, Authentication $authentication, AppChecker $appChecker, PrintErrorMessage $printErrorMessage)
    {
        $this->framework = $framework;
        $this->requestStack = $requestStack;
        $this->user = $user;
        $this->authentication = $authentication;
        $this->framework->initialize();
        $this->appChecker = $appChecker;
        $this->printErrorMessage = $printErrorMessage;

        // Check app config (tl_settings)
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

        $providerKey = Authentication::SECURED_AREA_FRONTEND;

        $provider = new GenericProvider([
            // The client ID assigned to you by the provider
            'clientId'                => Config::get('SAC_SSO_LOGIN_CLIENT_ID'),
            // The client password assigned to you by the provider
            'clientSecret'            => Config::get('SAC_SSO_LOGIN_CLIENT_SECRET'),
            // Absolute Callbackurl to your system(must be registered by service provider.)
            'redirectUri'             => Config::get('SAC_SSO_LOGIN_REDIRECT_URI'),
            'urlAuthorize'            => Config::get('SAC_SSO_LOGIN_URL_AUTHORIZE'),
            'urlAccessToken'          => Config::get('SAC_SSO_LOGIN_URL_ACCESS_TOKEN'),
            'urlResourceOwnerDetails' => Config::get('SAC_SSO_LOGIN_URL_RESOURCE_OWNER_DETAILS'),
            'response_type'           => 'code',
            'scopes'                  => ['openid'],
        ]);

        $request = $this->requestStack->getCurrentRequest();

        // If we don't have an authorization code then get one
        if (!$request->query->has('code'))
        {
            if (!$request->query->has('moduleId'))
            {
                // Module id not found in the query string
                throw new AppCheckFailedException('Login Error: URI parameter "moduleId" not found.');
            }

            if (!$request->query->has('targetPath'))
            {
                // Target path not found in the query string
                throw new AppCheckFailedException('Login Error: URI parameter "targetPath" not found.');
            }

            if (!$request->query->has('rt') || !RequestToken::validate($request->query->get('rt')))
            {
                throw new InvalidRequestTokenException('Invalid CSRF token. Please reload the page and try again.');
            }

            $_SESSION['SAC_SSO_OIDC_LOGIN']['targetPath'] = $request->query->get('targetPath');
            $_SESSION['SAC_SSO_OIDC_LOGIN']['moduleId'] = $request->query->get('moduleId');

            // Fetch the authorization URL from the provider; this returns the urlAuthorize option and generates and applies any necessary parameters
            // (e.g. state).
            $authorizationUrl = $provider->getAuthorizationUrl();

            // Get the state and store it to the session.
            $_SESSION['oauth2state'] = $provider->getState();

            // Redirect the user to the authorization URL.
            header('Location: ' . $authorizationUrl);
            exit;
            // Check given state against previously stored one to mitigate CSRF attack
        }
        elseif (empty($request->query->get('state')) || ($request->query->get('state') !== $_SESSION['oauth2state']))
        {
            unset($_SESSION['oauth2state']);
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

                if (!isset($arrData) || empty($arrData['contact_number']) || empty($arrData['email']) || empty($arrData['Roles']) || empty($arrData['contact_number']) || empty($arrData['sub']))
                {
                    $arrError = [
                        'matter'   => 'Die Überprüfung der Daten vom Identity Provider ist fehlgeschlagen.',
                        'howToFix' => 'Sie müssen Mitglied des SAC sein, um sich auf diesem Portal einloggen zu können.',
                        'explain'  => 'Der geschütze Bereich ist nur Mitgliedern des SAC (Schweizerischer Alpen Club) zugänglich.',
                    ];
                    $this->printErrorMessage->printErrorMessage($arrError);
                }

                // Check if user is club member
                $arrClubIds = explode(',', Config::get('SAC_EVT_SAC_SECTION_IDS'));
                if (!$this->user->isClubMember($arrData, $arrClubIds))
                {
                    $arrError = [
                        'matter'   => 'Die Überprüfung der Daten vom Identity Provider ist fehlgeschlagen.',
                        'howToFix' => 'Sie müssen Mitglied dieser SAC Sektion sein, um sich auf diesem Portal einloggen zu können.',
                        'explain'  => 'Der geschütze Bereich ist nur Mitgliedern dieser SAC Sektion zugänglich.',
                    ];
                    $this->printErrorMessage->printErrorMessage($arrError);
                }

                /**
                 * The provider provides a way to get an authenticated API request for
                 * the service, using the access token; it returns an object conforming
                 * to \GuzzleHttp\Psr7\Request.
                 * @var  \GuzzleHttp\Psr7\Request $request
                 */
                $request = $provider->getAuthenticatedRequest(
                    'GET',
                    Config::get('SAC_SSO_LOGIN_URL_RESOURCE_OWNER_DETAILS'),
                    $accessToken
                );

                // Check if username is valid
                if (!$this->user->isValidUsername($arrData['contact_number']))
                {
                    $arrError = [
                        'matter'   => sprintf('Die Überprüfung der Daten vom Identity Provider ist fehlgeschlagen. Der Benutzername "%s" ist ungültig.', $arrData['contact_number']),
                        'howToFix' => 'Bitte überprüfen Sie die Schreibweise des Benutzernamens.',
                        'explain'  => '',
                    ];
                    $this->printErrorMessage->printErrorMessage($arrError);
                }

                // Check if user exists
                if (!$hasError && !$this->user->userExists($arrData['contact_number'], $userClass))
                {
                    $arrError = [
                        'matter'   => sprintf('Die Überprüfung der Daten vom Identity Provider ist fehlgeschlagen. Der Benutzername "%s" wurde in der Datenbank nicht gefunden.', $arrData['contact_number']),
                        'howToFix' => 'Falls Sie soeben eine Neumitgliedschaft beantragt haben, warten Sie bitten einen Tag und versuchen Sie sich danach noch einmal einzuloggen.',
                        'explain'  => '',
                    ];
                    $this->printErrorMessage->printErrorMessage($arrError);
                }

                // Authenticate user
                $this->authentication->authenticate($arrData['contact_number'], $userClass, $providerKey, $request);

                $jumpToPath = $_SESSION['SAC_SSO_OIDC_LOGIN']['targetPath'];
                unset($_SESSION['SAC_SSO_OIDC_LOGIN']);

                /** @var  Controller $controllerAdapter */
                $controllerAdapter = $this->framework->getAdapter(Controller::class);
                $controllerAdapter->redirect($jumpToPath);

                // All ok. User is logged in!!!

            } catch (IdentityProviderException $e)
            {
                // Failed to get the access token or user details.
                //exit($e->getMessage());
                $arrError = [
                    'matter'   => 'Die Überprüfung Ihrer Daten vom Identity Provider ist fehlgeschlagen.',
                    'howToFix' => 'Bitte überprüfen Sie die Schreibweise ihrer Benutzereingaben.',
                    'explain'  => '',
                ];
                $this->printErrorMessage->printErrorMessage($arrError);
            }
        }

        $arrError = [
            'matter'   => 'Die Überprüfung Ihrer Daten vom Identity Provider ist fehlgeschlagen.',
            'howToFix' => 'Bitte überprüfen Sie die Schreibweise ihrer Benutzereingaben.',
            'explain'  => '',
        ];
        $this->printErrorMessage->printErrorMessage($arrError);
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

        $providerKey = Authentication::SECURED_AREA_BACKEND;

        // Authenticate user
        $this->authentication->authenticate($username, $userClass, $providerKey);

        /** @var  Controller $controllerAdapter */
        $controllerAdapter = $this->framework->getAdapter(Controller::class);
        $controllerAdapter->redirect('contao');

        return new Response(
            'Successfully logged in.',
            Response::HTTP_UNAUTHORIZED
        );
    }
}
