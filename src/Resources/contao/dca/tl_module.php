<?php

/**
 * Swiss Alpine Club (SAC) Contao Login Client Bundle
 * Copyright (c) 2008-2020 Marko Cupic
 * @package swiss-alpine-club-contao-login-client-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/swiss-alpine-club-contao-login-client-bundle
 */

$GLOBALS['TL_DCA']['tl_module']['palettes']['swiss_alpine_club_oidc_frontend_login'] = '{title_legend},name,headline,type;{button_legend},swiss_alpine_club_oidc_frontend_login_btn_lbl;{redirect_legend},jumpTo,redirectBack;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';

// Fields
$GLOBALS['TL_DCA']['tl_module']['fields']['swiss_alpine_club_oidc_frontend_login_btn_lbl'] = [
    'exclude'   => true,
    'sorting'   => true,
    'flag'      => 1,
    'search'    => true,
    'inputType' => 'text',
    'eval'      => ['mandatory' => false, 'maxlength' => 255, 'tl_class' => 'w50'],
    'sql'       => "varchar(255) NOT NULL default ''"
];
