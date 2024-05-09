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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Controller\FrontendModule;

use Codefog\HasteBundle\UrlParser;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\FrontendUser;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Controller\SacLoginStartController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsFrontendModule(SwissAlpineClubOidcFrontendLogin::TYPE, category: 'user', template: 'mod_swiss_alpine_club_oidc_frontend_login')]
class SwissAlpineClubOidcFrontendLogin extends AbstractFrontendModuleController
{
    public const TYPE = 'swiss_alpine_club_oidc_frontend_login';

    public function __construct(
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
        if (($user = $this->security->getUser()) instanceof FrontendUser) {
            $template->set('has_logged_in_user', true);
            $template->set('user', $user);
        } else {
            // Get adapters
            $pageModelAdapter = $this->framework->getAdapter(PageModel::class);
            $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

            // Generate the form action
            $action = $this->router->generate(SacLoginStartController::LOGIN_ROUTE_FRONTEND, [], UrlGeneratorInterface::ABSOLUTE_URL);
            $template->set('action', $this->uriSigner->sign($action));

            // Set the target path
            $strRedirect = $request->getUri();

            if (!$model->redirectBack && $model->jumpTo) {
                $redirectPage = $pageModelAdapter->findByPk($model->jumpTo);
                $strRedirect = $redirectPage instanceof PageModel ? $redirectPage->getAbsoluteUrl() : $strRedirect;
            }

            $template->set('target_path', $stringUtilAdapter->specialchars(base64_encode($strRedirect)));

            // Set the failure path
            $uri = $this->urlParser->addQueryString('sso_error=true', $request->getUri());
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
     * Retrieve first error message.
     *
     * @throws \Exception
     */
    private function getErrorMessage(Request $request): array|null
    {
        $flashBag = $request
            ->getSession()
            ->getFlashBag()
            ->get($this->sessionFlashBagKey)
        ;

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
