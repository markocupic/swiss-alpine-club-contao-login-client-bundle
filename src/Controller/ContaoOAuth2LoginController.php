<?php

declare(strict_types=1);

/*
 * This file is part of Swiss Alpine Club Contao Login Client Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/swiss-alpine-club-contao-login-client-bundle
 */

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Controller;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Exception\InvalidRequestTokenException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Client\OAuth2ClientFactory;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Event\OAuth2SuccessEvent;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfToken;

#[Route('/ssoauth/frontend', name: 'swiss_alpine_club_sso_login_frontend', defaults: ['_scope' => 'frontend', '_token_check' => false])]
#[Route('/ssoauth/backend', name: 'swiss_alpine_club_sso_login_backend', defaults: ['_scope' => 'backend', '_token_check' => false])]
class ContaoOAuth2LoginController extends AbstractController
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly ContaoCsrfTokenManager $tokenManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly OAuth2ClientFactory $oAuth2ClientFactory,
        private readonly string $csrfTokenName,
        private readonly bool $enableCsrfTokenCheck,
    ) {
    }

    public function __invoke(Request $request, string $_scope): Response
    {
        $this->framework->initialize(ContaoCoreBundle::SCOPE_FRONTEND === $_scope);

        if (!$request->query->has('code') && $request->isMethod('post')) {
            // Redirect to OAuth2 login page at https://login.sac-cas.ch/
            return $this->connectAction($request, $_scope);
        }

        return $this->getAccessTokenAction($request, $_scope);
    }

    private function connectAction(Request $request, string $_scope): Response
    {
        if ($this->enableCsrfTokenCheck) {
            $this->validateCsrfToken($request->get('REQUEST_TOKEN'));
        }

        if (!$request->request->has('_target_path')) {
            return new Response('Invalid request. Target path not found.', Response::HTTP_BAD_REQUEST);
        }

        if (!$request->request->has('_failure_path')) {
            return new Response('Invalid request. Failure path not found.', Response::HTTP_BAD_REQUEST);
        }
        // Save request params to the session
        $oAuthClient = $this->oAuth2ClientFactory->createOAuth2Client($_scope);
        $oAuthClient->setTargetPath(base64_decode($request->request->get('_target_path'), true));
        $oAuthClient->setFailurePath(base64_decode($request->request->get('_failure_path'), true));

        if (ContaoCoreBundle::SCOPE_FRONTEND === $_scope) {
            if (!$request->request->has('_module_id')) {
                return new Response('Invalid request. Module id not found.', Response::HTTP_BAD_REQUEST);
            }
            $oAuthClient->setModuleId($request->request->get('_module_id'));
        }

        return $oAuthClient->redirect();
    }

    private function getAccessTokenAction(Request $request, string $_scope): Response
    {
        if (!$request->query->has('code') || !$request->query->has('state') || !$request->query->has('session_state')) {
            return new Response('Invalid request.', Response::HTTP_BAD_REQUEST);
        }

        $oAuthClient = $this->oAuth2ClientFactory->createOAuth2Client($_scope);

        // Get the access token!
        // Log in User.
        $oauth2SuccessEvent = new OAuth2SuccessEvent($oAuthClient);

        if (!$this->eventDispatcher->hasListeners($oauth2SuccessEvent::NAME)) {
            return new Response('Successful OAuth2 login but no success handler defined.');
        }

        // Dispatch the OAuth2 success event.
        // Use an event subscriber to ...
        // - identify the Contao user from OAuth2 user
        // - check if user is in an allowed section, etc.
        // - and login to the Contao firewall or redirect to login-failure page
        $this->eventDispatcher->dispatch($oauth2SuccessEvent, $oauth2SuccessEvent::NAME);

        // This point should normally not be reached at all,
        // since a successful login will take you to the Contao frontend or backend.
        return new Response('', Response::HTTP_NO_CONTENT);
    }

    private function validateCsrfToken(string $strToken): void
    {
        $token = new CsrfToken($this->csrfTokenName, $strToken);

        if (!$this->tokenManager->isTokenValid($token)) {
            throw new InvalidRequestTokenException('Invalid CSRF token. Please reload the page and try again.');
        }
    }
}
