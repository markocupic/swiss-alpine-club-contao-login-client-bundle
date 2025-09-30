<?php

declare(strict_types=1);

/*
 * This file is part of Swiss Alpine Club Contao Login Client Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/swiss-alpine-club-contao-login-client-bundle
 */

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/_oauth2_login/hitobito/frontend', name: self::ROUTE_FRONTEND, defaults: ['_scope' => 'frontend'])]
#[Route('/_oauth2_login/hitobito/backend', name: self::ROUTE_BACKEND, defaults: ['_scope' => 'backend'])]
class RedirectController extends AbstractController
{
    public const string ROUTE_BACKEND = 'sac_login_redirect_backend';

    public const string ROUTE_FRONTEND = 'sac_login_redirect_frontend';

    public function __invoke(Request $request, string $_scope): Response
    {
        // This point should never be reached.
        return new Response('Could not find an authenticator for this route!');
    }
}
