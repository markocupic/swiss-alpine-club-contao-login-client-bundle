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
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Framework\ContaoFramework;
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

#[AsFrontendModule(SwissAlpineClubOidcFrontendLogin::TYPE, category: 'user', template: 'mod_swiss_alpine_club_oidc_frontend_login')]
class SwissAlpineClubOidcFrontendLogin extends AbstractFrontendModuleController
{
    public const TYPE = 'swiss_alpine_club_oidc_frontend_login';

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Security $security,
        private readonly RequestStack $requestStack,
        private readonly TranslatorInterface $translator,
    ) {
    }

    protected function getResponse(Template $template, ModuleModel $model, Request $request): Response
    {
        if (($user = $this->security->getUser()) instanceof FrontendUser) {
            $template->has_logged_in_user = true;
            $template->user = $user;
        } else {
            // Get adapters
            $pageModelAdapter = $this->framework->getAdapter(PageModel::class);
            $systemAdapter = $this->framework->getAdapter(System::class);
            $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);
            $uriAdapter = $this->framework->getAdapter(Uri::class);

            $strRedirect = $this->requestStack->getCurrentRequest()->getUri();

            if (!$model->redirectBack && $model->jumpTo) {
                $redirectPage = $pageModelAdapter->findByPk($model->jumpTo);
                $strRedirect = $redirectPage instanceof PageModel ? $redirectPage->getAbsoluteUrl() : $strRedirect;
            }

            $template->target_path = $stringUtilAdapter->specialchars(base64_encode($strRedirect));
            $uri = $uriAdapter->fromString($request->getUri());
            $uri->addQueryParam('sso_error', 'true');
            $template->failure_path = $stringUtilAdapter->specialchars(base64_encode($uri->toString()));
            $template->has_logged_in_user = false;
            $template->btn_lbl = empty($model->swiss_alpine_club_oidc_frontend_login_btn_lbl) ? $this->translator->trans('MSC.loginWithSacSso', [], 'contao_default') : $model->swiss_alpine_club_oidc_frontend_login_btn_lbl;
            $template->error = $this->getErrorMessage();
            $template->enable_csrf_token_check = $systemAdapter->getContainer()->getParameter('sac_oauth2_client.oidc.enable_csrf_token_check');
        }

        return $template->getResponse();
    }

    /**
     * Retrieve first error message.
     *
     * @throws \Exception
     */
    private function getErrorMessage(): array|null
    {
        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);
        $container = $systemAdapter->getContainer();

        $flashBag = $this->requestStack
            ->getCurrentRequest()
            ->getSession()
            ->getFlashBag()
            ->get($container->getParameter('sac_oauth2_client.session.flash_bag_key'))
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
