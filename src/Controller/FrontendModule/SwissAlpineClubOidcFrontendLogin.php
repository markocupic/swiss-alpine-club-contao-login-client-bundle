<?php

declare(strict_types=1);

/*
 * This file is part of Swiss Alpine Club Contao Login Client Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
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
use Haste\Util\Url;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\Security;

/**
 * Class SwissAlpineClubOidcFrontendLogin.
 *
 * @FrontendModule("swiss_alpine_club_oidc_frontend_login", category="user")
 */
class SwissAlpineClubOidcFrontendLogin extends AbstractFrontendModuleController
{
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var PageModel
     */
    private $page;

    /**
     * SwissAlpineClubOidcFrontendLogin constructor.
     */
    public function __construct(ContaoFramework $framework)
    {
        $this->framework = $framework;
    }

    public function __invoke(Request $request, ModuleModel $model, string $section, array $classes = null, PageModel $page = null): Response
    {
        $this->page = $page;

        return parent::__invoke($request, $model, $section, $classes);
    }

    public static function getSubscribedServices(): array
    {
        $services = parent::getSubscribedServices();

        $services['contao.framework'] = ContaoFramework::class;
        $services['security.helper'] = Security::class;

        return $services;
    }

    protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
    {
        $translator = $this->get('translator');

        /** @var Environment $environmentAdapter */
        $environmentAdapter = $this->framework->getAdapter(Environment::class);

        /** @var PageModel $pageModelAdapter */
        $pageModelAdapter = $this->framework->getAdapter(PageModel::class);

        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        /** @var Url $urlAdapter */
        $urlAdapter = $this->framework->getAdapter(Url::class);

        // Get logged in member object
        if (($user = $this->get('security.helper')->getUser()) instanceof FrontendUser) {
            $template->loggedInAs = $translator->trans('MSC.loggedInAs', [$user->username], 'contao_default');
            $template->username = $user->username;
            $template->logout = true;
        } else {
            $strRedirect = $environmentAdapter->get('base').$environmentAdapter->get('request');

            if (!$model->redirectBack && $model->jumpTo > 0) {
                $redirectPage = $pageModelAdapter->findByPk($model->jumpTo);
                $strRedirect = $redirectPage instanceof PageModel ? $redirectPage->getAbsoluteUrl() : $strRedirect;
            }

            // Csrf token check is disabled by default
            $template->enableCsrfTokenCheck = $systemAdapter->getContainer()->getParameter('markocupic_sac_sso_login.oidc.enable_csrf_token_check');

            // Since Contao 4.9 urls are base64 encoded
            $template->targetPath = $stringUtilAdapter->specialchars(base64_encode($strRedirect));

            $failurePath = $urlAdapter->addQueryString('sso_error=true', $strRedirect);
            $template->failurePath = $stringUtilAdapter->specialchars(base64_encode($failurePath));

            $template->login = true;

            $template->btnLbl = empty($model->swiss_alpine_club_oidc_frontend_login_btn_lbl) ? $translator->trans('MSC.loginWithSacSso', [], 'contao_default') : $model->swiss_alpine_club_oidc_frontend_login_btn_lbl;

            /** @var RequestStack $requestStack */
            $requestStack = $this->get('request_stack');
            $request = $requestStack->getCurrentRequest();

            // Check for error messages & start session only if there was an error
            if ($request->query->has('sso_error')) {
                $session = $this->get('session');
                $flashBagKey = $systemAdapter->getContainer()->getParameter('markocupic_sac_sso_login.session.flash_bag_key');
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
