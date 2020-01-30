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


/**
 * Fields
 */
$GLOBALS['TL_LANG']['tl_oidc_server']['name'] = ['Name', 'Name des Login Servers. Wird auf der Contao-Login-Seite angezeigt.'];
$GLOBALS['TL_LANG']['tl_oidc_server']['url_authorize'] = ['Url: Autorisierung', 'Url der OAuth2 Autorisierung'];
$GLOBALS['TL_LANG']['tl_oidc_server']['url_access_token'] = ['Url: Access Token', 'Url zum Abruf des Access Tokens'];
$GLOBALS['TL_LANG']['tl_oidc_server']['url_resource_owner_details'] = ['Url: Resource Owner Details', 'Url zum Abruf der Resource Owner Details'];
$GLOBALS['TL_LANG']['tl_oidc_server']['public_id'] = ['Öffentlicher Schlüssel', 'Öffentlicher Schlüssel für diesen OAuth2 Server'];
$GLOBALS['TL_LANG']['tl_oidc_server']['secret'] = ['Geheimer Schlüssel', 'Geheimer Schlüssel für diesen OAuth2 Server'];
$GLOBALS['TL_LANG']['tl_oidc_server']['login_scope'] = ['Contao-Login-Zone', 'Wählen Sie die Zone (Backend/Frontend) aus.'];

/**
 * Legends
 */
$GLOBALS['TL_LANG']['tl_oidc_server']['server_auth_legend'] = 'Authentifizierung';


/**
 * Buttons
 */
$GLOBALS['TL_LANG']['tl_oidc_server']['new']    = array('Login-Server anlegen', 'Einen neuen Superlogin Server anlegen ');
$GLOBALS['TL_LANG']['tl_oidc_server']['show']   = array('Details', 'Zeige die Details zu Server %s');
$GLOBALS['TL_LANG']['tl_oidc_server']['edit']   = array('Bearbeiten ', 'Bearbeite Server ID %s');
$GLOBALS['TL_LANG']['tl_oidc_server']['cut']    = array('Verschieben ', 'Verschiebe Server ID %s');
$GLOBALS['TL_LANG']['tl_oidc_server']['copy']   = array('Duplizieren ', 'Dupliziere Server ID %s');
$GLOBALS['TL_LANG']['tl_oidc_server']['delete'] = array('L&ouml;schen ', 'L&ouml;sche Server ID %s');
