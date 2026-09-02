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

use Contao\CoreBundle\Routing\ScopeMatcher;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\OAuth2\Client\OAuth2ClientFactory;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\RedirectPathValidator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;

#[Route('/ssoauth/start/backend', name: self::LOGIN_ROUTE_BACKEND, defaults: ['_scope' => 'backend', '_token_check' => false])]
#[Route('/ssoauth/start/frontend', name: self::LOGIN_ROUTE_FRONTEND, defaults: ['_scope' => 'frontend', '_token_check' => false])]
class StartController extends AbstractController
{
    public const string LOGIN_ROUTE_BACKEND = 'swiss_alpine_club_login_backend_start';

    public const string LOGIN_ROUTE_FRONTEND = 'swiss_alpine_club_login_frontend_start';

    public function __construct(
        #[Autowire(service: 'markocupic.sac_oauth2_client.oauth2.security.authenticator.hitobito_authenticator')]
        private readonly AuthenticatorInterface $authenticator,
        #[Autowire(service: 'markocupic.sac_oauth2_client.oauth2.client.oauth2_client_factory')]
        private readonly OAuth2ClientFactory $oAuth2ClientFactory,
        private readonly RedirectPathValidator $redirectPathValidator,
        private readonly RouterInterface $router,
        private readonly ScopeMatcher $scopeMatcher,
        private readonly UriSigner $uriSigner,
    ) {
    }

    public function __invoke(Request $request, string $_scope): Response
    {
        if (!$this->uriSigner->checkRequest($request)) {
            return new JsonResponse(['message' => 'Access denied.'], Response::HTTP_BAD_REQUEST);
        }

        if ($this->scopeMatcher->isBackendRequest($request)) {
            $targetPath = $request->get('_target_path', base64_encode($this->router->generate('contao_backend', [], UrlGeneratorInterface::ABSOLUTE_URL)));
            $failurePath = $request->get('_failure_path', base64_encode($this->router->generate('contao_backend', [], UrlGeneratorInterface::ABSOLUTE_URL)));
        } else {
            // Frontend: If there is an authentication error, Contao will redirect the user
            // back to the login form
            $failurePath = $request->get('_failure_path');
            $targetPath = $request->get('_target_path', base64_encode($request->getSchemeAndHttpHost()));
        }

        // Both paths are base64 encoded and user supplied, therefore we have to make
        // sure they point to the current host. Otherwise, we would allow an attacker to
        // use the login flow as an open redirect.
        if (!\is_string($targetPath) || null === $this->redirectPathValidator->getSafePath($targetPath, $request)) {
            return new Response('Invalid request. Target path is missing or not allowed.', Response::HTTP_BAD_REQUEST);
        }

        if (!\is_string($failurePath) || null === $this->redirectPathValidator->getSafePath($failurePath, $request)) {
            return new Response('Invalid request. Failure path is missing or not allowed.', Response::HTTP_BAD_REQUEST);
        }

        $oAuthClient = $this->oAuth2ClientFactory->createOAuth2Client($request);

        // Write _target_path, _always_use_target_path and _failure_path to the session
        $oAuthClient->setTargetPath($targetPath);
        $oAuthClient->setAlwaysUseTargetPath((bool) $request->get('_always_use_target_path', '0'));
        $oAuthClient->setFailurePath($failurePath);

        return $this->authenticator->authorize($request);
    }
}
