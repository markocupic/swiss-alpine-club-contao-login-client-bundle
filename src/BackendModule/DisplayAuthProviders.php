<?php

/**
 * Swiss Alpine Club Login Client Bundle
 * OpenId Connect Login via https://sac-cas.ch for Contao Frontend and Backend
 *
 * @package Markocupic\SwissAlpineClubContaoLoginClientBundle
 * @author    Marko Cupic, Oberkirch
 * @license   MIT
 * @copyright 2020 Marko Cupic
 */

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\BackendModule;

use Contao\Environment;
use Contao\BackendTemplate;
use Contao\System;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Model\OidcServerModel;

/**
 * Class DisplayAuthProviders
 * @package Markocupic\SwissAlpineClubContaoLoginClientBundle\BackendModule
 */
class DisplayAuthProviders extends System
{
    /**
     * Display option field in backend login
     *
     * @param string $strContent
     * @param string $strTemplate
     * @return string
     */
    public function addServersToLoginPage(string $strContent, string $strTemplate): string
    {
        if ($strTemplate === 'be_login')
        {
            $objTemplate = new BackendTemplate('mod_oidc_backend_login');
            $objTemplate->loginServers = OidcServerModel::findByLoginScope('backend');

            $searchString = '<div class="tl_info" id="javascript">';
            $strContent = str_replace($searchString, $objTemplate->parse() . $searchString, $strContent);

            $searchString = '</head>';
            $cssLink = '<link rel="stylesheet" href="' . Environment::get('path') . '/bundles/markocupicswissalpineclubloginclient/css/stylesheet.css">';
            $strContent = str_replace($searchString, $cssLink . $searchString, $strContent);
        }

        return $strContent;
    }
}
