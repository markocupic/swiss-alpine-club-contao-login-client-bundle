<?php

declare(strict_types=1);

/*
 * This file is part of Swiss Alpine Club Contao Login Client Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/swiss-alpine-club-contao-login-client-bundle
 */

use Contao\Controller;
use Contao\CoreBundle\DataContainer\PaletteManipulator;

$GLOBALS['TL_DCA']['tl_module']['palettes']['swiss_alpine_club_oidc_frontend_login'] = '{title_legend},name,headline,type;{button_legend},swiss_alpine_club_oidc_frontend_login_btn_lbl;{redirect_legend},jumpTo,redirectBack;{account_legend},swiss_alpine_club_oidc_add_to_fe_groups;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';

// Selectors
$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'swiss_alpine_club_oidc_add_module';

// Subpalettes
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['swiss_alpine_club_oidc_add_module'] = 'swiss_alpine_club_oidc_module';

// Load DCA
Controller::loadDataContainer('tl_content');

// Palettes
PaletteManipulator::create()
    ->addLegend('sac_sso_login_settings', 'title_legend_legend')
    ->addField(['swiss_alpine_club_oidc_add_module'], 'sac_sso_login_settings', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('login', 'tl_module');

// Fields
$GLOBALS['TL_DCA']['tl_module']['fields']['swiss_alpine_club_oidc_frontend_login_btn_lbl'] = [
    'exclude'   => true,
    'sorting'   => true,
    'flag'      => 1,
    'search'    => true,
    'inputType' => 'text',
    'eval'      => ['mandatory' => false, 'maxlength' => 255, 'tl_class' => 'w50'],
    'sql'       => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['swiss_alpine_club_oidc_add_to_fe_groups'] = [
    'exclude'    => true,
    'inputType'  => 'checkbox',
    'foreignKey' => 'tl_member_group.name',
    'eval'       => ['multiple' => true],
    'sql'        => 'blob NULL',
    'relation'   => ['type' => 'hasMany', 'load' => 'lazy'],
];

$GLOBALS['TL_DCA']['tl_module']['fields']['swiss_alpine_club_oidc_add_module'] = [
    'exclude'   => true,
    'inputType' => 'checkbox',
    'eval'      => ['submitOnChange' => true],
    'sql'       => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['swiss_alpine_club_oidc_module'] = [
    'exclude'          => true,
    'inputType'        => 'select',
    'options_callback' => ['tl_content', 'getModules'],
    'eval'             => ['mandatory' => true, 'chosen' => true, 'submitOnChange' => false, 'tl_class' => 'w50 wizard'],
    'wizard'           => [
        ['tl_content', 'editModule'],
    ],
    'sql'              => 'int(10) unsigned NOT NULL default 0',
];
