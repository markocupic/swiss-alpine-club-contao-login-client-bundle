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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\User;

use Contao\CoreBundle\Framework\ContaoFramework;
use Doctrine\DBAL\Connection;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\ErrorMessage\ErrorMessageManager;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\OAuth\OAuthUser;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\OAuth\OAuthUserChecker;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ContaoUserFactory
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Connection $connection,
        private readonly TranslatorInterface $translator,
        private readonly PasswordHasherFactoryInterface $hasherFactory,
        private readonly OAuthUserChecker $resourceOwnerChecker,
        private readonly ErrorMessageManager $errorMessageManager,
    ) {
    }

    public function loadContaoUser(OAuthUser $resourceOwner, string $contaoScope): ContaoUser
    {
        return new ContaoUser(
            $this->framework,
            $this->connection,
            $this->translator,
            $this->hasherFactory,
            $this->resourceOwnerChecker,
            $this->errorMessageManager,
            $resourceOwner,
            $contaoScope,
        );
    }
}
