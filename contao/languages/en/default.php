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

/*
 * Miscellaneous
 */
$GLOBALS['TL_LANG']['MSC']['loginWithSacSso'] = 'Mit SAC Login anmelden';

// Error management
$GLOBALS['TL_LANG']['MSC']['infoMatter'] = 'Information';
$GLOBALS['TL_LANG']['MSC']['warningMatter'] = 'Login leider nicht möglich';
$GLOBALS['TL_LANG']['MSC']['errorMatter'] = 'Login-Fehler';
$GLOBALS['TL_LANG']['MSC']['errorHowToFix'] = 'Was kann ich tun?';
$GLOBALS['TL_LANG']['MSC']['errorExplain'] = 'Erklärung';
$GLOBALS['TL_LANG']['MSC']['or'] = 'or';

$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_invalidState_matter'] = 'Leider ist es beim Versuch dich einzuloggen zu einem Fehler gekommen (ungültiger State).';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_invalidState_howToFix'] = 'Bitte probiere dich nochmals einzuloggen.';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_invalidState_explain'] = '';

$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_missingAuthCode_matter'] = 'Leider ist es beim Versuch dich einzuloggen zu einem Fehler gekommen (fehlender Auth Code).';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_missingAuthCode_howToFix'] = 'Bitte probiere dich nochmals einzuloggen.';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_missingAuthCode_explain'] = '';

$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_resourceOwnerHasInvalidUuid_matter'] = 'Hallo{{br}}Schön bist du hier. Leider hat die Überprüfung deiner vom Identity Provider an uns übermittelten Daten fehlgeschlagen. Es wurde keine UUID übermittelt.';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_resourceOwnerHasInvalidUuid_howToFix'] = 'Bitte logge dich auf https://www.sac-cas.ch mit deinem Account ein und überprüfe die Richtigkeit deiner Eingaben.';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_resourceOwnerHasInvalidUuid_explain'] = '';

$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_resourceOwnerHasInvalidEmail_matter'] = 'Hallo %s{{br}}Schön bist du hier. Leider hat die Überprüfung deiner vom Identity Provider an uns übermittelten Daten fehlgeschlagen.';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_resourceOwnerHasInvalidEmail_howToFix'] = 'Du hast noch keine gültige E-Mail-Adresse hinterlegt. Bitte logge dich auf https://www.sac-cas.ch mit deinem Account ein und hinterlege deine E-Mail-Adresse.';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_resourceOwnerHasInvalidEmail_explain'] = 'Einige Anwendungen (z.B. Event-Tool) auf diesem Portal setzen eine gültige E-Mail-Adresse voraus.';

$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_missingSacMembership_matter'] = 'Hallo %s{{br}}Schön bist du hier. Leider hat dein Login-Versuch nicht geklappt, weil du kein SAC-Mitglied zu sein scheinst.';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_missingSacMembership_howToFix'] = 'Um von allen Services auf unserem Online-Portal zu profitieren, {{br}}- kannst du eine Mitgliedschaft bei der SAC Sektion Pilatus abschliessen.{{br}}Dazu darfst du dich sehr gerne bei unserer Geschäftsstelle melden.';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_missingSacMembership_explain'] = '';

$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_notMemberOfAllowedSection_matter'] = 'Hallo %s{{br}}Schön bist du hier. Leider hat dein Login-Versuch nicht geklappt, weil du kein Mitglied der SAC Sektion Pilatus zu sein scheinst.';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_notMemberOfAllowedSection_howToFix'] = 'Um von allen Services auf unserem Online-Portal zu profitieren, {{br}}- kannst du eine Zusatzmitgliedschaft bei SAC Pilatus abschliessen, {{br}}- oder einen Sektionswechsel zu SAC Pilatus beantragen.{{br}}{{br}}Dazu darfst du dich sehr gerne bei unserer Geschäftsstelle melden.';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_notMemberOfAllowedSection_explain'] = '';

$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_contaoFrontendUserNotFound_matter'] = 'Hallo %s{{br}}Schön bist du hier. Leider konnten wir dich nicht in unserer Mitgliederdatenbank finden.';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_contaoFrontendUserNotFound_howToFix'] = 'Falls du soeben/erst kürzlich eine Neumitgliedschaft beantragt hast, dann warte bitte einen Tag und versuche dich danach noch einmal hier einzuloggen.';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_contaoFrontendUserNotFound_explain'] = 'Es dauert mindestens einen Tag bis die SAC Zentralstelle deine Mitgliedschaft bestätigt hat.';

$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_contaoBackendUserNotFound_matter'] = 'Hallo %s{{br}}Schön bist du hier. Leider konnten wir dich nicht in unserer Backend-Benutzer-Datenbank finden.';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_contaoBackendUserNotFound_howToFix'] = 'Wenn du denkst, dass es sich um einen Irrtum handelt, dann melde dich mit deinem Anliegen bei unserem Webmaster.';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_contaoBackendUserNotFound_explain'] = 'Das Backend unserer Webseite ist nur Funktionären zugänglich.';

$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_contaoFrontendUserLoginNotEnabled_matter'] = 'Hallo %s{{br}}Schön bist du hier. Da dein Login im Moment nicht aktiv ist (login = false), ist eine Anmeldung derzeit nicht möglich.';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_contaoFrontendUserLoginNotEnabled_howToFix'] = 'Melde dich bei unserem Webmaster, sollte es sich hierbei um einen Irrtum handeln.';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_contaoFrontendUserLoginNotEnabled_explain'] = '';

$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_contaoUserDisabled_matter'] = 'Hallo %s{{br}}Schön bist du hier. Da dein Konto deaktiviert ist (disable = true), ist eine Anmeldung derzeit nicht möglich.';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_contaoUserDisabled_howToFix'] = 'Melde dich bei unserem Webmaster, sollte es sich hierbei um einen Irrtum handeln.';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_contaoUserDisabled_explain'] = '';

$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_unexpected_matter'] = 'Leider ist es beim Versuch dich einzuloggen zu einem unerwarteten Fehler gekommen.';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_unexpected_howToFix'] = 'Bitte probiere dich nochmals einzuloggen.';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_unexpected_explain'] = '';
