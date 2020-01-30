<?php

declare(strict_types=1);

/**
 * Swiss Alpine Club Contao Login Client Bundle
 * Copyright (c) 2008-2020 Marko Cupic
 * @package swiss-alpine-club-contao-login-client-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2020
 * @link https://github.com/markocupic/swiss-alpine-club-contao-login-client-bundle
 */

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Controller;

use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FrontendUser;
use Contao\BackendUser;
use Contao\PageModel;
use Contao\System;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Authentication\Authentication;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class OauthController
 * @package Markocupic\SwissAlpineClubContaoLoginClientBundle\Controller
 */
class OauthController extends AbstractController
{

    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var Authentication
     */
    private $authentication;

    /**
     * OauthController constructor.
     * @param ContaoFramework $framework
     * @param Authentication $authentication
     */
    public function __construct(ContaoFramework $framework, Authentication $authentication)
    {
        $this->framework = $framework;
        $this->authentication = $authentication;

        $this->framework->initialize();
    }

    /**
     * @return Response
     * @throws \Exception
     * @Route("/ssoauth/frontend", name="sac_ch_sso_auth_frontend", defaults={"_scope" = "frontend", "_token_check" = false})
     */
    public function frontendUserAuthenticationAction(): Response
    {
        //return new Response('This extension is under construction.', 200);

        // Retrieve the username from openid connect
        $username = '185155';

        $userClass = FrontendUser::class;

        $providerKey = Authentication::SECURED_AREA_FRONTEND;

        $this->framework = System::getContainer()->get('contao.framework');
        $this->framework->initialize();

        ////
        ///

        $provider = new \League\OAuth2\Client\Provider\GenericProvider([
            'clientId'                => Config::get('SAC_SSO_LOGIN_CLIENT_ID'),    // The client ID assigned to you by the provider
            'clientSecret'            => Config::get('SAC_SSO_LOGIN_CLIENT_SECRET'),   // The client password assigned to you by the provider
            'redirectUri'             => Config::get('SAC_SSO_LOGIN_REDIRECT_URI'), // Absolute Callbackurl to your system(must be registered by service provider.)
            'urlAuthorize'            => Config::get('SAC_SSO_LOGIN_URL_AUTHORIZE'),
            'urlAccessToken'          => Config::get('SAC_SSO_LOGIN_URL_ACCESS_TOKEN'),
            'urlResourceOwnerDetails' => Config::get('SAC_SSO_LOGIN_URL_RESOURCE_OWNER_DETAILS'),
            'response_type'           => 'code',
            'scopes'                  => ['openid'],
        ]);
        mail('m.cupic@gmx.ch', 'state ' . $provider->getState(), '');

        // If we don't have an authorization code then get one
        if (!isset($_GET['code']))
        {
            // Fetch the authorization URL from the provider; this returns the
            // urlAuthorize option and generates and applies any necessary parameters
            // (e.g. state).
            $authorizationUrl = $provider->getAuthorizationUrl();

            // Get the state generated for you and store it to the session.
            $_SESSION['oauth2state'] = $provider->getState();

            // Redirect the user to the authorization URL.
            header('Location: ' . $authorizationUrl);
            exit;
            // Check given state against previously stored one to mitigate CSRF attack
        }
        elseif (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state']))
        {
            unset($_SESSION['oauth2state']);
            exit('Invalid state');
        }
        else
        {
            try
            {
                // Try to get an access token using the authorization code grant.
                $accessToken = $provider->getAccessToken('authorization_code', [
                    'code' => $_GET['code']
                ]);

                // We have an access token, which we may use in authenticated
                // requests against the service provider's API.
                //echo $accessToken->getToken() . "\n";
                //echo $accessToken->getRefreshToken() . "\n";
                //echo $accessToken->getExpires() . "\n";
                //echo ($accessToken->hasExpired() ? 'expired' : 'not expired') . "\n";

                // Using the access token, we may look up details about the
                // resource owner.
                $resourceOwner = $provider->getResourceOwner($accessToken);
                $arrData = $resourceOwner->toArray();
                mail('m.cupic@gmx.ch', 'var_export ', print_r($arrData, true));
                $username = $arrData['contact_number'];

                //echo "kostenpflichtig REST Stuff";

                // The provider provides a way to get an authenticated API request for
                // the service, using the access token; it returns an object conforming
                // to Psr\Http\Message\RequestInterface.
                $request = $provider->getAuthenticatedRequest(
                    'GET',
                    $provider['urlResourceOwnerDetails'],
                    $accessToken
                );

                // Authenticate user
                $this->authentication->authenticate($username, $userClass, $providerKey);

                /** @var  PageModel $pageModelAdapter */
                $pageModelAdapter = $this->framework->getAdapter(PageModel::class);

                // Redirect to users profile
                $objPage = $pageModelAdapter->findByIdOrAlias('member-profile');
                if ($objPage !== null)
                {
                    /** @var  Controller $controllerAdapter */
                    $controllerAdapter = $this->framework->getAdapter(Controller::class);
                    $controllerAdapter->redirect($objPage->getFrontendUrl());
                }

                return new Response(
                    'Successfully logged in.',
                    Response::HTTP_UNAUTHORIZED
                );
            } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e)
            {
                // Failed to get the access token or user details.
                //exit($e->getMessage());
                return new Response(
                    'Login Error.' . $e->getMessage(),
                    Response::HTTP_UNAUTHORIZED
                );
            }
        }

        return new Response(
            'Login Error.',
            Response::HTTP_UNAUTHORIZED
        );
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
