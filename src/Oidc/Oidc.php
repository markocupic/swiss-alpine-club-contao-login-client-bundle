<?php

declare(strict_types=1);

/**
 * Swiss Alpine Club (SAC) Contao Login Client Bundle
 * Copyright (c) 2008-2020 Marko Cupic
 * @package swiss-alpine-club-contao-login-client-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/swiss-alpine-club-contao-login-client-bundle
 */

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Oidc;

use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\System;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericProvider;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Exception\AppCheckFailedException;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Exception\InvalidRequestTokenException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManager;

/**
 * Class Oidc
 * @package Markocupic\SwissAlpineClubContaoLoginClientBundle\Oidc
 */
class Oidc
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
     * @var CsrfTokenManager
     */
    private $csrfTokenManager;

    /**
     * @var array
     */
    private $providerData = [];

    /**
     * Oidc constructor.
     * @param ContaoFramework $framework
     * @param RequestStack $requestStack
     * @param Session $session
     * @param CsrfTokenManager $csrfTokenManager
     * @throws AppCheckFailedException
     */
    public function __construct(ContaoFramework $framework, RequestStack $requestStack, Session $session, CsrfTokenManager $csrfTokenManager)
    {
        $this->framework = $framework;
        $this->requestStack = $requestStack;
        $this->session = $session;
        $this->csrfTokenManager = $csrfTokenManager;

        $this->framework->initialize();

        // Check app configuration in the contao backend settings (tl_settings)
        $this->checkConfiguration();

        // Set provider data from config
        $this->setProviderFromConfig();
    }

    /**
     * @return bool
     * @throws AppCheckFailedException
     * @throws InvalidRequestTokenException
     */
    public function runOpenIdConnectFlow(): bool
    {
        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        /** @var GenericProvider $provider */
        $provider = new GenericProvider($this->getProviderData());

        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();

        $bagName = System::getContainer()->getParameter('swiss_alpine_club_contao_login_client.session.attribute_bag_name');

        /** @var Session $session */
        $session = $this->session->getBag($bagName);

        // If we don't have an authorization code then get one
        if (!$request->query->has('code'))
        {
            // Validate query params
            $this->checkQueryParams();

            $session->set('targetPath', $request->query->get('targetPath'));
            $session->set('errorPath', $request->query->get('errorPath'));
            if ($request->query->has('moduleId'))
            {
                $session->set('moduleId', $request->query->get('moduleId'));
            }

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

            $arrError = [
                'matter'   => 'Die Überprüfung Ihrer Daten vom Identity Provider hat fehlgeschlagen. Fehlercode: ungültiger state!',
                'howToFix' => 'Bitte überprüfen Sie die Schreibweise Ihrer Benutzereingaben.',
                'explain'  => '',
            ];
            $session->set('lastOidcError', $arrError);
            return false;
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
                $session->set('arrData', $arrData);
                return true;
            } catch (IdentityProviderException $e)
            {
                // Failed to get the access token or user details.
                $arrError = [
                    'matter'   => 'Die Überprüfung Ihrer Daten vom Identity Provider hat fehlgeschlagen.',
                    'howToFix' => 'Bitte überprüfen Sie die Schreibweise Ihrer Benutzereingaben.',
                    'explain'  => '',
                ];
                $session->set('lastOidcError', $arrError);
                return false;
            }
        }
    }

    /**
     * @return array
     */
    public function getProviderData(): array
    {
        return $this->providerData;
    }

    /**
     * Set provider data from config
     */
    private function setProviderFromConfig(): void
    {
        $this->providerData = [
            // The client ID assigned to you by the provider
            'clientId'                => Config::get('SAC_SSO_LOGIN_CLIENT_ID'),
            // The client password assigned to you by the provider
            'clientSecret'            => Config::get('SAC_SSO_LOGIN_CLIENT_SECRET'),
            // Absolute Callbackurl to your system(must be registered by service provider.)
            'redirectUri'             => Config::get('SAC_SSO_LOGIN_REDIRECT_URI_BACKEND'),
            'urlAuthorize'            => Config::get('SAC_SSO_LOGIN_URL_AUTHORIZE'),
            'urlAccessToken'          => Config::get('SAC_SSO_LOGIN_URL_ACCESS_TOKEN'),
            'urlResourceOwnerDetails' => Config::get('SAC_SSO_LOGIN_URL_RESOURCE_OWNER_DETAILS'),
            'response_type'           => 'code',
            'scopes'                  => ['openid'],
        ];
    }

    /**
     * @param array $arrData
     */
    public function setProviderData(array $arrData): void
    {
        $this->providerData = array_merge($this->providerData, $arrData);
    }

    /**
     * @throws AppCheckFailedException
     * @throws InvalidRequestTokenException
     */
    private function checkQueryParams()
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request->query->has('targetPath'))
        {
            // Target path not found in the query string
            throw new AppCheckFailedException('Login Error: URI parameter "targetPath" not found.');
        }

        if (!$request->query->has('errorPath'))
        {
            // Target path not found in the query string
            throw new AppCheckFailedException('Login Error: URI parameter "errorPath" not found.');
        }

        $tokenName = System::getContainer()->getParameter('contao.csrf_token_name');
        if (!$request->query->has('rt') || !$this->csrfTokenManager->isTokenValid(new CsrfToken($tokenName, $request->query->get('rt'))))
        {
            throw new InvalidRequestTokenException('Invalid CSRF token. Please reload the page and try again.');
        }
    }

    /**
     * @throws AppCheckFailedException
     */
    private function checkConfiguration()
    {
        $arrConfigs = [
            // Club ids
            'SAC_EVT_SAC_SECTION_IDS',
            //OIDC Stuff
            'SAC_SSO_LOGIN_CLIENT_ID',
            'SAC_SSO_LOGIN_CLIENT_SECRET',
            'SAC_SSO_LOGIN_REDIRECT_URI_FRONTEND',
            'SAC_SSO_LOGIN_REDIRECT_URI_BACKEND',
            'SAC_SSO_LOGIN_URL_AUTHORIZE',
            'SAC_SSO_LOGIN_URL_ACCESS_TOKEN',
            'SAC_SSO_LOGIN_URL_RESOURCE_OWNER_DETAILS',
        ];

        foreach ($arrConfigs as $config)
        {
            if (empty(Config::get($config)))
            {
                throw new AppCheckFailedException('Parameter tl_settings.' . $config . ' not found. Please check the Contao settings');
            }
        }
    }

}
