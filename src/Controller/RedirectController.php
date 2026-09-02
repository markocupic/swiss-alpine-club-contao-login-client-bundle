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

use Codefog\HasteBundle\UrlParser;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\ErrorMessage\ErrorMessage;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\ErrorMessage\ErrorMessageManager;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\OAuth2\Client\OAuth2ClientFactory;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\Authenticator\Exception\MissingAuthCodeAuthenticationException;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\RedirectPathValidator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/_oauth2_login/hitobito/frontend', name: self::ROUTE_FRONTEND, defaults: ['_scope' => 'frontend'])]
#[Route('/_oauth2_login/hitobito/backend', name: self::ROUTE_BACKEND, defaults: ['_scope' => 'backend'])]
class RedirectController extends AbstractController
{
    public const string ROUTE_BACKEND = 'sac_login_redirect_backend';

    public const string ROUTE_FRONTEND = 'sac_login_redirect_frontend';

    public function __construct(
        #[Autowire('@markocupic.sac_oauth2_client.error_message.error_message_manager')]
        private readonly ErrorMessageManager $errorMessageManager,
        #[Autowire('@markocupic.sac_oauth2_client.oauth2.client.oauth2_client_factory')]
        private readonly OAuth2ClientFactory $oAuth2ClientFactory,
        private readonly RedirectPathValidator $redirectPathValidator,
        private readonly RouterInterface $router,
        private readonly ScopeMatcher $scopeMatcher,
        private readonly TranslatorInterface $translator,
        private readonly UrlParser $urlParser,
    ) {
    }

    /**
     * This route is handled by the HitobitoAuthenticator. We only get here, if the
     * authenticator does not support the request, e.g. if the user aborts the login
     * at the OAuth2 server and gets redirected back without an authorization code.
     */
    public function __invoke(Request $request, string $_scope): Response
    {
        $failurePath = null;

        try {
            $failurePath = $this->redirectPathValidator->getSafePath(
                $this->oAuth2ClientFactory->createOAuth2Client($request)->getFailurePath(),
                $request,
            );

            $this->addErrorMessage();
        } catch (\Exception) {
            // There is no session we could read the failure path from.
        }

        // Let's play it safe and make sure we always have a valid redirect URL.
        if (null === $failurePath) {
            if ($this->scopeMatcher->isBackendRequest($request)) {
                $failurePath = $this->router->generate('contao_backend', [], UrlGeneratorInterface::ABSOLUTE_URL);
            } else {
                $failurePath = $this->urlParser->addQueryString('sac-oidc-error=true', $request->getSchemeAndHttpHost());
            }
        }

        return new RedirectResponse($failurePath);
    }

    private function addErrorMessage(): void
    {
        $key = MissingAuthCodeAuthenticationException::KEY;

        $this->errorMessageManager->add2Flash(
            new ErrorMessage(
                ErrorMessage::LEVEL_ERROR,
                $this->translator->trans(\sprintf('ERR.sacOidcLoginError_%s_matter', $key), [], 'contao_default'),
                $this->translator->trans(\sprintf('ERR.sacOidcLoginError_%s_howToFix', $key), [], 'contao_default'),
                $this->translator->trans(\sprintf('ERR.sacOidcLoginError_%s_explain', $key), [], 'contao_default'),
            ),
        );
    }
}
