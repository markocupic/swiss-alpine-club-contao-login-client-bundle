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

use Contao\BackendUser;
use Contao\CoreBundle\ContaoCoreBundle;
use Contao\FrontendUser;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\RedirectPathValidator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class LogoutController extends AbstractController
{
    public function __construct(
        private readonly Connection $connection,
        private readonly RedirectPathValidator $redirectPathValidator,
        private readonly Security $security,
        private readonly RouterInterface $router,
        #[Autowire('%sac_oauth2_client.oidc.auth_provider_endpoint_logout%')]
        private readonly string $logoutEndpoint,
    ) {
    }

    /**
     * See:
     * https://saccas.atlassian.net/wiki/spaces/DOC/pages/4491673605/Anleitung+SAC+Login+OIDC#%C3%9Cbersicht-verf%C3%BCgbare-OIDC-Scopes.
     */
    #[Route('/_oauth2_login/hitobito/backend/logout', name: self::class.'Backend', defaults: ['_scope' => 'backend'])]
    #[Route('/_oauth2_login/hitobito/frontend/logout', name: self::class.'Frontend', defaults: ['_scope' => 'frontend'])]
    public function logout(Request $request, string $_scope): JsonResponse
    {
        $user = $this->security->getUser();

        if (!$user instanceof BackendUser && !$user instanceof FrontendUser) {
            $json = [
                'status' => 'warning',
                'error' => \sprintf('No Contao %s User found.', $_scope),
                'withIdToken' => false,
                'logoutUri' => $this->getPostLoginUri($_scope, $request),
            ];

            return new JsonResponse($json);
        }

        $idToken = $this->getIdToken($request, $_scope);

        // https://portal-test.sac-cas.ch/oidc/logout?id_token_hint=xyz&post_logout_redirect_uri=https%3A%2F%2Fmydomain.com%2Fcontao%2Flogout
        $url = \sprintf(
            '%s?id_token_hint=%s&post_logout_redirect_uri=%s',
            $this->logoutEndpoint,
            $idToken,
            urlencode($this->getPostLoginUri($_scope, $request)),
        );

        $json = [
            'status' => !empty($idToken) ? 'success' : 'warning',
            'error' => empty($idToken) ? 'ID token not found.' : null,
            'withIdToken' => !empty($idToken),
            'logoutUri' => !empty($idToken) ? $url : $this->getPostLoginUri($_scope, $request),
        ];

        return new JsonResponse($json);
    }

    private function getIdToken(Request $request, string $scope): string|null
    {
        $uuid = $this->getLoginSessionUuid($request, $scope);

        if (empty($uuid)) {
            return null;
        }

        $idToken = $this->getIdTokenFromSessionUuid($uuid);

        return empty($idToken) ? null : $idToken;
    }

    private function getLoginSessionUuid(Request $request, string $scope): string|null
    {
        $session = $request->getSession();

        if ('backend' === $scope) {
            $uuid = $session->get('sac_login_session_backend');
        } else {
            $uuid = $session->get('sac_login_session_frontend');
        }

        return $uuid;
    }

    private function getIdTokenFromSessionUuid(string $uuid): string|null
    {
        try {
            $idToken = $this->connection->fetchOne(
                'SELECT id_token FROM tl_sac_login_session WHERE uuid = ?',
                [$uuid],
                [Types::STRING],
            );
        } catch (\Exception) {
            return null;
        }

        return empty($idToken) ? null : $idToken;
    }

    private function getPostLoginUri(string $scope, Request $request): string
    {
        $postLogoutRedirectUri = $request->query->get('post_logout_redirect_uri');

        if (\is_string($postLogoutRedirectUri)) {
            $decoded = $this->redirectPathValidator->getSafePath($postLogoutRedirectUri, $request);

            if (null !== $decoded) {
                // The identity provider needs an absolute url.
                return str_starts_with($decoded, '/') ? $request->getSchemeAndHttpHost().$decoded : $decoded;
            }
        }

        if (ContaoCoreBundle::SCOPE_BACKEND === $scope) {
            return $this->router->generate('contao_backend_logout', [], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        return $request->getSchemeAndHttpHost();
    }
}
