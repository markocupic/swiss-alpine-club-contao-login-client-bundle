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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Controller\FrontendModule;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;
use Contao\Environment;
use Contao\FrontendUser;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Contao\Template;
use JustSteveKing\UriBuilder\Uri;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @FrontendModule("swiss_alpine_club_oidc_frontend_login", category="user")
 */
class SwissAlpineClubOidcFrontendLogin extends AbstractFrontendModuleController
{
    private ContaoFramework $framework;
    private Security $security;
    private RequestStack $requestStack;
    private TranslatorInterface $translator;

    public function __construct(ContaoFramework $framework, Security $security, RequestStack $requestStack, TranslatorInterface $translator)
    {
        $this->framework = $framework;
        $this->security = $security;
        $this->requestStack = $requestStack;
        $this->translator = $translator;
    }

    protected function getResponse(Template $template, ModuleModel $model, Request $request): Response|null
    {
        /** @var Environment $environmentAdapter */
        $environmentAdapter = $this->framework->getAdapter(Environment::class);

        /** @var PageModel $pageModelAdapter */
        $pageModelAdapter = $this->framework->getAdapter(PageModel::class);

        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        /** @var Uri $urlAdapter */
        $uriAdapter = $this->framework->getAdapter(Uri::class);

        // Get logged in member object
        if (($user = $this->security->getUser()) instanceof FrontendUser) {
            $template->loggedInAs = $this->translator->trans('MSC.loggedInAs', [$user->username], 'contao_default');
            $template->username = $user->username;
            $template->logout = true;
        } else {
            $strRedirect = $environmentAdapter->get('base').$environmentAdapter->get('request');

            if (!$model->redirectBack && $model->jumpTo > 0) {
                $redirectPage = $pageModelAdapter->findByPk($model->jumpTo);
                $strRedirect = $redirectPage instanceof PageModel ? $redirectPage->getAbsoluteUrl() : $strRedirect;
            }

            // Csrf token check is disabled by default
            $template->enableCsrfTokenCheck = $systemAdapter->getContainer()->getParameter('sac_oauth2_client.oidc.enable_csrf_token_check');

            // Since Contao 4.9 urls are base64 encoded
            $template->targetPath = $stringUtilAdapter->specialchars(base64_encode($strRedirect));

            $uri = $uriAdapter->fromString($request->getUri());
            $uri->addQueryParam('sso_error', 'true');
            $template->failurePath = $stringUtilAdapter->specialchars(base64_encode($uri->toString()));

            $template->login = true;

            $template->btnLbl = empty($model->swiss_alpine_club_oidc_frontend_login_btn_lbl) ? $this->translator->trans('MSC.loginWithSacSso', [], 'contao_default') : $model->swiss_alpine_club_oidc_frontend_login_btn_lbl;

            $request = $this->requestStack->getCurrentRequest();

            // Check for error messages & start session only if there was an error
            if ($request->query->has('sso_error')) {
                $session = $request->getSession();
                $flashBagKey = $systemAdapter->getContainer()->getParameter('sac_oauth2_client.session.flash_bag_key');
                $flashBag = $session->getFlashBag()->get($flashBagKey);

                if (\count($flashBag) > 0) {
                    $arrError = [];

                    foreach ($flashBag[0] as $k => $v) {
                        if ('level' === $k) {
                            $arrError['bs-alert-class'] = 'error' === $v ? 'danger' : $v;
                        }
                        $arrError[$k] = $v;
                    }
                    $template->error = $arrError;
                }
            }
        }

        return $template->getResponse();
    }
}
