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

use Contao\CoreBundle\Exception\ResponseException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\OpenIdConnect\OpenIdConnect;
use Safe\Exceptions\JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Login Contao frontend/backend user.
 *
 * @Route("/ssoauth/frontend", name="swiss_alpine_club_sso_login_frontend", defaults={"_scope" = "frontend", "_token_check" = false})
 * @Route("/ssoauth/backend", name="swiss_alpine_club_sso_login_backend", defaults={"_scope" = "backend", "_token_check" = false})
 */
class ContaoAuthenticationController extends AbstractController
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly OpenIdConnect $openIdConnect,
    ) {
    }

    /**
     * @throws JsonException
     */
    public function __invoke(string $_scope): Response
    {
        $this->framework->initialize('frontend' === $_scope);
        $this->openIdConnect->authenticate($_scope);

        $response = new Response('Something went wrong. You could not be logged in.');

        throw new ResponseException($response);
    }
}
