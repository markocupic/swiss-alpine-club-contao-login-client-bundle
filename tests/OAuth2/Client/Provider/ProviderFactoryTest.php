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
use Markocupic\SwissAlpineClubContaoLoginClientBundle\OAuth2\Client\Provider\PkceMethod;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\OAuth2\Client\Provider\ProviderFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;

#[CoversClass(ProviderFactory::class)]
final class ProviderFactoryTest extends TestCase
{
    public function testItAcceptsAValidConfiguration(): void
    {
        $options = self::resolve([]);

        $this->assertSame(PkceMethod::S256, $options['pkceMethod']);
        $this->assertSame(OAuthScope::defaults(), $options['scopes']);
    }

    public function testPkceIsOptionalForBackwardCompatibility(): void
    {
        $options = self::resolve([], unset: ['pkceMethod']);

        $this->assertArrayNotHasKey('pkceMethod', $options);
    }

    #[DataProvider('endpointOptions')]
    public function testTheEndpointsHaveToUseHttps(string $option): void
    {
        $this->expectException(InvalidOptionsException::class);

        self::resolve([$option => 'http://insecure.example/x']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function endpointOptions(): iterable
    {
        return [
            'urlAuthorize' => ['urlAuthorize'],
            'urlAccessToken' => ['urlAccessToken'],
            'urlResourceOwnerDetails' => ['urlResourceOwnerDetails'],
            'redirectUri' => ['redirectUri'],
        ];
    }

    public function testARawStringIsNoLongerAcceptedAsPkceMethod(): void
    {
        $this->expectException(InvalidOptionsException::class);

        self::resolve(['pkceMethod' => 'S256']);
    }

    public function testScopesHaveToBeEnumCases(): void
    {
        $this->expectException(InvalidOptionsException::class);

        self::resolve(['scopes' => ['openid', 'with_roles']]);
    }

    public function testAMixedScopeListIsRejected(): void
    {
        $this->expectException(InvalidOptionsException::class);

        self::resolve(['scopes' => [OAuthScope::OpenId, 'openid']]);
    }

    /**
     * @param array<string, mixed> $overrides
     * @param list<string>         $unset
     *
     * @return array<string, mixed>
     */
    private static function resolve(array $overrides, array $unset = []): array
    {
        $config = [
            'clientId' => 'abc',
            'clientSecret' => 'secret',
            'urlAuthorize' => 'https://sac-cas.puzzle.ch/oauth/authorize',
            'urlAccessToken' => 'https://sac-cas.puzzle.ch/oauth/token',
            'urlResourceOwnerDetails' => 'https://sac-cas.puzzle.ch/de/oauth/profile',
            'redirectUri' => 'https://www.sac-pilatus.ch/_oauth2_login/hitobito/frontend',
            'scopes' => OAuthScope::defaults(),
            'pkceMethod' => PkceMethod::S256,
        ];

        $config = [...$config, ...$overrides];

        foreach ($unset as $key) {
            unset($config[$key]);
        }

        $resolver = new OptionsResolver();

        $configure = new \ReflectionMethod(ProviderFactory::class, 'configureOptions');
        $configure->invoke((new \ReflectionClass(ProviderFactory::class))->newInstanceWithoutConstructor(), $resolver);

        return $resolver->resolve($config);
    }
}
