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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\Authenticator\Exception;

use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\Authenticator\LoginFailureReason;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Thrown when a SAC SSO login fails.
 *
 * The reason tells why it failed, so listeners and error handlers can react to a
 * specific failure without having to catch a dedicated exception class.
 */
class SacLoginAuthenticationException extends AuthenticationException
{
    private LoginFailureReason $reason;

    public function __construct(LoginFailureReason $reason, \Throwable|null $previous = null)
    {
        $this->reason = $reason;

        parent::__construct($reason->getMessage(), 0, $previous);
    }

    /**
     * The exception is stored in the session by
     * HitobitoAuthenticator::onAuthenticationFailure(), so the reason has to survive
     * serialization. The parent only keeps token, code, message, file and line.
     */
    public function __serialize(): array
    {
        return [parent::__serialize(), $this->reason];
    }

    public function __unserialize(array $data): void
    {
        [$parentData, $this->reason] = $data;

        parent::__unserialize($parentData);
    }

    public function getReason(): LoginFailureReason
    {
        return $this->reason;
    }
}
