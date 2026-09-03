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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Tests\Security;

use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\RedirectPathValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @covers \Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\RedirectPathValidator
 */
final class RedirectPathValidatorTest extends TestCase
{
    private const string HOST = 'www.sac-pilatus.ch';

    /**
     * @dataProvider safeTargets
     */
    public function testAcceptsATargetOnTheCurrentHost(string $path): void
    {
        $this->assertSame($path, self::validator()->getSafePath(base64_encode($path), self::request()));
    }

    /**
     * @dataProvider unsafeTargets
     */
    public function testRejectsATargetPointingSomewhereElse(string $path): void
    {
        $this->assertNull(self::validator()->getSafePath(base64_encode($path), self::request()));
    }

    /**
     * @dataProvider malformedInput
     */
    public function testRejectsMalformedInput(string|null $encodedPath): void
    {
        $this->assertNull(self::validator()->getSafePath($encodedPath, self::request()));
    }

    public function testTheHostComparisonIsCaseInsensitive(): void
    {
        $path = 'https://WWW.SAC-PILATUS.CH/contao';

        $this->assertTrue(self::validator()->isSafePath($path, self::request()));
    }

    public function testAPortDoesNotMakeTheHostForeign(): void
    {
        $this->assertTrue(self::validator()->isSafePath('https://'.self::HOST.':8443/contao', self::request()));
    }

    public function testIsSafePathAndGetSafePathAgree(): void
    {
        $request = self::request();
        $validator = self::validator();

        foreach ([...array_values(self::safeTargets()), ...array_values(self::unsafeTargets())] as [$path]) {
            $this->assertSame(
                $validator->isSafePath($path, $request),
                null !== $validator->getSafePath(base64_encode($path), $request),
                \sprintf('isSafePath() and getSafePath() disagree about "%s".', $path),
            );
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function safeTargets(): iterable
    {
        return [
            'absolute https url' => ['https://'.self::HOST.'/mitglieder'],
            'absolute http url' => ['http://'.self::HOST.'/x'],
            'url with query and fragment' => ['https://'.self::HOST.'/x?a=b#c'],
            'host only' => ['https://'.self::HOST],
            'relative path' => ['/contao'],
            'root' => ['/'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsafeTargets(): iterable
    {
        return [
            'foreign host' => ['https://evil.example/'],
            'host as a subdomain of the attacker' => ['https://'.self::HOST.'.evil.example/'],
            'own host only in the path' => ['https://evil.example/'.self::HOST],
            'scheme relative url' => ['//evil.example/'],
            'backslash variant' => ['/\\evil.example/'],
            'double backslash' => ['\\\\evil.example/'],
            'triple slash' => ['///evil.example/'],
            'own host as user info' => ['https://'.self::HOST.'@evil.example/'],
            'user and password' => ['https://user:pw@evil.example/'],
            'javascript scheme' => ['javascript:alert(1)'],
            'data scheme' => ['data:text/html,<script>alert(1)</script>'],
            'leading whitespace' => ['  https://evil.example/'],
            'crlf injection' => ['https://'.self::HOST."/x\r\nLocation: https://evil.example"],
            'host without scheme' => ['evil.example/x'],
            'bare word' => ['contao'],
            'empty string' => [''],
        ];
    }

    /**
     * @return array<string, array{string|null}>
     */
    public static function malformedInput(): iterable
    {
        return [
            'null' => [null],
            'empty' => [''],
            'not base64' => ['nicht base64!!!'],
            'padding only' => ['####'],
        ];
    }

    private static function validator(): RedirectPathValidator
    {
        return new RedirectPathValidator();
    }

    private static function request(): Request
    {
        return Request::create('https://'.self::HOST.'/');
    }
}
