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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\Authenticator;

/**
 * The reason why a SAC SSO login has failed.
 *
 * The case value is used as the reason in the Contao system log and as the variable
 * part of the translation keys "ERR.sacOidcLoginError_<value>_matter|howToFix|explain".
 */
enum LoginFailureReason: string
{
    case InvalidState = 'invalidState';
    case MissingAuthCode = 'missingAuthCode';
    case ResourceOwnerHasInvalidSacMemberId = 'resourceOwnerHasInvalidSacMemberId';
    case ResourceOwnerHasInvalidEmail = 'resourceOwnerHasInvalidEmail';
    case MissingSacMembership = 'missingSacMembership';
    case NotMemberOfAllowedSection = 'notMemberOfAllowedSection';
    case ContaoFrontendUserNotFound = 'contaoFrontendUserNotFound';
    case ContaoBackendUserNotFound = 'contaoBackendUserNotFound';
    case ContaoFrontendUserLoginNotEnabled = 'contaoFrontendUserLoginNotEnabled';
    case ContaoUserDisabled = 'contaoUserDisabled';
    case Unexpected = 'unexpected';

    /**
     * The developer facing message. It ends up as the exception message and in the
     * log, it is never shown to the user.
     */
    public function getMessage(): string
    {
        return match ($this) {
            self::InvalidState => 'Authentication process aborted! Invalid state parameter passed in callback URL.',
            self::MissingAuthCode => 'Authentication process aborted! No "code" parameter was found (usually this is a query parameter)! Did you authorize our app?',
            self::ResourceOwnerHasInvalidSacMemberId => 'Authentication process aborted! Resource owner has no sac member id.',
            self::ResourceOwnerHasInvalidEmail => 'Authentication process aborted! Resource owner has no/invalid email address.',
            self::MissingSacMembership => 'Authentication process aborted! Resource owner is not a SAC member.',
            self::NotMemberOfAllowedSection => 'Authentication process aborted! Resource owner is not member of an allowed SAC section.',
            self::ContaoFrontendUserNotFound => 'Authentication process aborted! Contao frontend user not found.',
            self::ContaoBackendUserNotFound => 'Authentication process aborted! Contao backend user not found.',
            self::ContaoFrontendUserLoginNotEnabled => 'Authentication process aborted! Contao frontend user login is not enabled.',
            self::ContaoUserDisabled => 'Authentication process aborted! Contao user is disabled.',
            self::Unexpected => 'There has been an unexpected error.',
        };
    }

    /**
     * Translation key for "what has happened".
     */
    public function getMatterTranslationKey(): string
    {
        return $this->getTranslationKey('matter');
    }

    /**
     * Translation key for "what the user can do about it".
     */
    public function getHowToFixTranslationKey(): string
    {
        return $this->getTranslationKey('howToFix');
    }

    /**
     * Translation key for the background information.
     */
    public function getExplainTranslationKey(): string
    {
        return $this->getTranslationKey('explain');
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    private function getTranslationKey(string $part): string
    {
        return \sprintf('ERR.sacOidcLoginError_%s_%s', $this->value, $part);
    }
}
