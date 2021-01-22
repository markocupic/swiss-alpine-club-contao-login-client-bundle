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

use Contao\Environment;

if (TL_MODE === 'BE')
{
	$GLOBALS['TL_CSS'][] = Environment::get('path') . '/bundles/markocupicswissalpineclubcontaologinclient/css/backend.css';
}

$GLOBALS['TL_JAVASCRIPT'][] = 'bundles/markocupicswissalpineclubcontaologinclient/js/ids-kill-session.js|static';

/**
 * Hooks
 */
$GLOBALS['TL_HOOKS']['postLogout'][] = array('Markocupic\SwissAlpineClubContaoLoginClientBundle\EventListener\Contao\PostLogoutListener', 'killSession');
$GLOBALS['TL_HOOKS']['parseBackendTemplate'][] = array('Markocupic\SwissAlpineClubContaoLoginClientBundle\EventListener\Contao\ParseBackendTemplateListener', 'addLoginButtonToTemplate');
