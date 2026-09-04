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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Controller\FrontendModule;

use Codefog\HasteBundle\UrlParser;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\FrontendUser;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Controller\StartController;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\Authenticator\HitobitoAuthenticator;
use Scheb\TwoFactorBundle\Security\TwoFactor\Event\TwoFactorAuthenticationEvent;
use Scheb\TwoFactorBundle\Security\TwoFactor\Event\TwoFactorAuthenticationEvents;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsFrontendModule(category: 'user')]
class SacOauthFrontendLoginController extends AbstractFrontendModuleController
{
    use TargetPathTrait;

    public const string TYPE = 'sac_oauth_frontend_login';

    public function __construct(
        private readonly ContaoCsrfTokenManager $csrfTokenManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ContaoFramework $framework,
        private readonly RouterInterface $router,
        private readonly Security $security,
        private readonly TranslatorInterface $translator,
        private readonly UriSigner $uriSigner,
        private readonly UrlParser $urlParser,
        #[Autowire('%sac_oauth2_client.session.flash_bag_key%')]
        private readonly string $sessionFlashBagKey,
    ) {
    }

    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        // A member who has passed the SSO login but still owes the second factor holds
        // a TwoFactorToken. Security::getUser() already returns the member behind it,
        // so without this branch the module would show the "you are logged in" box and
        // the code could never be entered. Contao's own login module does the same, see
        // Contao\ModuleLogin::compile().
        if ($this->security->isGranted('IS_AUTHENTICATED_2FA_IN_PROGRESS')) {
            return $this->getTwoFactorResponse($template, $request);
        }

        if (($user = $this->security->getUser()) instanceof FrontendUser) {
            $template->set('has_logged_in_user', true);
            $template->set('user', $user);
        } else {
            // Get adapters
            $pageModelAdapter = $this->framework->getAdapter(PageModel::class);
            $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

            // Generate the form action
            $action = $this->router->generate(StartController::LOGIN_ROUTE_FRONTEND, [], UrlGeneratorInterface::ABSOLUTE_URL);
            $template->set('action', $this->uriSigner->sign($action));

            // Set the target path
            $strRedirect = $request->getUri();

            if (!$model->redirectBack && $model->jumpTo) {
                $redirectPage = $pageModelAdapter->findById($model->jumpTo);
                $strRedirect = $redirectPage instanceof PageModel ? $redirectPage->getAbsoluteUrl() : $strRedirect;
            }

            $template->set('target_path', $stringUtilAdapter->specialchars(base64_encode($strRedirect)));

            // Set the failure path
            $uri = $this->urlParser->addQueryString('sac-oidc-error=true', $request->getUri());
            $template->set('failure_path', $stringUtilAdapter->specialchars(base64_encode($uri)));

            // Do not show the login form if there is a logged in frontend user.
            $template->set('has_logged_in_user', false);

            // Get the button label
            $template->set('btn_lbl', empty($model->swiss_alpine_club_oidc_frontend_login_btn_lbl) ? $this->translator->trans('MSC.loginWithSacSso', [], 'contao_default') : $model->swiss_alpine_club_oidc_frontend_login_btn_lbl);

            // Get login error messages from session
            $template->set('error', $this->getErrorMessage($request));
        }

        return $template->getResponse();
    }

    /**
     * Render Contao's second factor challenge.
     *
     * The form posts to the current page with "FORM_SUBMIT=tl_login", because that is
     * what Contao's ContaoLoginAuthenticator listens for. It hands a request over to
     * the two factor authenticator as soon as a TwoFactorToken is pending, so no
     * password is involved here.
     */
    private function getTwoFactorResponse(FragmentTemplate $template, Request $request): Response
    {
        $token = $this->security->getToken();

        if (null !== $token) {
            // Let the two factor providers prepare themselves, e.g. to send out a code.
            $this->eventDispatcher->dispatch(
                new TwoFactorAuthenticationEvent($request, $token),
                TwoFactorAuthenticationEvents::FORM,
            );
        }

        // Contao's AuthenticationSuccessHandler insists on a "_target_path" form field
        // and answers with a 400 Bad Request without it. Where the member wanted to go
        // was stored by HitobitoAuthenticator::onAuthenticationSuccess() under its own
        // key - the firewall's target path points at the page the member is parked on
        // while the code is entered, which is a different thing.
        $targetPath = $request->getSession()->get(HitobitoAuthenticator::SESSION_KEY_TWO_FACTOR_TARGET_PATH);

        if (!\is_string($targetPath) || '' === $targetPath) {
            $firewallName = $this->security->getFirewallConfig($request)?->getName();
            $targetPath = null !== $firewallName ? $this->getTargetPath($request->getSession(), $firewallName) : null;
        }

        $template->set('has_logged_in_user', false);
        $template->set('two_factor_in_progress', true);
        $template->set('action', $request->getUri());
        $template->set('request_token', $this->csrfTokenManager->getDefaultTokenValue());
        $template->set('target_path', base64_encode($targetPath ?? $request->getUri()));
        $template->set('cancel_url', $this->router->generate('contao_frontend_logout'));

        return $template->getResponse();
    }

    /**
     * Retrieve first error message.
     *
     * @throws \Exception
     */
    private function getErrorMessage(Request $request): array|null
    {
        $session = $request->getSession();

        if (!$session instanceof FlashBagAwareSessionInterface) {
            return null;
        }

        $flashBag = $session->getFlashBag()->get($this->sessionFlashBagKey);

        if (!empty($flashBag)) {
            $arrError = [];

            foreach ($flashBag[0] as $k => $v) {
                $arrError[$k] = $v;
            }

            return $arrError;
        }

        return null;
    }
}
