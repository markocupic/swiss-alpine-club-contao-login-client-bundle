<?php

declare(strict_types=1);

/*
 * This file is part of Swiss Alpine Club Contao Login Client Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/swiss-alpine-club-contao-login-client-bundle
 */

use Contao\CoreBundle\DataContainer\PaletteManipulator;

PaletteManipulator::create()
	->addLegend('sso_login_legend', 'password_legend', PaletteManipulator::POSITION_BEFORE)
	->addField('ssoLoginAttempts', 'sso_login_legend', PaletteManipulator::POSITION_APPEND)
	->applyToPalette('login', 'tl_user')
	->applyToPalette('admin', 'tl_user')
	->applyToPalette('default', 'tl_user')
	->applyToPalette('group', 'tl_user')
	->applyToPalette('extend', 'tl_user')
	->applyToPalette('custom', 'tl_user');

// Fields
$GLOBALS['TL_DCA']['tl_user']['fields']['ssoLoginAttempts'] = [
	'exclude'   => true,
	'sorting'   => true,
	'filter'    => true,
	'flag'      => 1,
	'search'    => true,
	'inputType' => 'text',
	'eval'      => ['mandatory' => false, 'rgxp' => 'natural', 'maxlength' => 5, 'tl_class' => 'w50'],
	'sql'       => "smallint(5) unsigned NOT NULL default 0",
];
