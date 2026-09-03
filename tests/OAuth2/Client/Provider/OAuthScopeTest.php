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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Tests\OAuth2\Client\Provider;

use Markocupic\SwissAlpineClubContaoLoginClientBundle\OAuth2\Client\Provider\OAuthScope;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Markocupic\SwissAlpineClubContaoLoginClientBundle\OAuth2\Client\Provider\OAuthScope
 */
final class OAuthScopeTest extends TestCase
{
    public function testTheDefaultsAreTheScopesNeededToIdentifyASacMember(): void
    {
        $this->assertSame(
            ['openid', 'with_roles', 'user_groups'],
            OAuthScope::toStrings(OAuthScope::defaults()),
        );
    }

    public function testToStringsUnwrapsTheEnum(): void
    {
        $this->assertSame(
            ['email', 'openid'],
            OAuthScope::toStrings([OAuthScope::Email, OAuthScope::OpenId]),
        );

        $this->assertSame([], OAuthScope::toStrings([]));
    }

    public function testValuesCoversEveryScopeOfferedByHitobito(): void
    {
        $this->assertSame(
            ['email', 'name', 'with_roles', 'openid', 'api', 'events', 'groups', 'people', 'invoices', 'mailing_lists', 'user_groups'],
            OAuthScope::values(),
        );
    }

    public function testEveryValueCanBeResolvedBackToItsCase(): void
    {
        foreach (OAuthScope::values() as $value) {
            $this->assertSame($value, OAuthScope::from($value)->value);
        }
    }
}
