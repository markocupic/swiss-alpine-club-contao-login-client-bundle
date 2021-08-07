<?php

declare(strict_types=1);

/*
 * This file is part of Swiss Alpine Club Contao Login Client Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
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
 * Class Oidc.
 */
class Oidc
{
    private ContaoFramework $framework;
    private RequestStack $requestStack;
    private CsrfTokenManager $csrfTokenManager;
    private TranslatorInterface $translator;
    private array $providerData = [];

    /**
     * Oidc constructor.
     *
     * @throws AppCheckFailedException
     */
    public function __construct(ContaoFramework $framework, RequestStack $requestStack, CsrfTokenManager $csrfTokenManager, TranslatorInterface $translator)
    {
        $this->framework = $framework;
        $this->requestStack = $requestStack;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->translator = $translator;
    }

    /**
     * Service method call
     */
    public function initializeFramework()
    {
        // Initialize Contao framework
        $this->framework->initialize();
    }

    /**
     * Service method call
     * Set provider data from config.
     */
    public function setProviderFromConfig(): void
    {
        /** @var Config $configAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        $this->providerData = [
            // The client ID assigned to you by the provider
            'clientId' => $systemAdapter->getContainer()->getParameter('sac_oauth2_client.oidc.client_id'),
            // The client password assigned to you by the provider
            'clientSecret' => $systemAdapter->getContainer()->getParameter('sac_oauth2_client.oidc.client_secret'),
            // Absolute Callbackurl to your system(must be registered by service provider.)
            'redirectUri' => $systemAdapter->getContainer()->getParameter('sac_oauth2_client.oidc.client_auth_endpoint_backend'),
            'urlAuthorize' => $systemAdapter->getContainer()->getParameter('sac_oauth2_client.oidc.auth_provider_endpoint_authorize'),
            'urlAccessToken' => $systemAdapter->getContainer()->getParameter('sac_oauth2_client.oidc.auth_provider_endpoint_token'),
            'urlResourceOwnerDetails' => $systemAdapter->getContainer()->getParameter('sac_oauth2_client.oidc.auth_provider_endpoint_userinfo'),
            'response_type' => 'code',
            'scopes' => ['openid'],
        ];
    }

    /**
     * @throws AppCheckFailedException
     * @throws InvalidRequestTokenException
     */
    public function runOpenIdConnectFlow(): bool
    {
        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        /** @var GenericProvider $provider */
        $provider = new GenericProvider($this->getProviderData());

        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();

        $bagName = $systemAdapter->getContainer()->getParameter('sac_oauth2_client.session.attribute_bag_name');

        /** @var Session $session */
        $session = $this->requestStack->getCurrentRequest()->getSession()->getBag($bagName);

        // If we don't have an authorization code then get one
        if (!$request->query->has('code')) {
            // Validate query params
            $this->checkQueryParams();

            $session->set('targetPath', base64_decode($request->request->get('targetPath'), true));
            $session->set('failurePath', base64_decode($request->request->get('failurePath'), true));

            if ($request->request->has('moduleId')) {
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

        if (empty($request->query->get('state')) || ($request->query->get('state') !== $session->get('oauth2state'))) {
            // Check given state against previously stored one to mitigate CSRF attack
            $session->remove('oauth2state');

            $arrError = [
                'level' => 'error',
                'matter' => $this->translator->trans('ERR.sacOidcLoginError_invalidState_matter', [], 'contao_default'),
                'howToFix' => $this->translator->trans('ERR.sacOidcLoginError_invalidState_howToFix', [], 'contao_default'),
                'explain' => $this->translator->trans('ERR.sacOidcLoginError_invalidState_explain', [], 'contao_default'),
            ];
            $session->set('lastOidcError', $arrError);

            return false;
        }

        try {
            // Try to get an access token using the authorization code grant.
            $accessToken = $provider->getAccessToken('authorization_code', [
                'code' => $request->query->get('code'),
            ]);

            // Get details about the resource owner
            $resourceOwner = $provider->getResourceOwner($accessToken);
            $arrData = $resourceOwner->toArray();
            $session->set('arrData', $arrData);

            return true;
        } catch (IdentityProviderException $e) {
            // Failed to get the access token or user details.
            $arrError = [
                'level' => 'error',
                'matter' => $this->translator->trans('ERR.sacOidcLoginError_invalidState_matter', [], 'contao_default'),
                'howToFix' => $this->translator->trans('ERR.sacOidcLoginError_invalidState_howToFix', [], 'contao_default'),
                'explain' => $this->translator->trans('ERR.sacOidcLoginError_invalidState_explain', [], 'contao_default'),
            ];
            $session->set('lastOidcError', $arrError);

            return false;
        }
    }

    public function getProviderData(): array
    {
        return $this->providerData;
    }

    public function setProviderData(array $arrData): void
    {
        $this->providerData = array_merge($this->providerData, $arrData);
    }

    /**
     * @throws AppCheckFailedException
     * @throws InvalidRequestTokenException
     */
    private function checkQueryParams(): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request->request->has('targetPath')) {
            // Target path not found in the query string
            $this->sendErrorMessageToBrowser('Login Error: URI parameter "targetPath" not found.');
            exit;
        }

        if (!$request->request->has('failurePath')) {
            // Target path not found in the query string
            $this->sendErrorMessageToBrowser('Login Error: URI parameter "failurePath" not found.');
            exit;
        }

        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        // Check csrf token (disabled by default)
        if ($systemAdapter->getContainer()->getParameter('sac_oauth2_client.oidc.enable_csrf_token_check')) {
            $tokenName = $systemAdapter->getContainer()->getParameter('contao.csrf_token_name');

            if (!$request->request->has('REQUEST_TOKEN') || !$this->csrfTokenManager->isTokenValid(new CsrfToken($tokenName, $request->request->get('REQUEST_TOKEN')))) {
                $this->sendErrorMessageToBrowser('Invalid CSRF token. Please reload the page and try again.');
                exit;
            }
        }
    }

    /**
     * @param $msg
     */
    private function sendErrorMessageToBrowser(string $msg): Response
    {
        $response = new Response($msg, 400);

        return $response->send();
    }


}
