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
use Contao\ModuleModel;
use Contao\System;
use Contao\Template;
use Contao\FrontendUser;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Oauth\Oauth;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Contao\PageModel;
use Symfony\Component\Security\Core\Security;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;

/**
 * Class SwissAlpineSsoFrontendLogin
 * @package Markocupic\SwissAlpineClubContaoLoginClientBundle\Controller\FrontendModule
 * @FrontendModule("swiss_alpine_sso_frontend_login", category="user")
 */
class SwissAlpineSsoFrontendLogin extends AbstractFrontendModuleController
{
    /** @var Oauth */
    private $oauth;

    /** @var PageModel */
    private $page;

    public function __construct(Oauth $oauth)
    {
        $this->oauth = $oauth;
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
            $template->targetPath = urlencode($targetPath);
            $template->errorPath = urlencode($this->page->getAbsoluteUrl());
            $template->loginWithSacSso = $translator->trans('MSC.loginWithSacSso', [], 'contao_default');
            $template->login = true;

            // Check for error messages
            if ($this->oauth->hasFlashBagMessage())
            {
                $arrError = [];
                $flashBag = $this->oauth->getFlashBagMessage(0);
                foreach ($flashBag as $k => $v)
                {
                    $arrError[$k] = $v;
                }
                $template->error = $arrError;
            }
        }

        return $template->getResponse();
    }
}
