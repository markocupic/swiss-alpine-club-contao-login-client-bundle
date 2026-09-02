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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\OAuth2\Client\Provider;

/**
 * OAuth scopes offered by the Hitobito identity provider.
 *
 * @see https://github.com/hitobito/hitobito/blob/master/doc/developer/people/oauth.md#openid-connect-oidc
 */
enum OAuthScope: string
{
    case Email = 'email';
    case Name = 'name';
    case WithRoles = 'with_roles';
    case OpenId = 'openid';
    case Api = 'api';
    case Events = 'events';
    case Groups = 'groups';
    case People = 'people';
    case Invoices = 'invoices';
    case MailingLists = 'mailing_lists';
    case UserGroups = 'user_groups';

    /**
     * The scopes this bundle needs to identify a SAC member.
     *
     * @return list<self>
     */
    public static function defaults(): array
    {
        return [self::OpenId, self::WithRoles, self::UserGroups];
    }

    /**
     * @param list<self> $scopes
     *
     * @return list<string>
     */
    public static function toStrings(array $scopes): array
    {
        return array_map(static fn (self $scope): string => $scope->value, $scopes);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
