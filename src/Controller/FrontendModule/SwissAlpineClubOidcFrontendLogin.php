<?php

declare(strict_types=1);

/**
 * Swiss Alpine Club (SAC) Contao Login Client Bundle
 * Copyright (c) 2008-2020 Marko Cupic
 * @package swiss-alpine-club-contao-login-client-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/swiss-alpine-club-contao-login-client-bundle
 */

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Controller\FrontendModule;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FrontendUser;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\System;
use Contao\Template;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Authorization\AuthorizationHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\Security;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;

/**
 * Class SwissAlpineClubOidcFrontendLogin
 * @package Markocupic\SwissAlpineClubContaoLoginClientBundle\Controller\FrontendModule
 * @FrontendModule("swiss_alpine_club_oidc_frontend_login", category="user")
 */
class SwissAlpineClubOidcFrontendLogin extends AbstractFrontendModuleController
{
    /**
     * @var Session
     */
    private $session;

    /**
     * @var PageModel
     */
    private $page;

    /**
     * SwissAlpineClubOidcFrontendLogin constructor.
     * @param Session $session
     */
    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    /**
     * @param Request $request
     * @param ModuleModel $model
     * @param string $section
     * @param array|null $classes
     * @param PageModel|null $page
     * @return Response
     */
    public function __invoke(Request $request, ModuleModel $model, string $section, array $classes = null, PageModel $page = null): Response
    {
        $this->page = $page;
        return parent::__invoke($request, $model, $section, $classes);
    }

    /**
     * @return array
     */
    public static function getSubscribedServices(): array
    {
        $services = parent::getSubscribedServices();

        $services['contao.framework'] = ContaoFramework::class;
        $services['security.helper'] = Security::class;

        return $services;
    }

    /**
     * @param Template $template
     * @param ModuleModel $model
     * @param Request $request
     * @return null|Response
     */
    protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
    {
        $translator = $this->get('translator');
        // Get logged in member object
        if (($user = $this->get('security.helper')->getUser()) instanceof FrontendUser)
        {
            $template->loggedInAs = $translator->trans('MSC.loggedInAs', [$user->username], 'contao_default');
            $template->username = $user->username;
            $template->logout = true;
        }
        else
        {
            $redirectPage = $model->jumpTo > 0 ? PageModel::findByPk($model->jumpTo) : null;
            $targetPath = $redirectPage instanceof PageModel ? $redirectPage->getAbsoluteUrl() : $this->page->getAbsoluteUrl();
            $template->targetPath = $targetPath;
            $template->failurePath = $this->page->getAbsoluteUrl();
            $template->login = true;
            $template->btnLbl = empty($model->swiss_alpine_club_oidc_frontend_login_btn_lbl) ? $translator->trans('MSC.loginWithSacSso', [], 'contao_default') : $model->swiss_alpine_club_oidc_frontend_login_btn_lbl;

            // Check for error messages
            $flashBagKey = System::getContainer()->getParameter('swiss_alpine_club_contao_login_client.session.flash_bag_key');
            $flashBag = $this->session->getFlashBag()->get($flashBagKey);
            if (count($flashBag) > 0)
            {
                $arrError = [];
                foreach ($flashBag[0] as $k => $v)
                {
                    if ($k === 'level')
                    {
                        $arrError['bs-alert-class'] = ($v === 'error') ? 'danger' : $v;
                    }
                    $arrError[$k] = $v;
                }
                $template->error = $arrError;
            }
        }

        return $template->getResponse();
    }
}
