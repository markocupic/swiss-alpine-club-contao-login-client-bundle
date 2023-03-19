<?php

declare(strict_types=1);

/*
 * This file is part of Swiss Alpine Club Contao Login Client Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/swiss-alpine-club-contao-login-client-bundle
 */

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Controller;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Exception\InvalidRequestTokenException;
use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\System;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Event\OAuth2SuccessEvent;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\Oauth\Exception\BadQueryStringException;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\Oauth\Provider\ProviderFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionBagInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfToken;

#[Route('/ssoauth/frontend', name: 'swiss_alpine_club_sso_login_frontend', defaults: ['_scope' => 'frontend', '_token_check' => false])]
#[Route('/ssoauth/backend', name: 'swiss_alpine_club_sso_login_backend', defaults: ['_scope' => 'backend', '_token_check' => false])]
class ContaoOauth2LoginController extends AbstractController
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly ContaoCsrfTokenManager $csrfTokenManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ProviderFactory $providerFactory,
    ) {
    }

    /**
     * @throws \Exception
     */
    public function __invoke(Request $request, string $_scope): Response
    {
        $this->framework->initialize(ContaoCoreBundle::SCOPE_FRONTEND === $_scope);

        $provider = $this->providerFactory->createProvider($_scope);

        if (!$request->query->has('code')) {
            $session = $this->getSession($request, $_scope);

            // Add failure path, target path and module id (if frontend request) to the session.
            $this->addDataToSession($request, $_scope);

            // Validate csrf token if enabled (disabled by default).
            $this->validateCsrfToken($request, $_scope);

            // 1. fetch the authorization URL from the provider;
            // this returns the urlAuthorize option and generates and applies any necessary parameters
            // (e.g. state).
            $authorizationUrl = $provider->getAuthorizationUrl();

            // 2. store the state to the session to mitigate cross site request forgery
            $session->set('oauth2state', $provider->getState());

            // Redirect the user to the authorization URL.
            throw new RedirectResponseException($authorizationUrl);
        }

        // Yeah, we have an access token!
        // But the Contao login is still pending.
        $oauth2SuccessEvent = new OAuth2SuccessEvent(
            $request,
            $provider,
            $this->getAccessToken($request, $provider, $_scope),
            $_scope,
        );

        if (!$this->eventDispatcher->hasListeners($oauth2SuccessEvent::NAME)) {
            return new Response('Successful oauth2 login but no success handler defined.');
        }

        // Dispatch the OAuth2 success event.
        // Use an event subscriber to ...
        // - get a Contao user from resource owner
        // - check if user is in an allowed section, etc.
        // - and login to the Contao firewall or redirect to login-failure page
        $this->eventDispatcher->dispatch($oauth2SuccessEvent, $oauth2SuccessEvent::NAME);

        // This point should normally not be reached at all,
        // since a successful login will take you to the Contao frontend or backend.
        return new Response('');
    }

    private function addDataToSession(Request $request, string $scope): void
    {
        $session = $this->getSession($request, $scope);

        // Set session from post
        if (!$request->request->has('targetPath')) {
            // Target path not found in the query string
            throw new BadQueryStringException('Login Error: URI parameter "targetPath" not found.');
        }
        $session->set('targetPath', base64_decode($request->request->get('targetPath'), true));

        if (!$request->request->has('failurePath')) {
            // Target path not found in the query string
            throw new BadQueryStringException('Login Error: URI parameter "failurePath" not found.');
        }
        $session->set('failurePath', base64_decode($request->request->get('failurePath'), true));

        if ($request->request->has('REQUEST_TOKEN')) {
            $session->set('requestToken', $request->request->get('REQUEST_TOKEN'));
        }

        if ($request->request->has('moduleId')) {
            $session->set('moduleId', $request->request->get('moduleId'));
        }
    }

    private function getAccessToken(Request $request, AbstractProvider $provider, string $scope): AccessToken
    {
        $session = $this->getSession($request, $scope);

        try {
            if (!$request->query->has('code')) {
                throw new BadQueryStringException('Authorization code not found.');
            }

            // Comparing the state from query with the state saved in the session will mitigate cross site request forgery.
            if (empty($request->query->get('state')) || ($request->query->get('state') !== $session->get('oauth2state'))) {
                throw new BadQueryStringException('Invalid OAuth2 state.');
            }

            // Try to get an access token using the authorization code grant.
            $accessToken = $provider->getAccessToken('authorization_code', [
                'code' => $request->query->get('code'),
            ]);
        } catch (BadQueryStringException|IdentityProviderException $e) {
            throw new ResponseException(new Response($e->getMessage()));
        }

        return $accessToken;
    }

    private function getSession(Request $request, string $scope): SessionBagInterface
    {
        if ('backend' === $scope) {
            $session = $request->getSession()->getBag('sac_oauth2_client_attr_backend');
        } else {
            $session = $request->getSession()->getBag('sac_oauth2_client_attr_frontend');
        }

        return $session;
    }

    /**
     * @throws InvalidRequestTokenException
     */
    private function validateCsrfToken(Request $request, string $scope): void
    {
        $systemAdapter = $this->framework->getAdapter(System::class);

        if ($systemAdapter->getContainer()->getParameter('sac_oauth2_client.oidc.enable_csrf_token_check')) {
            $tokenName = $systemAdapter->getContainer()->getParameter('contao.csrf_token_name');
            $session = $this->getSession($request, $scope);

            if (!$session->has('requestToken') || !$this->csrfTokenManager->isTokenValid(new CsrfToken($tokenName, $session->get('requestToken')))) {
                throw new InvalidRequestTokenException('Invalid CSRF token. Please reload the page and try again.');
            }
        }
    }
}
