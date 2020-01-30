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

if (TL_MODE === 'BE') {
	$GLOBALS['TL_CSS'][] = \Contao\Environment::get('path').'/bundles/markocupicswissalpineclubloginclient/css/backend.css';
}

$GLOBALS['TL_HOOKS']['parseBackendTemplate'][] = [
    \Markocupic\SwissAlpineClubContaoLoginClientBundle\BackendModule\DisplayAuthProviders::class, 'addServersToLoginPage'
];

$GLOBALS['BE_MOD']['oidc_login']['oidc_login_auth_servers'] = [
    'tables'       => ['tl_oidc_server'],
];


$GLOBALS['TL_MODELS']['tl_oidc_server'] = \Markocupic\SwissAlpineClubContaoLoginClientBundle\Model\OidcServerModel::class;
