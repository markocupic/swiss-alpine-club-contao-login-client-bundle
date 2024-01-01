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
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Client\Exception\InvalidStateException;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Client\OAuth2Client;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Config\ContaoLogConfig;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\ErrorMessage\ErrorMessage;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\ErrorMessage\ErrorMessageManager;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Event\InvalidLoginAttemptEvent;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\InteractiveLogin\InteractiveLogin;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\OAuth\OAuthUser;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\OAuth\OAuthUserChecker;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\User\ContaoUser;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\User\ContaoUserFactory;
use Psr\Log\LoggerInterface;
use Safe\Exceptions\JsonException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use function Safe\json_encode;

class Authenticator
{
    private Adapter $system;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly ContaoUserFactory $contaoUserFactory,
        private readonly ErrorMessageManager $errorMessageManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly InteractiveLogin $interactiveLogin,
        private readonly OAuthUserChecker $oAuthUserChecker,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface|null $logger = null,
    ) {
        // Adapters
        $this->system = $this->framework->getAdapter(System::class);
    }

    /**
     * @throws IdentityProviderException
     * @throws JsonException
     */
    public function authenticateContaoUser(OAuth2Client $oAuth2Client): void
    {
        $allowedScopes = [
            ContaoCoreBundle::SCOPE_BACKEND,
            ContaoCoreBundle::SCOPE_FRONTEND,
        ];

        $contaoScope = $oAuth2Client->getContaoScope();

        if (!\in_array($contaoScope, $allowedScopes, true)) {
            throw new \InvalidArgumentException(sprintf('The Contao Scope must be either "%s" "%s" given.', implode('" or "', $allowedScopes), $contaoScope));
        }

        $container = $this->system->getContainer();

        $isDebugMode = $container->getParameter('sac_oauth2_client.oidc.debug_mode');

        /** @var bool $blnAutoCreateContaoUser */
        $blnAutoCreateContaoUser = $container->getParameter('sac_oauth2_client.oidc.auto_create_'.$contaoScope.'_user');

        /** @var bool $blnAllowLoginToSacMembersOnly */
        $blnAllowLoginToSacMembersOnly = $container->getParameter('sac_oauth2_client.oidc.allow_'.$contaoScope.'_login_to_sac_members_only');

        /** @var bool $blnAllowLoginToPredefinedSectionsOnly */
        $blnAllowLoginToPredefinedSectionsOnly = $container->getParameter('sac_oauth2_client.oidc.allow_'.$contaoScope.'_login_to_predefined_section_members_only');

        /** @var bool $blnAllowContaoLoginIfAccountIsDisabled */
        $blnAllowContaoLoginIfAccountIsDisabled = $container->getParameter('sac_oauth2_client.oidc.allow_'.$contaoScope.'_login_if_contao_account_is_disabled');

        try {
            // Get the OAuth user also named "resource owner"
            /** @var OAuthUser $oAuthUser */
            $oAuthUser = $oAuth2Client->fetchUser();
        } catch (InvalidStateException $e) {
            $this->log($e->getMessage().' Code: '.$e->getCode(), __METHOD__, ContaoLogConfig::SAC_OAUTH2_DEBUG_LOG);

            $this->errorMessageManager->add2Flash(
                new ErrorMessage(
                    ErrorMessage::LEVEL_ERROR,
                    $this->translator->trans('ERR.sacOidcLoginError_invalidState_matter', [], 'contao_default'),
                    $this->translator->trans('ERR.sacOidcLoginError_invalidState_howToFix', [], 'contao_default'),
                )
            );

            throw new RedirectResponseException($oAuth2Client->getFailurePath());
        } catch (\Exception $e) {
            $this->log($e->getMessage().' Code: '.$e->getCode(), __METHOD__, ContaoLogConfig::SAC_OAUTH2_DEBUG_LOG);

            $this->errorMessageManager->add2Flash(
                new ErrorMessage(
                    ErrorMessage::LEVEL_ERROR,
                    $this->translator->trans('ERR.sacOidcLoginError_unexpectedError_matter', [], 'contao_default'),
                    $this->translator->trans('ERR.sacOidcLoginError_unexpectedError_howToFix', [], 'contao_default'),
                )
            );

            throw new RedirectResponseException($oAuth2Client->getFailurePath());
        }

        // For testing & debugging purposes only
        //$oAuthUser->overrideData($oAuthUser->getDummyResourceOwnerData(true));

        if ($isDebugMode) {
            // Log OAuth user details
            $logText = sprintf(
                'SAC oauth2 debug %s login. NAME: %s - SAC MEMBER ID: %s - ROLES: %s - DATA ALL: %s',
                $contaoScope,
                $oAuthUser->getFullName(),
                $oAuthUser->getSacMemberId(),
                $oAuthUser->getRolesAsString(),
                json_encode($oAuthUser->toArray()),
            );

            $this->log($logText, __METHOD__, ContaoLogConfig::SAC_OAUTH2_DEBUG_LOG);
        }

        // Check if uuid/sub is set
        if (!$this->oAuthUserChecker->checkHasUuid($oAuthUser)) {
            $this->dispatchInvalidLoginAttemptEvent(InvalidLoginAttemptEvent::FAILED_CHECK_HAS_UUID, $contaoScope, $oAuthUser);

            throw new RedirectResponseException($oAuth2Client->getFailurePath());
        }

        // Check if user is a SAC member
        if ($blnAllowLoginToSacMembersOnly) {
            if (!$this->oAuthUserChecker->checkIsSacMember($oAuthUser)) {
                $this->dispatchInvalidLoginAttemptEvent(InvalidLoginAttemptEvent::FAILED_CHECK_IS_SAC_MEMBER, $contaoScope, $oAuthUser);

                throw new RedirectResponseException($oAuth2Client->getFailurePath());
            }
        }

        // Check if user is member of an allowed section
        if ($blnAllowLoginToPredefinedSectionsOnly) {
            if (!$this->oAuthUserChecker->checkIsMemberOfAllowedSection($oAuthUser, $contaoScope)) {
                $this->dispatchInvalidLoginAttemptEvent(InvalidLoginAttemptEvent::FAILED_CHECK_IS_MEMBER_OF_ALLOWED_SECTION, $contaoScope, $oAuthUser);

                throw new RedirectResponseException($oAuth2Client->getFailurePath());
            }
        }

        // Check has valid email address
        // This test should always be positive,
        // because creating an account at https://www.sac-cas.ch
        // requires already a valid email address
        if (!$this->oAuthUserChecker->checkHasValidEmailAddress($oAuthUser)) {
            $this->dispatchInvalidLoginAttemptEvent(InvalidLoginAttemptEvent::FAILED_CHECK_HAS_VALID_EMAIL_ADDRESS, $contaoScope, $oAuthUser);

            throw new RedirectResponseException($oAuth2Client->getFailurePath());
        }

        // Create the user wrapper object
        $contaoUser = $this->contaoUserFactory->loadContaoUser($oAuthUser, $contaoScope);

        // Create Contao frontend or backend user, if it doesn't exist.
        if (ContaoCoreBundle::SCOPE_FRONTEND === $contaoScope) {
            if ($blnAutoCreateContaoUser) {
                $contaoUser->createIfNotExists();
            }
        }

        // if $contaoScope === 'backend': Check if Contao backend user exists
        // if $contaoScope === 'frontend': Check if Contao frontend user exists
        if (!$contaoUser->checkUserExists()) {
            $this->dispatchInvalidLoginAttemptEvent(InvalidLoginAttemptEvent::FAILED_CHECK_USER_EXISTS, $contaoScope, $oAuthUser, $contaoUser);

            throw new RedirectResponseException($oAuth2Client->getFailurePath());
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
            $this->dispatchInvalidLoginAttemptEvent(InvalidLoginAttemptEvent::FAILED_CHECK_IS_ACCOUNT_ENABLED, $contaoScope, $oAuthUser, $contaoUser);

            throw new RedirectResponseException($oAuth2Client->getFailurePath());
        }

        // The flash bag should actually be empty. Let's clear it to be on the safe side.
        $this->errorMessageManager->clearFlash();

        // Log in as a Contao backend or frontend user.
        $this->interactiveLogin->login($contaoUser);

        $targetPath = $oAuth2Client->getTargetPath();

        // Clear the session
        $oAuth2Client->getSession()->clear();

        // Contao system log
        $logText = sprintf(
            '%s User "%s" [%s] has logged in with SAC OPENID CONNECT APP.',
            ContaoCoreBundle::SCOPE_FRONTEND === $contaoScope ? 'Frontend' : 'Backend',
            $oAuthUser->getFullName(),
            $oAuthUser->getSacMemberId()
        );
        $this->log($logText, __METHOD__, ContaoContext::ACCESS);

        // All ok. The Contao user has successfully logged in.
        // Let's redirect to the target page now.
        throw new RedirectResponseException($targetPath);
    }

    private function log(string $logText, string $method, string $context): void
    {
        $this->logger?->info(
            $logText,
            ['contao' => new ContaoContext($method, $context, null)]
        );
    }

    private function dispatchInvalidLoginAttemptEvent(string $causeOfError, string $contaoScope, OAuthUser $oAuthUser, ContaoUser $contaoUser = null): void
    {
        $event = new InvalidLoginAttemptEvent($causeOfError, $contaoScope, $oAuthUser, $contaoUser);
        $this->eventDispatcher->dispatch($event, InvalidLoginAttemptEvent::NAME);
    }
}
