<?php

/**
 * Swiss Alpine Club (SAC) Contao Login Client Bundle
 * Copyright (c) 2008-2020 Marko Cupic
 * @package swiss-alpine-club-contao-login-client-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/swiss-alpine-club-contao-login-client-bundle
 */

use Contao\CoreBundle\DataContainer\PaletteManipulator;

PaletteManipulator::create()
    ->addLegend('sac_sso_login_settings', 'global_legend')
    ->addField(['SAC_SSO_LOGIN_CLIENT_ID'], 'sac_sso_login_settings', PaletteManipulator::POSITION_APPEND)
    ->addField(['SAC_SSO_LOGIN_CLIENT_SECRET'], 'sac_sso_login_settings', PaletteManipulator::POSITION_APPEND)
    ->addField(['SAC_SSO_LOGIN_REDIRECT_URI_FRONTEND'], 'sac_sso_login_settings', PaletteManipulator::POSITION_APPEND)
    ->addField(['SAC_SSO_LOGIN_REDIRECT_URI_BACKEND'], 'sac_sso_login_settings', PaletteManipulator::POSITION_APPEND)
    ->addField(['SAC_SSO_LOGIN_URL_AUTHORIZE'], 'sac_sso_login_settings', PaletteManipulator::POSITION_APPEND)
    ->addField(['SAC_SSO_LOGIN_URL_ACCESS_TOKEN'], 'sac_sso_login_settings', PaletteManipulator::POSITION_APPEND)
    ->addField(['SAC_SSO_LOGIN_URL_RESOURCE_OWNER_DETAILS'], 'sac_sso_login_settings', PaletteManipulator::POSITION_APPEND)
    ->addField(['SAC_SSO_LOGIN_ADD_TO_MEMBER_GROUPS'], 'sac_sso_login_settings', PaletteManipulator::POSITION_APPEND)
    ->addField(['SAC_SSO_LOGIN_ENABLE_BACKEND_SSO'], 'sac_sso_login_settings', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('default', 'tl_settings');

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_SSO_LOGIN_CLIENT_ID'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_settings']['SAC_SSO_LOGIN_CLIENT_ID'],
    'inputType' => 'text',
    'eval'      => ['mandatory' => true, 'decodeEntities' => true, 'tl_class' => 'w50'],
];

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_SSO_LOGIN_CLIENT_SECRET'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_settings']['SAC_SSO_LOGIN_CLIENT_SECRET'],
    'inputType' => 'text',
    'eval'      => ['mandatory' => true, 'decodeEntities' => true, 'tl_class' => 'w50'],
];

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_SSO_LOGIN_REDIRECT_URI_FRONTEND'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_settings']['SAC_SSO_LOGIN_REDIRECT_URI_FRONTEND'],
    'inputType' => 'text',
    'eval'      => ['mandatory' => true, 'decodeEntities' => true, 'tl_class' => 'w50'],
];

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_SSO_LOGIN_REDIRECT_URI_BACKEND'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_settings']['SAC_SSO_LOGIN_REDIRECT_URI_BACKEND'],
    'inputType' => 'text',
    'eval'      => ['mandatory' => true, 'decodeEntities' => true, 'tl_class' => 'w50'],
];

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_SSO_LOGIN_URL_AUTHORIZE'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_settings']['SAC_SSO_LOGIN_URL_AUTHORIZE'],
    'inputType' => 'text',
    'eval'      => ['mandatory' => true, 'decodeEntities' => true, 'tl_class' => 'w50'],
];

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_SSO_LOGIN_URL_ACCESS_TOKEN'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_settings']['SAC_SSO_LOGIN_URL_ACCESS_TOKEN'],
    'inputType' => 'text',
    'eval'      => ['mandatory' => true, 'decodeEntities' => false, 'tl_class' => 'w50'],
];

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_SSO_LOGIN_URL_RESOURCE_OWNER_DETAILS'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_settings']['SAC_SSO_LOGIN_URL_RESOURCE_OWNER_DETAILS'],
    'inputType' => 'text',
    'eval'      => ['mandatory' => true, 'decodeEntities' => false, 'tl_class' => 'w50'],
];

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_SSO_LOGIN_ADD_TO_MEMBER_GROUPS'] = [
    'label'            => &$GLOBALS['TL_LANG']['tl_settings']['SAC_SSO_LOGIN_ADD_TO_MEMBER_GROUPS'],
    'inputType'        => 'select',
    'options_callback' => ['SwissAlpineClubContaoLoginClient_tl_settings', 'getMemberGroups'],
    'eval'             => ['multiple' => true, 'chosen' => true, 'tl_class' => 'clr'],
];

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_SSO_LOGIN_ENABLE_BACKEND_SSO'] = [
    'label'            => &$GLOBALS['TL_LANG']['tl_settings']['SAC_SSO_LOGIN_ENABLE_BACKEND_SSO'],
    'inputType'        => 'checkbox',
    'eval'             => ['tl_class' => 'clr'],
];

class SwissAlpineClubContaoLoginClient_tl_settings
{
    /**
     * @return array
     */
    function getMemberGroups(): array
    {
        $arrGr = [];
        $objGroup = \Contao\MemberGroupModel::findAll();
        if ($objGroup !== null)
        {
            while ($objGroup->next())
            {
                $arrGr[$objGroup->id] = $objGroup->name;
            }
        }
        return $arrGr;
    }
}

