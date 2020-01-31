<?php

declare(strict_types=1);

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Controller\FrontendModule;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\ModuleModel;
use Contao\Template;
use Contao\FrontendUser;
use Contao\TourDifficultyCategoryModel;
use Contao\TourDifficultyModel;
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
        // Get logged in member object
        if (($user = $this->get('security.helper')->getUser()) instanceof FrontendUser)
        {
            $template->loggedInAs = sprintf($GLOBALS['TL_LANG']['MSC']['loggedInAs'], $user->username);
            $template->username = $user->username;
            $template->logout = true;
        }else{
            $redirectPage = $model->jumpTo > 0 ? PageModel::findByPk($model->jumpTo) : null;
            $targetPath = $redirectPage instanceof PageModel ? $redirectPage->getAbsoluteUrl() : $objPage->getAbsoluteUrl();
            $template->targetPath = urlencode($targetPath);
            $template->login = true;
        }


        return $template->getResponse();
    }
}
