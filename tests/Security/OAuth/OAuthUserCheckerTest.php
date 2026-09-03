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

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Validator;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\OAuth2\Client\Provider\Hitobito;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\OAuth\OAuthUser;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\OAuth\OAuthUserChecker;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Tests\Fixtures\ResourceOwnerFixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OAuthUserChecker::class)]
final class OAuthUserCheckerTest extends TestCase
{
    private const string ROLE_MEMBER = 'Group::SektionsMitglieder::Mitglied';

    private const string ROLE_ADDITIONAL = 'Group::SektionsMitglieder::MitgliedZusatzsektion';

    /**
     * The fixture holds three roles: layer group 1415 (Mitglied), 1425 and 1569
     * (both MitgliedZusatzsektion). The map turns 1415 into 4250 and 1425 into
     * 4252, 1569 is unknown to the map and stays as it is.
     */
    private const string SECTION_ID_MAP = '{"1415":4250,"1420":4251,"1425":4252,"1430":4253,"1435":4254}';

    public function testAResourceOwnerWithoutASubjectIsRejected(): void
    {
        $this->assertFalse($this->checker()->checkHasSacMemberId(self::user([])));
        $this->assertFalse($this->checker()->checkHasSacMemberId(self::user(['email' => 'x@example.ch'])));
    }

    public function testAResourceOwnerWithASubjectIsAccepted(): void
    {
        $this->assertTrue($this->checker()->checkHasSacMemberId(self::member()));
    }

    public function testTheLayerGroupIdsAreMappedToSacSectionIds(): void
    {
        $checker = $this->checker(frontendSections: [4250, 4251, 4252]);

        $this->assertSame(
            [4250, 4252],
            array_values($checker->getAllowedSacSectionIds(self::member(), ContaoCoreBundle::SCOPE_FRONTEND)),
        );
    }

    public function testAnIdTheMapDoesNotKnowIsPassedThroughUnchanged(): void
    {
        $checker = $this->checker(frontendSections: [1569]);

        $this->assertSame(
            [1569],
            array_values($checker->getAllowedSacSectionIds(self::member(), ContaoCoreBundle::SCOPE_FRONTEND)),
        );
    }

    public function testFrontendAndBackendUseTheirOwnListOfAllowedSections(): void
    {
        $checker = $this->checker(frontendSections: [4250], backendSections: [4252]);

        $this->assertSame([4250], array_values($checker->getAllowedSacSectionIds(self::member(), ContaoCoreBundle::SCOPE_FRONTEND)));
        $this->assertSame([4252], array_values($checker->getAllowedSacSectionIds(self::member(), ContaoCoreBundle::SCOPE_BACKEND)));
    }

    public function testFrontendAndBackendUseTheirOwnListOfAllowedRoles(): void
    {
        // The frontend only accepts the main membership, the backend also the
        // additional one. 1425 belongs to an additional membership.
        $checker = $this->checker(
            frontendSections: [4250, 4252],
            backendSections: [4250, 4252],
            frontendRoles: [self::ROLE_MEMBER],
            backendRoles: [self::ROLE_MEMBER, self::ROLE_ADDITIONAL],
        );

        $this->assertSame([4250], array_values($checker->getAllowedSacSectionIds(self::member(), ContaoCoreBundle::SCOPE_FRONTEND)));
        $this->assertSame([4250, 4252], array_values($checker->getAllowedSacSectionIds(self::member(), ContaoCoreBundle::SCOPE_BACKEND)));
    }

    public function testAMemberOfNoAllowedSectionIsRejected(): void
    {
        $checker = $this->checker(frontendSections: [9999]);

        $this->assertSame([], $checker->getAllowedSacSectionIds(self::member(), ContaoCoreBundle::SCOPE_FRONTEND));
        $this->assertFalse($checker->checkIsMemberOfAllowedSection(self::member(), ContaoCoreBundle::SCOPE_FRONTEND));
    }

    public function testAMemberOfAnAllowedSectionIsAccepted(): void
    {
        $this->assertTrue(
            $this->checker(frontendSections: [4250])->checkIsMemberOfAllowedSection(self::member(), ContaoCoreBundle::SCOPE_FRONTEND),
        );
    }

    /**
     * Being a SAC member does not depend on the allowed sections, only on holding
     * one of the allowed roles.
     */
    public function testSacMembershipIgnoresTheAllowedSections(): void
    {
        $checker = $this->checker(frontendSections: []);

        $this->assertTrue($checker->checkIsSacMember(self::member(), ContaoCoreBundle::SCOPE_FRONTEND));
        $this->assertTrue($checker->isSacMember(self::member(), ContaoCoreBundle::SCOPE_FRONTEND));
        $this->assertFalse($checker->checkIsMemberOfAllowedSection(self::member(), ContaoCoreBundle::SCOPE_FRONTEND));
    }

    public function testSomebodyWithoutAnAllowedRoleIsNoSacMember(): void
    {
        $checker = $this->checker(frontendRoles: ['Group::Vorstand']);

        $this->assertFalse($checker->checkIsSacMember(self::member(), ContaoCoreBundle::SCOPE_FRONTEND));
    }

    public function testTheNonMemberFixtureBelongsToNoAllowedSection(): void
    {
        $checker = $this->checker(frontendSections: [4250, 4251, 4252, 4253, 4254]);
        $nonMember = self::user(ResourceOwnerFixtures::claims(false));

        $this->assertFalse($checker->checkIsMemberOfAllowedSection($nonMember, ContaoCoreBundle::SCOPE_FRONTEND));
    }

    public function testAnUnknownScopeIsRefused(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid contao scope detected');

        $this->checker()->getAllowedSacSectionIds(self::member(), 'sidebar');
    }

    public function testTheEmailAddressIsValidated(): void
    {
        $checker = $this->checker();

        $this->assertTrue($checker->checkHasValidEmailAddress(self::user(['email' => 'r.messner@matterhorn-kiosk.ch'])));
        $this->assertFalse($checker->checkHasValidEmailAddress(self::user(['email' => 'kein-at-zeichen'])));
        $this->assertFalse($checker->checkHasValidEmailAddress(self::user(['email' => ''])));
        $this->assertFalse($checker->checkHasValidEmailAddress(self::user([])));
    }

    /**
     * @param list<int>    $frontendSections
     * @param list<int>    $backendSections
     * @param list<string> $frontendRoles
     * @param list<string> $backendRoles
     */
    private function checker(array $frontendSections = [4250, 4251, 4252, 4253, 4254], array $backendSections = [4250, 4251, 4252, 4253, 4254], array $frontendRoles = [self::ROLE_MEMBER, self::ROLE_ADDITIONAL], array $backendRoles = [self::ROLE_MEMBER, self::ROLE_ADDITIONAL]): OAuthUserChecker
    {
        return new OAuthUserChecker(
            $this->framework(),
            $frontendSections,
            $frontendRoles,
            $backendRoles,
            $backendSections,
            self::SECTION_ID_MAP,
        );
    }

    /**
     * Contao's Validator is a plain static helper, so the checker can run against
     * the real thing instead of a stub.
     */
    private function framework(): ContaoFramework
    {
        $framework = $this->createMock(ContaoFramework::class);
        $framework
            ->method('getAdapter')
            ->willReturn(new Adapter(Validator::class))
        ;

        return $framework;
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
