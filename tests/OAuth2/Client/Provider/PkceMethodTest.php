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

use League\OAuth2\Client\Provider\AbstractProvider;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\OAuth2\Client\Provider\PkceMethod;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Markocupic\SwissAlpineClubContaoLoginClientBundle\OAuth2\Client\Provider\PkceMethod
 */
final class PkceMethodTest extends TestCase
{
    /**
     * The enum cannot reference the constants of league/oauth2-client, because enum
     * case values have to be literals. This test is what keeps them in sync.
     */
    public function testTheCaseValuesMatchTheOnesOfTheOauth2Library(): void
    {
        $this->assertSame(AbstractProvider::PKCE_METHOD_S256, PkceMethod::S256->value);
        $this->assertSame(AbstractProvider::PKCE_METHOD_PLAIN, PkceMethod::Plain->value);
    }

    public function testNoneDisablesPkce(): void
    {
        $this->assertNull(PkceMethod::None->toProviderOption());
        $this->assertFalse(PkceMethod::None->isEnabled());
    }

    public function testTheOtherMethodsArePassedOnToTheProvider(): void
    {
        foreach ([PkceMethod::S256, PkceMethod::Plain] as $method) {
            $this->assertSame($method->value, $method->toProviderOption(), $method->name);
            $this->assertTrue($method->isEnabled(), $method->name);
        }
    }

    public function testValuesListsEveryCase(): void
    {
        $this->assertSame(['S256', 'plain', 'none'], PkceMethod::values());
        $this->assertCount(\count(PkceMethod::cases()), PkceMethod::values());
    }
}
