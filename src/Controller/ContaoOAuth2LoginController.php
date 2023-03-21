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
use Contao\CoreBundle\Exception\InvalidRequestTokenException;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\System;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Client\Exception\BadRequestParameterException;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Client\OAuth2ClientFactory;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Event\OAuth2SuccessEvent;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/ssoauth/frontend', name: 'swiss_alpine_club_sso_login_frontend', defaults: ['_scope' => 'frontend', '_token_check' => false])]
#[Route('/ssoauth/backend', name: 'swiss_alpine_club_sso_login_backend', defaults: ['_scope' => 'backend', '_token_check' => false])]
class ContaoOAuth2LoginController extends AbstractController
{
    private Adapter $systemAdapter;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly OAuth2ClientFactory $oAuth2ClientFactory,
    ) {
        $this->systemAdapter = $this->framework->getAdapter(System::class);
    }

    /**
     * @throws \Exception
     */
    public function __invoke(Request $request, string $_scope): Response
    {
        $this->framework->initialize(ContaoCoreBundle::SCOPE_FRONTEND === $_scope);

        $oAuthClient = $this->oAuth2ClientFactory->createOAuth2Client($_scope);

        if (!$request->query->has('code')) {
            if ($this->systemAdapter->getContainer()->getParameter('sac_oauth2_client.oidc.enable_csrf_token_check')) {
                $this->validateCsrfToken($request->get('REQUEST_TOKEN'));
            }

            // Save target path to the session
            if (!$request->request->has('targetPath')) {
                // Target path not found in $_POST
                throw new BadRequestParameterException('Login Error: URI parameter "targetPath" not found.');
            }

            $oAuthClient->setTargetPath(base64_decode($request->request->get('targetPath'), true));

            // Save failure path to the session
            if (!$request->request->has('failurePath')) {
                // Failure path not found in $_POST
                throw new BadRequestParameterException('Login Error: URI parameter "failurePath" not found.');
            }

            $oAuthClient->setFailurePath(base64_decode($request->request->get('failurePath'), true));

            // Save module id path to the session
            if (ContaoCoreBundle::SCOPE_FRONTEND === $_scope) {
                $oAuthClient->setModuleId($request->request->get('moduleId'));
            }

            return $oAuthClient->redirect();
        }

        // Yeah, we have an access token!
        // But the user is still not logged in against the Contao backend/frontend firewall.
        $oauth2SuccessEvent = new OAuth2SuccessEvent($oAuthClient);

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

    private function validateCsrfToken(string $token): void
    {
        $tokenName = $this->systemAdapter->getContainer()->getParameter('contao.csrf_token_name');

        if (!$this->isCsrfTokenValid($tokenName, $token)) {
            throw new InvalidRequestTokenException('Invalid CSRF token. Please reload the page and try again.');
        }
    }
}
