<?php

declare(strict_types=1);

/*
 * This file is part of Swiss Alpine Club Contao Login Client Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/swiss-alpine-club-contao-login-client-bundle
 */

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Event;

use Markocupic\SwissAlpineClubContaoLoginClientBundle\Provider\SwissAlpineClubResourceOwner;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\User\ContaoUser;
use Symfony\Contracts\EventDispatcher\Event;

class InvalidLoginAttemptEvent extends Event
{
    public const NAME = 'sac_oauth2_client.invalid_login_attempt';

    // Checks
    public const FAILED_CHECK_HAS_UUID = 'failed.check.has.uuid';
    public const FAILED_CHECK_IS_SAC_MEMBER = 'failed.check.is.sac.member';
    public const FAILED_CHECK_IS_MEMBER_OF_ALLOWED_SECTION = 'failed.check.is.member.of.allowed.section';
    public const FAILED_CHECK_HAS_VALID_EMAIL_ADDRESS = 'failed.check.has.valid.email.address';
    public const FAILED_CHECK_USER_EXISTS = 'failed.check.user.exists';
    public const FAILED_CHECK_IS_ACCOUNT_ENABLED = 'failed.check.is.account.enabled';

    public function __construct(
        private readonly string $causeOfError,
        private readonly string $contaoScope,
        private readonly SwissAlpineClubResourceOwner $resourceOwner,
        private readonly ContaoUser|null $contaoUser = null,
    ) {
    }

    public function getCauseOfError(): string
    {
        return $this->causeOfError;
    }

    public function getContaoScope(): string
    {
        return $this->contaoScope;
    }

    public function getResourceOwner(): SwissAlpineClubResourceOwner
    {
        return $this->resourceOwner;
    }

    public function getUser(): ContaoUser|null
    {
        return $this->contaoUser;
    }
}
