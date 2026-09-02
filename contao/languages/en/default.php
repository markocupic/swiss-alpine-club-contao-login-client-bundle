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

/*
 * Miscellaneous
 */
$GLOBALS['TL_LANG']['MSC']['loginWithSacSso'] = 'Log in with SAC Login';

// Error management
$GLOBALS['TL_LANG']['MSC']['infoMatter'] = 'Information';
$GLOBALS['TL_LANG']['MSC']['warningMatter'] = 'Login not possible';
$GLOBALS['TL_LANG']['MSC']['errorMatter'] = 'Login error';
$GLOBALS['TL_LANG']['MSC']['errorHowToFix'] = 'What can I do?';
$GLOBALS['TL_LANG']['MSC']['errorExplain'] = 'Explanation';
$GLOBALS['TL_LANG']['MSC']['or'] = 'or';

$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_invalidState_matter'] = 'Unfortunately an error occurred while trying to log you in (invalid state).';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_invalidState_howToFix'] = 'Please try to log in again.';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_invalidState_explain'] = '';

$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_missingAuthCode_matter'] = 'Unfortunately an error occurred while trying to log you in (missing auth code).';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_missingAuthCode_howToFix'] = 'Please try to log in again.';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_missingAuthCode_explain'] = '';

$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_resourceOwnerHasInvalidSacMemberId_matter'] = 'Hello{{br}}Good to see you here. Unfortunately we were unable to verify the data the identity provider sent us. No UUID was transmitted.';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_resourceOwnerHasInvalidSacMemberId_howToFix'] = 'Please log in to https://portal.sac-cas.ch with your SAC account and check that your details are correct.';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_resourceOwnerHasInvalidSacMemberId_explain'] = '';

$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_resourceOwnerHasInvalidEmail_matter'] = 'Hello %s{{br}}Good to see you here. Unfortunately we were unable to verify the data the identity provider sent us.';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_resourceOwnerHasInvalidEmail_howToFix'] = 'You have not stored a valid email address yet. Please log in to https://portal.sac-cas.ch with your SAC account and add your email address.';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_resourceOwnerHasInvalidEmail_explain'] = 'Some applications on this portal (e.g. the event tool) require a valid email address.';

$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_missingSacMembership_matter'] = 'Hello %s{{br}}Good to see you here. Unfortunately your login attempt did not work, because you do not appear to be a SAC member.';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_missingSacMembership_howToFix'] = 'To benefit from all the services on our online portal, {{br}}- you can take out a membership with the SAC section Pilatus.{{br}}You are very welcome to contact our office about this.';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_missingSacMembership_explain'] = 'Your section membership has to be valid in the SAC portal at https://portal.sac-cas.ch.';

$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_notMemberOfAllowedSection_matter'] = 'Hello %s{{br}}Good to see you here. Unfortunately your login attempt did not work, because you do not appear to be a member of the SAC section Pilatus.';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_notMemberOfAllowedSection_howToFix'] = 'To benefit from all the services on our online portal, {{br}}- you can take out an additional membership with SAC Pilatus, {{br}}- or apply for a section transfer to SAC Pilatus.{{br}}{{br}}You are very welcome to contact our office about this.';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_notMemberOfAllowedSection_explain'] = 'Your section membership has to be valid in the SAC portal at https://portal.sac-cas.ch.';

$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_contaoFrontendUserNotFound_matter'] = 'Hello %s{{br}}Good to see you here. Unfortunately we could not find you in our member database.';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_contaoFrontendUserNotFound_howToFix'] = 'If you have applied for a new membership just now or very recently, please wait a day and then try to log in here again.';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_contaoFrontendUserNotFound_explain'] = 'It takes at least one day until the SAC central association has confirmed and activated your membership.';

$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_contaoBackendUserNotFound_matter'] = 'Hello %s{{br}}Good to see you here. Unfortunately we could not find you in our backend user database.';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_contaoBackendUserNotFound_howToFix'] = 'If you think this is a mistake, please get in touch with the person in charge of your department or with our webmaster.';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_contaoBackendUserNotFound_explain'] = 'The backend of our website is only accessible to active officials.';

$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_contaoFrontendUserLoginNotEnabled_matter'] = 'Hello %s{{br}}Good to see you here. Your login has not been enabled yet or is no longer active, so signing in is currently not possible.';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_contaoFrontendUserLoginNotEnabled_howToFix'] = 'After a recent change to your membership it can take a day until your account is active again. Otherwise please contact our webmaster if you think this is a mistake.';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_contaoFrontendUserLoginNotEnabled_explain'] = 'Your section membership has to be valid in the SAC portal at https://portal.sac-cas.ch.';

$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_contaoUserDisabled_matter'] = 'Hello %s{{br}}Good to see you here. Your account is disabled, so signing in is currently not possible.';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_contaoUserDisabled_howToFix'] = 'Please contact our webmaster if you think this is a mistake.';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_contaoUserDisabled_explain'] = '';

$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_unexpected_matter'] = 'Unfortunately an unexpected error occurred while trying to log you in.';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_unexpected_howToFix'] = 'Please try to log in again.';
$GLOBALS['TL_LANG']['ERR']['sacOidcLoginError_unexpected_explain'] = '';
