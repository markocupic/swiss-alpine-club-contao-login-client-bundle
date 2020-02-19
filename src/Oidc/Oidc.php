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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Translation\TranslatorInterface;

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
     * @var TranslatorInterface
     */
    private $translator;

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
     * @param TranslatorInterface $translator
     * @throws AppCheckFailedException
     */
    public function __construct(ContaoFramework $framework, RequestStack $requestStack, Session $session, CsrfTokenManager $csrfTokenManager, TranslatorInterface $translator)
    {
        $this->framework = $framework;
        $this->requestStack = $requestStack;
        $this->session = $session;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->translator = $translator;

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

            $session->set('targetPath', base64_decode($request->request->get('targetPath')));
            $session->set('failurePath', base64_decode($request->request->get('failurePath')));
            if ($request->request->has('moduleId'))
            {
                $session->set('moduleId', $request->request->get('moduleId'));
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
                'level'    => 'error',
                'matter'   => $this->translator->trans('ERR.sacOidcLoginError_invalidState_matter', [], 'contao_default'),
                'howToFix' => $this->translator->trans('ERR.sacOidcLoginError_invalidState_howToFix', [], 'contao_default'),
                'explain'  => $this->translator->trans('ERR.sacOidcLoginError_invalidState_explain', [], 'contao_default'),
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
                    'level'    => 'error',
                    'matter'   => $this->translator->trans('ERR.sacOidcLoginError_invalidState_matter', [], 'contao_default'),
                    'howToFix' => $this->translator->trans('ERR.sacOidcLoginError_invalidState_howToFix', [], 'contao_default'),
                    'explain'  => $this->translator->trans('ERR.sacOidcLoginError_invalidState_explain', [], 'contao_default'),
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

        if (!$request->request->has('targetPath'))
        {
            // Target path not found in the query string
            $this->sendErrorMessageToBrowser('Login Error: URI parameter "targetPath" not found.');
            exit;
        }

        if (!$request->request->has('failurePath'))
        {
            // Target path not found in the query string
            $this->sendErrorMessageToBrowser('Login Error: URI parameter "failurePath" not found.');
            exit;
        }

        $tokenName = System::getContainer()->getParameter('contao.csrf_token_name');
        if (!$request->request->has('REQUEST_TOKEN') || !$this->csrfTokenManager->isTokenValid(new CsrfToken($tokenName, $request->request->get('REQUEST_TOKEN'))))
        {
            $this->sendErrorMessageToBrowser('Invalid CSRF token. Please reload the page and try again.');
            exit;
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
            'SAC_SSO_LOGIN_URL_LOGOUT',
        ];

        foreach ($arrConfigs as $config)
        {
            if (empty(Config::get($config)))
            {
                throw new AppCheckFailedException('Parameter tl_settings.' . $config . ' not found. Please check the Contao settings');
            }
        }
    }

    /**
     * @param $arrMsg
     * @return Response
     */
    private function sendErrorMessageToBrowser(string $msg): Response
    {
        $response = new Response($msg, 400);
        return $response->send();
    }

}
