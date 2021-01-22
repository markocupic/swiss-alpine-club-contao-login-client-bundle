<?php

/*
 * This file is part of Swiss Alpine Club Contao Login Client Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/swiss-alpine-club-contao-login-client-bundle
 */

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\MemberGroupModel;

PaletteManipulator::create()
	->addLegend('sac_sso_login_settings', 'global_legend')
	->addField(array('SAC_SSO_LOGIN_CLIENT_ID'), 'sac_sso_login_settings', PaletteManipulator::POSITION_APPEND)
	->addField(array('SAC_SSO_LOGIN_CLIENT_SECRET'), 'sac_sso_login_settings', PaletteManipulator::POSITION_APPEND)
	->addField(array('SAC_SSO_LOGIN_REDIRECT_URI_FRONTEND'), 'sac_sso_login_settings', PaletteManipulator::POSITION_APPEND)
	->addField(array('SAC_SSO_LOGIN_REDIRECT_URI_BACKEND'), 'sac_sso_login_settings', PaletteManipulator::POSITION_APPEND)
	->addField(array('SAC_SSO_LOGIN_URL_AUTHORIZE'), 'sac_sso_login_settings', PaletteManipulator::POSITION_APPEND)
	->addField(array('SAC_SSO_LOGIN_URL_ACCESS_TOKEN'), 'sac_sso_login_settings', PaletteManipulator::POSITION_APPEND)
	->addField(array('SAC_SSO_LOGIN_URL_RESOURCE_OWNER_DETAILS'), 'sac_sso_login_settings', PaletteManipulator::POSITION_APPEND)
	->addField(array('SAC_SSO_LOGIN_URL_LOGOUT'), 'sac_sso_login_settings', PaletteManipulator::POSITION_APPEND)
	->addField(array('SAC_SSO_LOGIN_ADD_TO_MEMBER_GROUPS'), 'sac_sso_login_settings', PaletteManipulator::POSITION_APPEND)
	->addField(array('SAC_SSO_LOGIN_ENABLE_BACKEND_SSO'), 'sac_sso_login_settings', PaletteManipulator::POSITION_APPEND)
	->applyToPalette('default', 'tl_settings');

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_SSO_LOGIN_CLIENT_ID'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_settings']['SAC_SSO_LOGIN_CLIENT_ID'],
	'inputType' => 'text',
	'eval'      => array('mandatory' => true, 'decodeEntities' => true, 'tl_class' => 'w50'),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_SSO_LOGIN_CLIENT_SECRET'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_settings']['SAC_SSO_LOGIN_CLIENT_SECRET'],
	'inputType' => 'text',
	'eval'      => array('mandatory' => true, 'decodeEntities' => true, 'tl_class' => 'w50'),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_SSO_LOGIN_REDIRECT_URI_FRONTEND'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_settings']['SAC_SSO_LOGIN_REDIRECT_URI_FRONTEND'],
	'inputType' => 'text',
	'eval'      => array('mandatory' => true, 'decodeEntities' => true, 'tl_class' => 'w50'),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_SSO_LOGIN_REDIRECT_URI_BACKEND'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_settings']['SAC_SSO_LOGIN_REDIRECT_URI_BACKEND'],
	'inputType' => 'text',
	'eval'      => array('mandatory' => true, 'decodeEntities' => true, 'tl_class' => 'w50'),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_SSO_LOGIN_URL_AUTHORIZE'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_settings']['SAC_SSO_LOGIN_URL_AUTHORIZE'],
	'inputType' => 'text',
	'eval'      => array('mandatory' => true, 'decodeEntities' => true, 'tl_class' => 'w50'),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_SSO_LOGIN_URL_ACCESS_TOKEN'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_settings']['SAC_SSO_LOGIN_URL_ACCESS_TOKEN'],
	'inputType' => 'text',
	'eval'      => array('mandatory' => true, 'decodeEntities' => false, 'tl_class' => 'w50'),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_SSO_LOGIN_URL_RESOURCE_OWNER_DETAILS'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_settings']['SAC_SSO_LOGIN_URL_RESOURCE_OWNER_DETAILS'],
	'inputType' => 'text',
	'eval'      => array('mandatory' => true, 'decodeEntities' => false, 'tl_class' => 'w50'),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_SSO_LOGIN_URL_LOGOUT'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_settings']['SAC_SSO_LOGIN_URL_LOGOUT'],
	'inputType' => 'text',
	'eval'      => array('mandatory' => true, 'decodeEntities' => false, 'tl_class' => 'w50'),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_SSO_LOGIN_ADD_TO_MEMBER_GROUPS'] = array(
	'label'            => &$GLOBALS['TL_LANG']['tl_settings']['SAC_SSO_LOGIN_ADD_TO_MEMBER_GROUPS'],
	'inputType'        => 'select',
	'options_callback' => array('SwissAlpineClubContaoLoginClient_tl_settings', 'getMemberGroups'),
	'eval'             => array('multiple' => true, 'chosen' => true, 'tl_class' => 'clr'),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_SSO_LOGIN_ENABLE_BACKEND_SSO'] = array(
	'label'            => &$GLOBALS['TL_LANG']['tl_settings']['SAC_SSO_LOGIN_ENABLE_BACKEND_SSO'],
	'inputType'        => 'checkbox',
	'eval'             => array('tl_class' => 'clr'),
);

class SwissAlpineClubContaoLoginClient_tl_settings
{
	/**
	 * @return array
	 */
	function getMemberGroups(): array
	{
		$arrGr = array();
		$objGroup = MemberGroupModel::findAll();

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
