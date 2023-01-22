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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\System;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Exception\BadQueryStringException;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Exception\InvalidRequestTokenException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManager;

class Initializer
{
    private ContaoFramework $framework;
    private RequestStack $requestStack;
    private CsrfTokenManager $csrfTokenManager;

    public function __construct(ContaoFramework $framework, RequestStack $requestStack, CsrfTokenManager $csrfTokenManager)
    {
        $this->framework = $framework;
        $this->requestStack = $requestStack;
        $this->csrfTokenManager = $csrfTokenManager;
    }

    public function initialize(): void
    {
        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();

        // Set session from post
        if ($request->request->has('FORM_SUBMIT')) {
            /** @var System $systemAdapter */
            $systemAdapter = $this->framework->getAdapter(System::class);

            /** @var string $bagName */
            $bagName = $systemAdapter->getContainer()->getParameter('sac_oauth2_client.session.attribute_bag_name');

            /** @var Session $session */
            $session = $this->requestStack->getCurrentRequest()->getSession()->getBag($bagName);

            if ($request->request->has('REQUEST_TOKEN')) {
                $session->set('requestToken', $request->request->get('REQUEST_TOKEN'));
            }

            $session->set('targetPath', base64_decode($request->request->get('targetPath'), true));

            $session->set('failurePath', base64_decode($request->request->get('failurePath'), true));

            if ($request->request->has('moduleId')) {
                $session->set('moduleId', $request->request->get('moduleId'));
            }
        }

        $this->checkSession();
    }

    private function checkSession(): void
    {
        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        /** @var string $bagName */
        $bagName = $systemAdapter->getContainer()->getParameter('sac_oauth2_client.session.attribute_bag_name');

        /** @var Session $session */
        $session = $this->requestStack->getCurrentRequest()->getSession()->getBag($bagName);

        try {
            if (!$session->has('targetPath')) {
                // Target path not found in the query string
                throw new BadQueryStringException('Login Error: URI parameter "targetPath" not found.');
            }

            if (!$session->has('failurePath')) {
                // Target path not found in the query string
                throw new BadQueryStringException('Login Error: URI parameter "failurePath" not found.');
            }
        } catch (BadQueryStringException $e) {
            exit($e->getMessage());
        }

        // Csrf token check (disabled by default)
        if ($systemAdapter->getContainer()->getParameter('sac_oauth2_client.oidc.enable_csrf_token_check')) {
            $tokenName = $systemAdapter->getContainer()->getParameter('contao.csrf_token_name');

            try {
                if (!$session->has('REQUEST_TOKEN') || !$this->csrfTokenManager->isTokenValid(new CsrfToken($tokenName, $session->get('REQUEST_TOKEN')))) {
                    throw new InvalidRequestTokenException('Invalid CSRF token. Please reload the page and try again.');
                }
            } catch (InvalidRequestTokenException $e) {
                exit($e->getMessage());
            }
        }
    }
}
