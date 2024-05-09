<?php

declare(strict_types=1);

/*
 * This file is part of Swiss Alpine Club Contao Login Client Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/swiss-alpine-club-contao-login-client-bundle
 */

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\User;

use Contao\CoreBundle\Framework\ContaoFramework;
use Doctrine\DBAL\Connection;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Markocupic\SacEventToolBundle\DataContainer\Util;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\OAuth\OAuthUserChecker;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

final readonly class ContaoUserFactory
{
    public function __construct(
        private Connection $connection,
        private ContaoFramework $framework,
        private OAuthUserChecker $resourceOwnerChecker,
        private PasswordHasherFactoryInterface $hasherFactory,
        private Util $util,
    ) {
    }

    public function createContaoUser(ResourceOwnerInterface $resourceOwner, string $contaoScope): ContaoUser
    {
        return new ContaoUser(
            $this->framework,
            $this->connection,
            $this->hasherFactory,
            $this->resourceOwnerChecker,
            $resourceOwner,
            $this->util,
            $contaoScope,
        );
    }
}
