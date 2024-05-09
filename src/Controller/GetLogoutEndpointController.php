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

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/ssoauth/get_logout_endpoint', name: self::class)]
class GetLogoutEndpointController extends AbstractController
{
    public function __construct(
        #[Autowire('%sac_oauth2_client.oidc.auth_provider_endpoint_logout%')]
        private readonly string $authProviderLogoutEndpoint,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        $json = [
            'success' => 'true',
            'logout_endpoint_url' => $this->authProviderLogoutEndpoint,
        ];

        return new JsonResponse($json);
    }
}
