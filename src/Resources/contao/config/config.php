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
	$GLOBALS['TL_CSS'][] = \Contao\Environment::get('path').'/bundles/markocupicswissalpineclubcontaologinclient/css/backend.css';
}
/**
$GLOBALS['TL_HOOKS']['parseBackendTemplate'][] = [
    /\Markocupic\SwissAlpineClubContaoLoginClientBundle\BackendModule\DisplayAuthProviders::class, 'addServersToLoginPage'
];
**/

$GLOBALS['TL_JAVASCRIPT'][] = 'bundles/markocupicswissalpineclubcontaologinclient/js/ids-kill-session.js';

/**
 * Hooks
 */
$GLOBALS['TL_HOOKS']['postLogout'][] = array('Markocupic\SwissAlpineClubContaoLoginClientBundle\EventListener\Contao\PostLogoutListener', 'killSession');
