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

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\System;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericProvider;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Exception\BadQueryStringException;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Exception\InvalidRequestTokenException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManager;

/**
 * Class Oidc.
 */
class Oidc
{
    private ContaoFramework $framework;
    private RequestStack $requestStack;
    private CsrfTokenManager $csrfTokenManager;
    private ?GenericProvider $provider = null;

    /**
     * Oidc constructor.
     */
    public function __construct(ContaoFramework $framework, RequestStack $requestStack, CsrfTokenManager $csrfTokenManager)
    {
        $this->framework = $framework;
        $this->requestStack = $requestStack;
        $this->csrfTokenManager = $csrfTokenManager;
    }

    /**
     * Service method call.
     */
    public function initializeFramework(): void
    {
        // Initialize Contao framework
        $this->framework->initialize();
    }

    public function setProvider(array $arrData = []): void
    {
        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        $arrProviderData = array_merge(
            [
                // The client ID assigned to you by the provider
                'clientId' => $systemAdapter->getContainer()->getParameter('sac_oauth2_client.oidc.client_id'),
                // The client password assigned to you by the provider
                'clientSecret' => $systemAdapter->getContainer()->getParameter('sac_oauth2_client.oidc.client_secret'),
                // Absolute callback url to your system (must be registered by service provider.)
                'redirectUri' => $systemAdapter->getContainer()->getParameter('sac_oauth2_client.oidc.client_auth_endpoint_backend'),
                'urlAuthorize' => $systemAdapter->getContainer()->getParameter('sac_oauth2_client.oidc.auth_provider_endpoint_authorize'),
                'urlAccessToken' => $systemAdapter->getContainer()->getParameter('sac_oauth2_client.oidc.auth_provider_endpoint_token'),
                'urlResourceOwnerDetails' => $systemAdapter->getContainer()->getParameter('sac_oauth2_client.oidc.auth_provider_endpoint_userinfo'),
                'response_type' => 'code',
                'scopes' => ['openid'],
            ],
            $arrData
        );

        /** @var GenericProvider $provider */
        $this->provider = new GenericProvider($arrProviderData);
    }

    public function hasAuthCode(): bool
    {
        $request = $this->requestStack->getCurrentRequest();

        return $request->query->has('code');
    }

    /**
     * @throws BadQueryStringException
     * @throws InvalidRequestTokenException
     */
    public function getAuthCode(): RedirectResponse
    {
        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();

        /** @var string $bagName */
        $bagName = $systemAdapter->getContainer()->getParameter('sac_oauth2_client.session.attribute_bag_name');

        /** @var Session $session */
        $session = $this->requestStack->getCurrentRequest()->getSession()->getBag($bagName);

        // Validate query params
        $this->checkQueryParams();

        $session->set('targetPath', base64_decode($request->request->get('targetPath'), true));
        $session->set('failurePath', base64_decode($request->request->get('failurePath'), true));

        if ($request->request->has('moduleId')) {
            $session->set('moduleId', $request->request->get('moduleId'));
        }

        // Fetch the authorization URL from the provider; this returns the urlAuthorize option and generates and applies any necessary parameters
        // (e.g. state).
        $authorizationUrl = $this->provider->getAuthorizationUrl();

        // Get the state and store it to the session.
        $session->set('oauth2state', $this->provider->getState());

        // Redirect the user to the authorization URL.
        return new RedirectResponse($authorizationUrl);
    }

    /**
     * @throws BadQueryStringException
     */
    public function getAccessToken(): void
    {
        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();

        /** @var string $bagName */
        $bagName = $systemAdapter->getContainer()->getParameter('sac_oauth2_client.session.attribute_bag_name');

        /** @var Session $session */
        $session = $this->requestStack->getCurrentRequest()->getSession()->getBag($bagName);

        if (!$this->hasAuthCode()) {
            throw new BadQueryStringException('Authorization code not found.');
        }

        if (empty($request->query->get('state')) || ($request->query->get('state') !== $session->get('oauth2state'))) {
            throw new BadQueryStringException('Invalid OAuth2 state.');
        }

        try {
            // Try to get an access token using the authorization code grant.
            $accessToken = $this->provider->getAccessToken('authorization_code', [
                'code' => $request->query->get('code'),
            ]);

            // Get details about the resource owner
            $resourceOwner = $this->provider->getResourceOwner($accessToken);
            $arrData = $resourceOwner->toArray();
            $session->set('arrData', $arrData);
        } catch (IdentityProviderException $e) {
            exit($e->getMessage());
        }
    }

    /**
     * @throws BadQueryStringException
     * @throws InvalidRequestTokenException
     */
    private function checkQueryParams(): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request->request->has('targetPath')) {
            // Target path not found in the query string
            throw new BadQueryStringException('Login Error: URI parameter "targetPath" not found.');
        }

        if (!$request->request->has('failurePath')) {
            // Target path not found in the query string
            throw new BadQueryStringException('Login Error: URI parameter "failurePath" not found.');
        }

        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        // Csrf token check (disabled by default)
        if ($systemAdapter->getContainer()->getParameter('sac_oauth2_client.oidc.enable_csrf_token_check')) {
            $tokenName = $systemAdapter->getContainer()->getParameter('contao.csrf_token_name');

            if (!$request->request->has('REQUEST_TOKEN') || !$this->csrfTokenManager->isTokenValid(new CsrfToken($tokenName, $request->request->get('REQUEST_TOKEN')))) {
                throw new InvalidRequestTokenException('Invalid CSRF token. Please reload the page and try again.');
            }
        }
    }
}
