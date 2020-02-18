<?php

/**
 * Swiss Alpine Club (SAC) Contao Login Client Bundle
 * Copyright (c) 2008-2020 Marko Cupic
 * @package swiss-alpine-club-contao-login-client-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/swiss-alpine-club-contao-login-client-bundle
 */

/**
 * Miscellaneous
 */
$GLOBALS['TL_LANG']['MSC']['loginWithSacSso'] = 'Mit SAC Login anmelden';

// Error management
$GLOBALS['TL_LANG']['MSC']['errorMatter'] = 'Fehlermeldung';
$GLOBALS['TL_LANG']['MSC']['errorHowToFix'] = 'Was kann ich tun?';
$GLOBALS['TL_LANG']['MSC']['errorExplain'] = 'Erklärung';

$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_invalidState_matter'] = 'Die Überprüfung Ihrer Daten vom Identity Provider hat fehlgeschlagen. Fehlercode: ungültiger state!';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_invalidState_howToFix'] = 'Bitte überprüfen Sie die Schreibweise Ihrer Benutzereingaben.';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_invalidState_explain'] = '';

$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_accountDisabled_matter'] = 'Hallo %s{{br}}Schön bist du hier. Leider hat die Überprüfung deiner vom Identity Provider an uns übermittelten Daten fehlgeschlagen.';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_accountDisabled_howToFix'] = '';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_accountDisabled_explain'] = 'Dein Konto wurde leider deaktiviert und kann im Moment nicht verwendet werden.';

$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_userDoesNotExist_matter'] = 'Hallo %s{{br}}Schön bist du hier. Leider hat die Überprüfung deiner vom Identity Provider an uns übermittelten Daten fehlgeschlagen.';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_userDoesNotExist_howToFix'] = 'Falls du soeben/erst kürzlich eine Neumitgliedschaft beantragt hast, dann warte bitten einen Tag und versuche dich danach noch einmal hier einzuloggen.';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_userDoesNotExist_explain'] = 'Leider dauert es mindestens einen Tag bis uns von der Zentralstelle deine Mitgliedschaft bestätigt wird.';

$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_backendUserNotFound_matter'] = 'Hallo %s{{br}}Schön bist du hier. Leider wurde dein Konto nicht gefunden. Wenn du meinst, dass es sich um einen Fehler handelt, dann melde dich mit deinem Anliegen bei der Geschäftsstelle.';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_backendUserNotFound_howToFix'] = '';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_backendUserNotFound_explain'] = '';

$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_userIsNotSacMember_matter'] = 'Hallo %s{{br}}Schön bist du hier. Leider hat dein Loginversuch nicht geklappt, weil du nicht Mitglied der SAC Sektion Pilatus zu sein scheinst.';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_userIsNotSacMember_howToFix'] = 'Um von allen Services auf unserem Online-Portal zu profitieren, {{br}}- kannst du eine Zusatzmitgliedschaft bei SAC Pilatus abschliessen, {{br}}- oder einen Sektionswechsel zu SAC Pilatus beantragen.{{br}}{{br}}Dazu darfst du dich sehr gerne bei unserer Geschäftsstelle melden.';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_userIsNotSacMember_explain'] = '';

$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_userIsNotMemberOfAllowedSection_matter'] = 'Hallo %s{{br}}Schön bist du hier. Leider hat dein Loginversuch nicht geklappt, weil du nicht Mitglied der SAC Sektion Pilatus zu sein scheinst.';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_userIsNotMemberOfAllowedSection_howToFix'] = 'Um von allen Services auf unserem Online-Portal zu profitieren, {{br}}- kannst du eine Zusatzmitgliedschaft bei SAC Pilatus abschliessen, {{br}}- oder einen Sektionswechsel zu SAC Pilatus beantragen.{{br}}{{br}}Dazu darfst du dich sehr gerne bei unserer Geschäftsstelle melden.';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_userIsNotMemberOfAllowedSection_explain'] = '';

$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_invalidUsername_matter'] = 'Hallo{{br}}Schön bist du hier. Leider hat die Überprüfung deiner vom Identity Provider an uns übermittelten Daten fehlgeschlagen.';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_invalidUsername_howToFix'] = 'Bitte überprüfe die Richtigkeit deiner Eingaben.';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_invalidUsername_explain'] = '';

$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_invalidEmail_matter'] = 'Hallo %s{{br}}Schön bist du hier. Leider hat die Überprüfung deiner vom Identity Provider an uns übermittelten Daten fehlgeschlagen.';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_invalidEmail_howToFix'] = 'Du hast noch keine gültige E-Mail-Adresse hinterlegt. Bitte logge dich auf https:://www.sac-cas.ch mit deinem Account ein und hinterlege deine E-Mail-Adresse.';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_invalidEmail_explain'] = 'Einige Anwendungen (z.B. Event-Tool) auf diesem Portal setzen eine gültige E-Mail-Adresse voraus.';
