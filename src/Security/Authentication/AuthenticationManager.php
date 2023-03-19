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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\Authentication;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\System;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Token\AccessToken;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Config\ContaoLogConfig;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Event\InvalidLoginAttemptEvent;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\InteractiveLogin\InteractiveLogin;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\Oauth\ResourceOwner\ResourceOwnerChecker;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\Oauth\ResourceOwner\SwissAlpineClubResourceOwner;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\User\ContaoUser;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\User\ContaoUserFactory;
use Psr\Log\LoggerInterface;
use Safe\Exceptions\JsonException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionBagInterface;
use function Safe\json_encode;

class AuthenticationManager
{
    private Adapter $system;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly RequestStack $requestStack,
        private readonly ResourceOwnerChecker $resourceOwnerChecker,
        private readonly ContaoUserFactory $contaoUserFactory,
        private readonly InteractiveLogin $interactiveLogin,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface|null $logger = null,
    ) {
        // Adapters
        $this->system = $this->framework->getAdapter(System::class);
    }

    /**
     * @throws JsonException
     */
    public function authenticateContaoUser(Request $request, AbstractProvider $provider, AccessToken $accessToken, string $contaoScope): void
    {
        $allowedScopes = [
            ContaoCoreBundle::SCOPE_BACKEND,
            ContaoCoreBundle::SCOPE_FRONTEND,
        ];

        if (!\in_array($contaoScope, $allowedScopes, true)) {
            throw new \InvalidArgumentException(sprintf('The Contao Scope must be either "%s" "%s" given.', implode('" or "', $allowedScopes), $contaoScope));
        }

        $container = $this->system->getContainer();

        $isDebugMode = $container->getParameter('sac_oauth2_client.oidc.debug_mode');

        // Session
        $session = $this->getSession($request, $contaoScope);
        $flashBag = $this->requestStack->getCurrentRequest()->getSession()->getFlashBag();
        $flashBagKey = $container->getParameter('sac_oauth2_client.session.flash_bag_key');

        /** @var bool $blnAutoCreateContaoUser */
        $blnAutoCreateContaoUser = $container->getParameter('sac_oauth2_client.oidc.auto_create_'.$contaoScope.'_user');

        /** @var bool $blnAllowLoginToSacMembersOnly */
        $blnAllowLoginToSacMembersOnly = $container->getParameter('sac_oauth2_client.oidc.allow_'.$contaoScope.'_login_to_sac_members_only');

        /** @var bool $blnAllowLoginToPredefinedSectionsOnly */
        $blnAllowLoginToPredefinedSectionsOnly = $container->getParameter('sac_oauth2_client.oidc.allow_'.$contaoScope.'_login_to_predefined_section_members_only');

        /** @var bool $blnAllowContaoLoginIfAccountIsDisabled */
        $blnAllowContaoLoginIfAccountIsDisabled = $container->getParameter('sac_oauth2_client.oidc.allow_'.$contaoScope.'_login_if_contao_account_is_disabled');

        // Get the resource owner object
        /** @var SwissAlpineClubResourceOwner $resourceOwner */
        $resourceOwner = $provider->getResourceOwner($accessToken);

        // For testing purposes only
        //$resourceOwner->overrideData($resourceOwner->getDummyResourceOwnerData(true));

        if ($isDebugMode) {
            // Log resource owners details
            $logText = sprintf(
                'SAC oauth2 debug %s login. NAME: %s - SAC MEMBER ID: %s - ROLES: %s - DATA ALL: %s',
                $contaoScope,
                $resourceOwner->getFullName(),
                $resourceOwner->getSacMemberId(),
                $resourceOwner->getRolesAsString(),
                json_encode($resourceOwner->toArray()),
            );

            $this->log($logText, __METHOD__, ContaoLogConfig::SAC_OAUTH2_DEBUG_LOG);
        }

        // Check if uuid/sub is set
        if (!$this->resourceOwnerChecker->checkHasUuid($resourceOwner)) {
            $this->dispatchInvalidLoginAttemptEvent(InvalidLoginAttemptEvent::FAILED_CHECK_HAS_UUID, $contaoScope, $resourceOwner);

            throw new RedirectResponseException($session->get('failurePath'));
        }

        // Check if user is a SAC member
        if ($blnAllowLoginToSacMembersOnly) {
            if (!$this->resourceOwnerChecker->checkIsSacMember($resourceOwner)) {
                $this->dispatchInvalidLoginAttemptEvent(InvalidLoginAttemptEvent::FAILED_CHECK_IS_SAC_MEMBER, $contaoScope, $resourceOwner);

                throw new RedirectResponseException($session->get('failurePath'));
            }
        }

        // Check if user is member of an allowed section
        if ($blnAllowLoginToPredefinedSectionsOnly) {
            if (!$this->resourceOwnerChecker->checkIsMemberOfAllowedSection($resourceOwner, $contaoScope)) {
                $this->dispatchInvalidLoginAttemptEvent(InvalidLoginAttemptEvent::FAILED_CHECK_IS_MEMBER_OF_ALLOWED_SECTION, $contaoScope, $resourceOwner);

                throw new RedirectResponseException($session->get('failurePath'));
            }
        }

        // Check has valid email address
        // This test should always be positive,
        // because creating an account at https://www.sac-cas.ch
        // requires already a valid email address
        if (!$this->resourceOwnerChecker->checkHasValidEmailAddress($resourceOwner)) {
            $this->dispatchInvalidLoginAttemptEvent(InvalidLoginAttemptEvent::FAILED_CHECK_HAS_VALID_EMAIL_ADDRESS, $contaoScope, $resourceOwner);

            throw new RedirectResponseException($session->get('failurePath'));
        }

        // Create the user wrapper object
        $contaoUser = $this->contaoUserFactory->loadContaoUser($resourceOwner, $contaoScope);

        // Create Contao frontend or backend user, if it doesn't exist.
        if (ContaoCoreBundle::SCOPE_FRONTEND === $contaoScope) {
            if ($blnAutoCreateContaoUser) {
                $contaoUser->createIfNotExists();
            }
        }

        // if $contaoScope === 'backend': Check if Contao backend user exists
        // if $contaoScope === 'frontend': Check if Contao frontend user exists
        if (!$contaoUser->checkUserExists()) {
            $this->dispatchInvalidLoginAttemptEvent(InvalidLoginAttemptEvent::FAILED_CHECK_USER_EXISTS, $contaoScope, $resourceOwner, $contaoUser);

            throw new RedirectResponseException($session->get('failurePath'));
        }

        // Allow login to frontend users only if account is disabled
        if (ContaoCoreBundle::SCOPE_FRONTEND === $contaoScope) {
            // Set tl_member.disable = ''
            $contaoUser->enableLogin();
        }

        // if $contaoScope === 'backend': Set tl_user.locked = 0
        // if $contaoScope === 'frontend': Set tl_member.locked = 0
        $contaoUser->unlock();

        // Set tl_user.loginAttempts = 0
        $contaoUser->resetLoginAttempts();

        // Update tl_member and tl_user
        $contaoUser->updateFrontendUser();
        $contaoUser->updateBackendUser();

        // if $contaoScope === 'backend': Check if tl_user.disable == '' or tl_user.login == '1' or tl_user.start and tl_user.stop are not in an allowed time range
        // if $contaoScope === 'frontend': Check if tl_member.disable == '' or tl_member.login == '1' or tl_member.start and tl_member.stop are not in an allowed time range
        if (!$contaoUser->checkIsAccountEnabled() && !$blnAllowContaoLoginIfAccountIsDisabled) {
            $this->dispatchInvalidLoginAttemptEvent(InvalidLoginAttemptEvent::FAILED_CHECK_IS_ACCOUNT_ENABLED, $contaoScope, $resourceOwner, $contaoUser);

            throw new RedirectResponseException($session->get('failurePath'));
        }

        if ($flashBag->has($flashBagKey)) {
            $flashBag->clear();
        }

        // Log in as a Contao backend or frontend user.
        $this->interactiveLogin->login($contaoUser);

        $targetPath = $session->get('targetPath');

        $session->clear();

        // Contao system log
        $logText = sprintf(
            '%s User "%s" [%s] has logged in with SAC OPENID CONNECT APP.',
            ContaoCoreBundle::SCOPE_FRONTEND === $contaoScope ? 'Frontend' : 'Backend',
            $resourceOwner->getFullName(),
            $resourceOwner->getSacMemberId()
        );
        $this->log($logText, __METHOD__, ContaoContext::ACCESS);

        // All ok. The Contao user has successfully logged in.
        // Let's redirect to the target page now.
        throw new RedirectResponseException($targetPath);
    }

    private function getSession(Request $request, string $scope): SessionBagInterface
    {
        if ('backend' === $scope) {
            $session = $request->getSession()->getBag('sac_oauth2_client_attr_backend');
        } else {
            $session = $request->getSession()->getBag('sac_oauth2_client_attr_frontend');
        }

        return $session;
    }

    private function log(string $logText, string $method, string $context): void
    {
        $this->logger?->info(
            $logText,
            ['contao' => new ContaoContext($method, $context, null)]
        );
    }

    private function dispatchInvalidLoginAttemptEvent(string $causeOfError, string $contaoScope, SwissAlpineClubResourceOwner $resourceOwner, ContaoUser $contaoUser = null): void
    {
        $event = new InvalidLoginAttemptEvent($causeOfError, $contaoScope, $resourceOwner, $contaoUser);
        $this->eventDispatcher->dispatch($event, InvalidLoginAttemptEvent::NAME);
    }
}
