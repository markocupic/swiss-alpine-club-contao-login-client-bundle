<?php

declare(strict_types=1);

/*
 * This file is part of Swiss Alpine Club Contao Login Client Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/swiss-alpine-club-contao-login-client-bundle
 */

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Controller\Authentication;

use Contao\CoreBundle\Exception\ResponseException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\System;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\OpenIdConnect\OpenIdConnect;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AuthenticationController extends AbstractController
{
    private ContaoFramework $framework;
    private OpenIdConnect $openIdConnect;

    public function __construct(ContaoFramework $framework, OpenIdConnect $openIdConnect)
    {
        $this->framework = $framework;
        $this->openIdConnect = $openIdConnect;
    }

    /**
     * Login Contao frontend user.
     *
     * @throws \Exception
     *
     * @Route("/ssoauth/frontend", name="swiss_alpine_club_sso_login_frontend", defaults={"_scope" = "frontend", "_token_check" = false})
     *
     * @throws \Exception
     */
    public function authenticateContaoFrontendUser(string $_scope): Response
    {
        $this->openIdConnect->authenticate($_scope);

        $response = new Response('Something went wrong. You could not be logged in.');

        throw new ResponseException($response);
    }

    /**
     * Login Contao backend user.
     *
     * @throws \Exception
     *
     * @Route("/ssoauth/backend", name="swiss_alpine_club_sso_login_backend", defaults={"_scope" = "backend", "_token_check" = false})
     *
     * @throws \Exception
     */
    public function authenticateContaoBackendUser(string $_scope): Response
    {
        $this->openIdConnect->authenticate($_scope);

        $response = new Response('Something went wrong. You could not be logged in.');

        throw new ResponseException($response);
    }

    /**
     * @Route("/ssoauth/send_logout_endpoint", name="swiss_alpine_club_sso_login_send_logout_endpoint")
     */
    public function sendLogoutEndpointAction(): JsonResponse
    {
        $this->framework->initialize();

        /** @var System $configAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        $data = [
            'success' => 'true',
            'logout_endpoint_url' => $systemAdapter->getContainer()->getParameter('sac_oauth2_client.oidc.auth_provider_endpoint_logout'),
        ];

        return new JsonResponse($data);
    }
}
