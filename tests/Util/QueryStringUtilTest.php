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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Tests\Util;

use Markocupic\SwissAlpineClubContaoLoginClientBundle\Util\QueryStringUtil;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class QueryStringUtilTest extends TestCase
{
    /**
     * @dataProvider urlProvider
     */
    #[DataProvider('urlProvider')]
    public function testAddsTheParameter(string $url, string $expected): void
    {
        $this->assertSame($expected, QueryStringUtil::addParameter($url, 'sac-oidc-error', 'true'));
    }

    public static function urlProvider(): iterable
    {
        yield 'host only' => ['https://example.com', 'https://example.com?sac-oidc-error=true'];
        yield 'path' => ['https://example.com/login.html', 'https://example.com/login.html?sac-oidc-error=true'];
        yield 'existing query' => ['https://example.com/login.html?foo=bar', 'https://example.com/login.html?foo=bar&sac-oidc-error=true'];
        yield 'replaces the parameter' => ['https://example.com/?sac-oidc-error=false&foo=bar', 'https://example.com/?foo=bar&sac-oidc-error=true'];
        yield 'keeps the fragment' => ['https://example.com/a?foo=bar#top', 'https://example.com/a?foo=bar&sac-oidc-error=true#top'];
        yield 'keeps encoded parameters' => ['https://example.com/?a%20b=c%26d', 'https://example.com/?a%20b=c%26d&sac-oidc-error=true'];
        yield 'empty query' => ['https://example.com/?', 'https://example.com/?sac-oidc-error=true'];
    }
}
