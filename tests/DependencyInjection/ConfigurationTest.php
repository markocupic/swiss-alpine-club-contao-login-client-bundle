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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Tests\DependencyInjection;

use Markocupic\SwissAlpineClubContaoLoginClientBundle\DependencyInjection\Configuration;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\OAuth2\Client\Provider\OAuthScope;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\OAuth2\Client\Provider\PkceMethod;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

/**
 * @covers \Markocupic\SwissAlpineClubContaoLoginClientBundle\DependencyInjection\Configuration
 */
final class ConfigurationTest extends TestCase
{
    public function testTheDefaultsAreSafe(): void
    {
        $config = self::process([]);

        $this->assertSame(PkceMethod::S256->value, $config['oidc']['pkce_method'], 'PKCE has to be on by default.');
        $this->assertFalse($config['oidc']['debug_mode'], 'Debug mode logs personal data and must be off by default.');
        $this->assertFalse($config['oidc']['auto_create_backend_user']);
        $this->assertFalse($config['oidc']['reactivate_disabled_frontend_user_on_login']);
        $this->assertFalse($config['oidc']['enforce_frontend_two_factor'], 'Enabling two factor authentication has to be a deliberate decision.');
        $this->assertFalse($config['oidc']['enforce_backend_two_factor'], 'Enabling two factor authentication has to be a deliberate decision.');
        $this->assertTrue($config['oidc']['allow_frontend_login_to_sac_members_only']);
        $this->assertTrue($config['oidc']['allow_backend_login_to_predefined_section_members_only']);
    }

    public function testTheContainerParametersStayScalar(): void
    {
        $config = self::process([]);

        $this->assertIsString($config['oidc']['pkce_method'], 'An enum instance would not survive the container dump.');
        $this->assertSame(OAuthScope::toStrings(OAuthScope::defaults()), $config['oidc']['oauth_scopes']);

        foreach ($config['oidc']['oauth_scopes'] as $scope) {
            $this->assertIsString($scope);
        }
    }

    /**
     * @dataProvider everyPkceMethod
     */
    public function testEveryPkceMethodOfTheEnumIsAccepted(string $method): void
    {
        $this->assertSame($method, self::process(['pkce_method' => $method])['oidc']['pkce_method']);
    }

    /**
     * @dataProvider everyScope
     */
    public function testEveryScopeOfTheEnumIsAccepted(string $scope): void
    {
        $this->assertSame([$scope], self::process(['oauth_scopes' => [$scope]])['oidc']['oauth_scopes']);
    }

    public function testAnUnknownPkceMethodIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        self::process(['pkce_method' => 'S128']);
    }

    public function testAnUnknownScopeIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        self::process(['oauth_scopes' => ['openid', 'tippfehler']]);
    }

    public function testAMalformedSectionIdMapIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        self::process(['section_id_map' => '{kaputt']);
    }

    public function testASectionIdMapHasToBeAJsonObject(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        self::process(['section_id_map' => '"just a string"']);
    }

    public function testTheDefaultSectionIdMapIsValidJson(): void
    {
        $map = json_decode(self::process([])['oidc']['section_id_map'], true);

        $this->assertIsArray($map);
        $this->assertNotEmpty($map);
    }

    /**
     * The option was renamed because it does not only allow the login, it also
     * reactivates the account permanently.
     */
    public function testTheRenamedOptionReplacesTheOldOne(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        self::process(['allow_frontend_login_if_contao_account_is_disabled' => true]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function everyPkceMethod(): iterable
    {
        return array_combine(PkceMethod::values(), array_map(static fn (string $v): array => [$v], PkceMethod::values()));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function everyScope(): iterable
    {
        return array_combine(OAuthScope::values(), array_map(static fn (string $v): array => [$v], OAuthScope::values()));
    }

    /**
     * @param array<string, mixed> $oidc
     *
     * @return array<string, mixed>
     */
    private static function process(array $oidc): array
    {
        return (new Processor())->processConfiguration(new Configuration(), [['oidc' => $oidc]]);
    }
}
