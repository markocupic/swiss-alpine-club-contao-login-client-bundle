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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\EventListener;

use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\MemberModel;
use Contao\UserModel;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Event\AuthenticationFailureEvent;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\OAuth2\Client\Provider\Hitobito;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\OAuth\OAuthUser;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;

#[AsEventListener]
readonly class AuthenticationFailureListener
{
    public function __construct(
        private Connection $connection,
        private ScopeMatcher $scopeMatcher,
    ) {
    }

    public function __invoke(AuthenticationFailureEvent $event): void
    {
        $this->incrementLoginAttempts($event);
    }

    private function incrementLoginAttempts(AuthenticationFailureEvent $event): void
    {
        $resourceOwner = $event->getResourceOwner();

        if (null === $resourceOwner) {
            return;
        }

        $oAuthUser = new OAuthUser($resourceOwner->toArray(), Hitobito::ACCESS_TOKEN_RESOURCE_OWNER_ID);
        $sacMemberId = $oAuthUser->getSacMemberId();

        if (empty($sacMemberId)) {
            return;
        }

        $userTable = $this->getTable($event->getRequest());

        if (null === $userTable) {
            return;
        }

        $userTable = $this->connection->quoteIdentifier($userTable);

        $ssoLoginAttempts = $this->connection->fetchOne(
            'SELECT ssoLoginAttempts FROM '.$userTable.' WHERE sacMemberId = ?',
            [$sacMemberId],
            [Types::INTEGER],
        );

        if (false !== $ssoLoginAttempts) {
            $this->connection->update(
                $userTable,
                // Cap the counter, the column is a smallint(5) unsigned.
                ['ssoLoginAttempts' => min((int) $ssoLoginAttempts + 1, 65535)],
                ['sacMemberId' => $sacMemberId],
            );
        }
    }

    private function getTable(Request $request): string|null
    {
        if ($this->scopeMatcher->isBackendRequest($request)) {
            return UserModel::getTable();
        }

        if ($this->scopeMatcher->isFrontendRequest($request)) {
            return MemberModel::getTable();
        }

        return null;
    }
}
