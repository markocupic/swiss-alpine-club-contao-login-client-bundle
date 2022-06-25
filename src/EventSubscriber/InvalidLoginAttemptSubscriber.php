<?php

declare(strict_types=1);

/*
 * This file is part of Swiss Alpine Club Contao Login Client Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/swiss-alpine-club-contao-login-client-bundle
 */

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\EventSubscriber;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\MemberModel;
use Contao\UserModel;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Event\InvalidLoginAttemptEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class InvalidLoginAttemptSubscriber implements EventSubscriberInterface
{
    private const PRIORITY = 1000;
    private ContaoFramework $framework;
    private LoggerInterface|null $logger;

    public function __construct(ContaoFramework $framework, LoggerInterface|null $logger)
    {
        $this->framework = $framework;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        return [InvalidLoginAttemptEvent::NAME => ['handleInvalidLoginAttempts', self::PRIORITY]];
    }

    /**
     * @throws \Exception
     */
    public function handleInvalidLoginAttempts(InvalidLoginAttemptEvent $loginEvent): void
    {
        // Increment tl_user.loginAttempts or tl_member.loginAttempts, if login fails
        // Write cause of error to the Contao system log
        $remoteUser = $loginEvent->getRemoteUser();
        $identifier = $remoteUser->get('contact_number');
        $firstname = $remoteUser->get('vorname');
        $lastname = $remoteUser->get('familienname');

        if (ContaoCoreBundle::SCOPE_FRONTEND === $loginEvent->getContaoScope()) {
            $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);
            $userModel = $memberModelAdapter->findByUsername($identifier);
            $logLevel = 'SSO_FRONTEND_LOGIN_FAIL';
            $logText = sprintf(
                'SAC oauth2 (SSO-Frontend-Login) failed for user "%s %s" with member id [%s]. Cause: %s',
                $firstname,
                $lastname,
                $identifier,
                $loginEvent->getCauseOfError()
            );
        } else {
            $userModelAdapter = $this->framework->getAdapter(UserModel::class);
            $userModel = $userModelAdapter->findBySacMemberId($identifier);
            $logLevel = 'SSO_BACKEND_LOGIN_FAIL';
            $logText = sprintf(
                'SAC oauth2 (SSO-Backend-Login) failed for user "%s %s" with member id [%s]. Cause: %s',
                $firstname,
                $lastname,
                $identifier,
                $loginEvent->getCauseOfError()
            );
        }

        if (null !== $userModel) {
            ++$userModel->loginAttempts;
            $userModel->save();
        }

        $this->logger->info($logText, ['contao' => new ContaoContext(__METHOD__, $logLevel, null)]);
    }
}
