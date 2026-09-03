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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Tests\Security\OAuth;

use Markocupic\SwissAlpineClubContaoLoginClientBundle\OAuth2\Client\Provider\Hitobito;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\OAuth\OAuthUser;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Tests\Fixtures\ResourceOwnerFixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OAuthUser::class)]
final class OAuthUserTest extends TestCase
{
    private const array ALLOWED_ROLES = [
        'Group::SektionsMitglieder::Ehrenmitglied',
        'Group::SektionsMitglieder::MitgliedZusatzsektion',
        'Group::SektionsMitglieder::Mitglied',
    ];

    /**
     * The mapping to SAC section ids is done by the OAuthUserChecker, this class
     * hands out what the identity provider sent.
     */
    public function testItReturnsTheRawHitobitoLayerGroupIds(): void
    {
        $this->assertSame([1415, 1425, 1569], self::member()->getHitobitoLayerGroupIds(self::ALLOWED_ROLES));
    }

    public function testItOnlyReturnsTheIdsOfAllowedRoles(): void
    {
        $this->assertSame(
            [1415],
            self::member()->getHitobitoLayerGroupIds(['Group::SektionsMitglieder::Mitglied']),
        );
    }

    public function testItReturnsNothingWithoutMatchingRoles(): void
    {
        $this->assertSame([], self::member()->getHitobitoLayerGroupIds(['Group::Vorstand']));
        $this->assertSame([], self::user(['sub' => '123456'])->getHitobitoLayerGroupIds(self::ALLOWED_ROLES));
    }

    public function testItSkipsIncompleteRoles(): void
    {
        $user = self::user([
            'sub' => '123456',
            'roles' => [
                ['role' => 'Group::SektionsMitglieder::Mitglied'],
                ['layer_group_id' => '4711'],
                ['role' => 'Group::SektionsMitglieder::Mitglied', 'layer_group_id' => '1415'],
            ],
        ]);

        $this->assertSame([1415], $user->getHitobitoLayerGroupIds(self::ALLOWED_ROLES));
    }

    public function testTheSacMemberIdIsTheSubjectWithoutLeadingZeros(): void
    {
        $this->assertSame('123456', self::user(['sub' => '000123456'])->getSacMemberId());
        $this->assertSame('', self::user([])->getSacMemberId());
    }

    public function testTheGenderIsMappedToTheContaoVocabulary(): void
    {
        $this->assertSame('male', self::user(['gender' => 'm'])->getGender());
        $this->assertSame('female', self::user(['gender' => 'w'])->getGender());
        $this->assertSame('other', self::user(['gender' => ''])->getGender());
        $this->assertSame('other', self::user([])->getGender());
    }

    public function testTheFullNameIsLastNameFirstName(): void
    {
        $this->assertSame('Messner Reinhold', self::member()->getFullName());
    }

    public function testMissingClaimsBecomeEmptyStrings(): void
    {
        $user = self::user([]);

        $this->assertSame('', $user->getLastName());
        $this->assertSame('', $user->getFirstName());
        $this->assertSame('', $user->getStreet());
        $this->assertSame('', $user->getPostal());
        $this->assertSame('', $user->getCity());
        $this->assertSame('', $user->getEmail());
        $this->assertSame('', $user->getPhone());
        $this->assertSame('', $user->getDateOfBirth());
        $this->assertSame([], $user->getRolesAsArray());
    }

    public function testToArrayReturnsTheUntouchedClaims(): void
    {
        $claims = ResourceOwnerFixtures::claims(true);

        $this->assertSame($claims, self::user($claims)->toArray());
    }

    public function testTheIdIsTheConfiguredResourceOwnerIdClaim(): void
    {
        $this->assertSame('123456', self::member()->getId());
    }

    /**
     * OAuthUserChecker::checkHasSacMemberId() rejects a resource owner without a
     * subject. Without the fallback the getter would raise a TypeError instead, the
     * check would never return false and the member would see the generic error.
     */
    public function testTheIdIsAnEmptyStringIfTheProviderDidNotSendASubject(): void
    {
        $this->assertSame('', self::user([])->getId());
        $this->assertSame('', self::user(['email' => 'r.messner@matterhorn-kiosk.ch'])->getId());
    }

    private static function member(): OAuthUser
    {
        return self::user(ResourceOwnerFixtures::claims(true));
    }

    /**
     * @param array<string, mixed> $claims
     */
    private static function user(array $claims): OAuthUser
    {
        return new OAuthUser($claims, Hitobito::ACCESS_TOKEN_RESOURCE_OWNER_ID);
    }
}
