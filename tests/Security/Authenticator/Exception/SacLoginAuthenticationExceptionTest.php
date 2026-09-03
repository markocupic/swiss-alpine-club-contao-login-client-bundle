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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Tests\Security\Authenticator\Exception;

use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\Authenticator\Exception\SacLoginAuthenticationException;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\Authenticator\LoginFailureReason;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * @covers \Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\Authenticator\Exception\SacLoginAuthenticationException
 */
final class SacLoginAuthenticationExceptionTest extends TestCase
{
    public function testItCarriesTheReasonAndItsMessage(): void
    {
        $exception = new SacLoginAuthenticationException(LoginFailureReason::MissingSacMembership);

        $this->assertSame(LoginFailureReason::MissingSacMembership, $exception->getReason());
        $this->assertSame(LoginFailureReason::MissingSacMembership->getMessage(), $exception->getMessage());
    }

    public function testItIsAnAuthenticationExceptionSoTheFirewallHandlesIt(): void
    {
        $this->assertInstanceOf(
            AuthenticationException::class,
            new SacLoginAuthenticationException(LoginFailureReason::Unexpected),
        );
    }

    public function testItKeepsTheUnderlyingCause(): void
    {
        $cause = new \RuntimeException('SQLSTATE[HY000] connection refused');
        $exception = new SacLoginAuthenticationException(LoginFailureReason::Unexpected, $cause);

        $this->assertSame($cause, $exception->getPrevious());
    }

    /**
     * HitobitoAuthenticator::onAuthenticationFailure() puts the exception into the
     * session. AuthenticationException::__serialize() only keeps token, code,
     * message, file and line, so without the override the reason would be lost and
     * the typed property would end up uninitialised.
     */
    public function testTheReasonSurvivesTheSession(): void
    {
        foreach (LoginFailureReason::cases() as $reason) {
            $restored = unserialize(serialize(new SacLoginAuthenticationException($reason)));

            $this->assertInstanceOf(SacLoginAuthenticationException::class, $restored);
            $this->assertSame($reason, $restored->getReason(), $reason->name);
            $this->assertSame($reason->getMessage(), $restored->getMessage(), $reason->name);
        }
    }

    public function testTheReasonSurvivesEvenWithAPreviousException(): void
    {
        $exception = new SacLoginAuthenticationException(LoginFailureReason::InvalidState, new \RuntimeException('boom'));
        $restored = unserialize(serialize($exception));

        $this->assertSame(LoginFailureReason::InvalidState, $restored->getReason());
    }
}
