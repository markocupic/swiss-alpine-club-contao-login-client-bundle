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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Tests\Security\Authenticator;

use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\Authenticator\LoginFailureReason;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(LoginFailureReason::class)]
final class LoginFailureReasonTest extends TestCase
{
    private const string LANGUAGE_DIR = __DIR__.'/../../../contao/languages';

    #[DataProvider('reasons')]
    public function testEveryReasonHasADeveloperMessage(LoginFailureReason $reason): void
    {
        // A missing match arm would raise an \UnhandledMatchError here.
        $this->assertNotSame('', $reason->getMessage(), $reason->name);
    }

    #[DataProvider('reasons')]
    public function testTheTranslationKeysFollowTheAgreedShape(LoginFailureReason $reason): void
    {
        $this->assertSame('ERR.sacOidcLoginError_'.$reason->value.'_matter', $reason->getMatterTranslationKey());
        $this->assertSame('ERR.sacOidcLoginError_'.$reason->value.'_howToFix', $reason->getHowToFixTranslationKey());
        $this->assertSame('ERR.sacOidcLoginError_'.$reason->value.'_explain', $reason->getExplainTranslationKey());
    }

    /**
     * The user facing message is looked up by the case value, so a new case without
     * translations would show a raw key to the member.
     */
    #[DataProvider('languages')]
    public function testEveryReasonIsTranslated(string $language): void
    {
        $translations = self::loadTranslations($language);

        foreach (LoginFailureReason::cases() as $reason) {
            foreach ([$reason->getMatterTranslationKey(), $reason->getHowToFixTranslationKey(), $reason->getExplainTranslationKey()] as $key) {
                $this->assertArrayHasKey(
                    substr($key, \strlen('ERR.')),
                    $translations,
                    \sprintf('Missing %s translation for "%s".', $language, $key),
                );
            }
        }
    }

    public function testValuesListsEveryCase(): void
    {
        $this->assertCount(\count(LoginFailureReason::cases()), LoginFailureReason::values());
        $this->assertContains('unexpected', LoginFailureReason::values());
    }

    public function testTheCaseValuesAreUnique(): void
    {
        $this->assertSame(LoginFailureReason::values(), array_unique(LoginFailureReason::values()));
    }

    /**
     * @return array<string, array{LoginFailureReason}>
     */
    public static function reasons(): iterable
    {
        $data = [];

        foreach (LoginFailureReason::cases() as $reason) {
            $data[$reason->value] = [$reason];
        }

        return $data;
    }

    /**
     * @return array<string, array{string}>
     */
    public static function languages(): iterable
    {
        return ['de' => ['de'], 'en' => ['en']];
    }

    /**
     * @return array<string, string>
     */
    private static function loadTranslations(string $language): array
    {
        $backup = $GLOBALS['TL_LANG'] ?? null;
        $GLOBALS['TL_LANG'] = [];

        require self::LANGUAGE_DIR.'/'.$language.'/default.php';
        $translations = $GLOBALS['TL_LANG']['ERR'] ?? [];

        if (null === $backup) {
            unset($GLOBALS['TL_LANG']);
        } else {
            $GLOBALS['TL_LANG'] = $backup;
        }

        return $translations;
    }
}
