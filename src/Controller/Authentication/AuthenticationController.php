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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Controller\Authentication;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\ModuleModel;
use Contao\System;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Event\InvalidLoginAttemptEvent;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Initializer;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\InteractiveLogin\InteractiveLogin;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Oidc\Oidc;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\User\RemoteUser;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\User\User;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class AuthenticationController.
 */
class AuthenticationController extends AbstractController
{
    public const CONTAO_SCOPE_FRONTEND = 'frontend';
    public const CONTAO_SCOPE_BACKEND = 'backend';
    private const OPENID_CONNECT_DEBUG_LOG = 'OPENID_CONNECT_DEBUG_LOG';

    private ContaoFramework $framework;
    private Initializer $initializer;
    private RequestStack $requestStack;
    private RemoteUser $remoteUser;
    private User $user;
    private InteractiveLogin $interactiveLogin;
    private Oidc $oidc;
    private EventDispatcherInterface $eventDispatcher;
    private LoggerInterface|null $logger;

    public function __construct(ContaoFramework $framework, Initializer $initializer, RequestStack $requestStack, RemoteUser $remoteUser, User $user, InteractiveLogin $interactiveLogin, Oidc $oidc, EventDispatcherInterface $eventDispatcher, LoggerInterface $logger = null)
    {
        $this->framework = $framework;
        $this->initializer = $initializer;
        $this->requestStack = $requestStack;
        $this->remoteUser = $remoteUser;
        $this->user = $user;
        $this->interactiveLogin = $interactiveLogin;
        $this->oidc = $oidc;
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
    }

    /**
     * Login frontend user.
     *
     * @throws \Exception
     *
     * @Route("/ssoauth/frontend", name="swiss_alpine_club_sso_login_frontend", defaults={"_scope" = self::CONTAO_SCOPE_FRONTEND, "_token_check" = false})
     */
    public function frontendUserAuthenticationAction(string $_scope): RedirectResponse
    {
        $this->initializer->initialize();

        $contaoScope = $_scope;

        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        /** @var ModuleModel $moduleModelAdapter */
        $moduleModelAdapter = $this->framework->getAdapter(ModuleModel::class);

        $container = $systemAdapter->getContainer();

        $isDebugMode = $systemAdapter->getContainer()->getParameter('sac_oauth2_client.oidc.debug_mode');
        $bagName = $systemAdapter->getContainer()->getParameter('sac_oauth2_client.session.attribute_bag_name');
        $flashBagKey = $container->getParameter('sac_oauth2_client.session.flash_bag_key');

        /** @var Session $session */
        $session = $this->requestStack->getCurrentRequest()->getSession()->getBag($bagName);

        $flashBag = $this->requestStack->getCurrentRequest()->getSession()->getFlashBag();

        /** @var bool $blnAutocreate */
        $blnAutocreate = $container->getParameter('sac_oauth2_client.oidc.autocreate_frontend_user');

        /** @var bool $blnAllowLoginToSacMembersOnly */
        $blnAllowLoginToSacMembersOnly = $container->getParameter('sac_oauth2_client.oidc.allow_frontend_login_to_sac_members_only');

        /** @var bool $blnAllowLoginToPredefinedSectionsOnly */
        $blnAllowLoginToPredefinedSectionsOnly = $container->getParameter('sac_oauth2_client.oidc.allow_frontend_login_to_predefined_section_members_only');

        /** @var bool $blnAllowFrontendLoginIfContaoAccountIsDisabled */
        $blnAllowFrontendLoginIfContaoAccountIsDisabled = $container->getParameter('sac_oauth2_client.oidc.allow_frontend_login_if_contao_account_is_disabled');

        // Set redirect uri
        $this->oidc->setProvider(['redirectUri' => $container->getParameter('sac_oauth2_client.oidc.client_auth_endpoint_frontend')]);

        if (!$this->oidc->hasAuthCode()) {
            return $this->oidc->getAuthCode();
        }

        $this->oidc->getAccessToken();

        $arrData = $session->get('arrData');

        if ($isDebugMode) {
            // Log resource owners details
            $text = sprintf('SAC oauth2 debug %s login. NAME: %s - SAC MEMBER ID: %s - ROLES: %s - DATA ALL: %s', $contaoScope, $arrData['name'], $arrData['contact_number'], $arrData['Roles'], json_encode($arrData));
            $this->log($text, __METHOD__, self::OPENID_CONNECT_DEBUG_LOG);
        }

        $this->remoteUser->create($arrData, $contaoScope);
        //$this->remoteUser->create($this->remoteUser->getMockUserData(true), $contaoScope); // Should end in an error message

        // Check if uuid/sub is set
        if (!$this->remoteUser->checkHasUuid()) {
            $this->dispatchInvalidLoginAttemptEvent(InvalidLoginAttemptEvent::FAILED_CHECK_HAS_UUID, $contaoScope, $this->remoteUser, null);

            return new RedirectResponse($session->get('failurePath'));
        }

        // Check if user is a SAC member
        if ($blnAllowLoginToSacMembersOnly) {
            if (!$this->remoteUser->checkIsSacMember()) {
                $this->dispatchInvalidLoginAttemptEvent(InvalidLoginAttemptEvent::FAILED_CHECK_IS_SAC_MEMBER, $contaoScope, $this->remoteUser, null);

                return new RedirectResponse($session->get('failurePath'));
            }
        }

        // Check if user is member of an allowed section
        if ($blnAllowLoginToPredefinedSectionsOnly) {
            if (!$this->remoteUser->checkIsMemberOfAllowedSection()) {
                $this->dispatchInvalidLoginAttemptEvent(InvalidLoginAttemptEvent::FAILED_CHECK_IS_MEMBER_OF_ALLOWED_SECTION, $contaoScope, $this->remoteUser, null);

                return new RedirectResponse($session->get('failurePath'));
            }
        }

        // Check has valid email address
        // This test should always be positive,
        // because creating an account at https://www.sac-cas.ch
        // requires already a valid email address
        if (!$this->remoteUser->checkHasValidEmailAddress()) {
            $this->dispatchInvalidLoginAttemptEvent(InvalidLoginAttemptEvent::FAILED_CHECK_HAS_VALID_EMAIL_ADDRESS, $contaoScope, $this->remoteUser, null);

            return new RedirectResponse($session->get('failurePath'));
        }

        // Initialize user
        $this->user->initialize($this->remoteUser, $contaoScope);

        // Create user if it doesn't exist.
        if ($blnAutocreate) {
            $this->user->createIfNotExists();
        }

        // Check if user exists
        if (!$this->user->checkUserExists()) {
            $this->dispatchInvalidLoginAttemptEvent(InvalidLoginAttemptEvent::FAILED_CHECK_USER_EXISTS, $contaoScope, $this->remoteUser, $this->user);

            return new RedirectResponse($session->get('failurePath'));
        }

        // Allow login: set tl_member.disable = ''
        $this->user->enableLogin();

        // Set tl_member.locked = 0
        $this->user->unlock();

        // Set tl_member.loginAttempts = 0
        $this->user->resetLoginAttempts();

        // Set tl_member.login = '1'
        $this->user->activateMemberAccount();

        // Update tl_member and tl_user
        $this->user->updateFrontendUser();
        $this->user->updateBackendUser();

        // Check if tl_member.disable == '' or tl_member.start and tl_member.stop are not in an allowed time range
        if (!$this->user->checkIsAccountEnabled() && !$blnAllowFrontendLoginIfContaoAccountIsDisabled) {
            $this->dispatchInvalidLoginAttemptEvent(InvalidLoginAttemptEvent::FAILED_CHECK_IS_ACCOUNT_ENABLED, $contaoScope, $this->remoteUser, $this->user);

            return new RedirectResponse($session->get('failurePath'));
        }

        if ($flashBag->has($flashBagKey)) {
            // User::checkIsAccountEnabled() will set a message if test was false
            $flashBag->clear();
        }

        // Log in user
        $this->interactiveLogin->login($this->user);

        // Add predefined frontend groups to contao frontend user
        // The groups have to be predefined in the frontend module settings
        $moduleModel = $moduleModelAdapter->findByPk($session->get('moduleId'));
        $this->user->addFrontendGroups($moduleModel);

        $targetPath = $session->get('targetPath');
        $session->clear();

        // All ok. User has successfully logged in.
        // Let's redirect to the target page now.
        return new RedirectResponse($targetPath);
    }

    /**
     * Login backend user.
     *
     * @throws \Exception
     *
     * @Route("/ssoauth/backend", name="swiss_alpine_club_sso_login_backend", defaults={"_scope" = self::CONTAO_SCOPE_BACKEND, "_token_check" = false})
     */
    public function backendUserAuthenticationAction(string $_scope): RedirectResponse
    {
        $this->initializer->initialize();

        $contaoScope = $_scope;

        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        $container = $systemAdapter->getContainer();

        $isDebugMode = $systemAdapter->getContainer()->getParameter('sac_oauth2_client.oidc.debug_mode');
        $bagName = $systemAdapter->getContainer()->getParameter('sac_oauth2_client.session.attribute_bag_name');
        $flashBagKey = $container->getParameter('sac_oauth2_client.session.flash_bag_key');

        /** @var Session $session */
        $session = $this->requestStack->getCurrentRequest()->getSession()->getBag($bagName);
        $flashBag = $this->requestStack->getCurrentRequest()->getSession()->getFlashBag();

        /** @var bool $blnAutocreate */
        $blnAutocreate = $container->getParameter('sac_oauth2_client.oidc.autocreate_backend_user');

        /** @var bool $blnAllowLoginToSacMembersOnly */
        $blnAllowLoginToSacMembersOnly = $container->getParameter('sac_oauth2_client.oidc.allow_backend_login_to_sac_members_only');

        /** @var bool $blnAllowLoginToPredefinedSectionsOnly */
        $blnAllowLoginToPredefinedSectionsOnly = $container->getParameter('sac_oauth2_client.oidc.allow_backend_login_to_predefined_section_members_only');

        /** @var $blnAllowBackendLoginIfContaoAccountIsDisabled */
        $blnAllowBackendLoginIfContaoAccountIsDisabled = $container->getParameter('sac_oauth2_client.oidc.allow_backend_login_if_contao_account_is_disabled');

        // Set redirect uri
        $this->oidc->setProvider(['redirectUri' => $container->getParameter('sac_oauth2_client.oidc.client_auth_endpoint_backend')]);

        if (!$this->oidc->hasAuthCode()) {
            return $this->oidc->getAuthCode();
        }

        $this->oidc->getAccessToken();

        $arrData = $session->get('arrData');

        if ($isDebugMode) {
            // Log resource owners details
            $text = sprintf('SAC oauth2 debug %s login. NAME: %s - SAC MEMBER ID: %s - ROLES: %s - DATA ALL: %s', $contaoScope, $arrData['name'], $arrData['contact_number'], $arrData['Roles'], json_encode($arrData));
            $this->log($text, __METHOD__, self::OPENID_CONNECT_DEBUG_LOG);
        }

        $this->remoteUser->create($arrData, $contaoScope);

        // Check if uuid/sub is set
        if (!$this->remoteUser->checkHasUuid()) {
            $this->dispatchInvalidLoginAttemptEvent(InvalidLoginAttemptEvent::FAILED_CHECK_HAS_UUID, $contaoScope, $this->remoteUser, null);

            return new RedirectResponse($session->get('failurePath'));
        }

        // Check if user is a SAC member
        if ($blnAllowLoginToSacMembersOnly) {
            if (!$this->remoteUser->checkIsSacMember()) {
                $this->dispatchInvalidLoginAttemptEvent(InvalidLoginAttemptEvent::FAILED_CHECK_IS_SAC_MEMBER, $contaoScope, $this->remoteUser, null);

                return new RedirectResponse($session->get('failurePath'));
            }
        }

        // Check if user is member of an allowed section
        if ($blnAllowLoginToPredefinedSectionsOnly) {
            if (!$this->remoteUser->checkIsMemberOfAllowedSection()) {
                $this->dispatchInvalidLoginAttemptEvent(InvalidLoginAttemptEvent::FAILED_CHECK_IS_MEMBER_OF_ALLOWED_SECTION, $contaoScope, $this->remoteUser, null);

                return new RedirectResponse($session->get('failurePath'));
            }
        }

        // Check has valid email address
        // This test should always be positive,
        // because creating an account at https://www.sac-cas.ch
        // requires already a valid email address
        if (!$this->remoteUser->checkHasValidEmailAddress()) {
            $this->dispatchInvalidLoginAttemptEvent(InvalidLoginAttemptEvent::FAILED_CHECK_HAS_VALID_EMAIL_ADDRESS, $contaoScope, $this->remoteUser, null);

            return new RedirectResponse($session->get('failurePath'));
        }

        // Initialize user
        $this->user->initialize($this->remoteUser, $contaoScope);

        // Create user, if it doesn't exist.
        // Not allowed to backend users!
        if ($blnAutocreate) {
            // $this->user->createIfNotExists();
        }

        // Check if user exists
        if (!$this->user->checkUserExists()) {
            $this->dispatchInvalidLoginAttemptEvent(InvalidLoginAttemptEvent::FAILED_CHECK_USER_EXISTS, $contaoScope, $this->remoteUser, $this->user);

            return new RedirectResponse($session->get('failurePath'));
        }

        // Allow login: set tl_user.disable = ''
        //$this->user->enableLogin();

        // Set tl_user.locked = 0
        $this->user->unlock();

        // Set tl_user.loginAttempts = 0
        $this->user->resetLoginAttempts();

        // Update tl_member and tl_user
        $this->user->updateFrontendUser();
        $this->user->updateBackendUser();

        // Check if tl_user.disable == '' or tl_user.login == '1' or tl_user.start and tl_user.stop are not in an allowed time range
        if (!$this->user->checkIsAccountEnabled() && !$blnAllowBackendLoginIfContaoAccountIsDisabled) {
            $this->dispatchInvalidLoginAttemptEvent(InvalidLoginAttemptEvent::FAILED_CHECK_IS_ACCOUNT_ENABLED, $contaoScope, $this->remoteUser, $this->user);

            return new RedirectResponse($session->get('failurePath'));
        }

        if ($flashBag->has($flashBagKey)) {
            // User::checkIsAccountEnabled() will set a message if test was false
            $flashBag->clear();
        }

        // Log in user
        $this->interactiveLogin->login($this->user);

        $targetPath = $session->get('targetPath');

        $session->clear();

        // All ok. User has successfully logged in.
        // Let's redirect to the target page now.
        return new RedirectResponse($targetPath);
    }

    /**
     * @Route("/ssoauth/send_logout_endpoint", name="swiss_alpine_club_sso_login_send_logout_endpoint")
     */
    public function sendLogoutEndpointAction(): JsonResponse
    {
        $this->framework->initialize();

        /** @var System $configAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        $data = [
            'success' => 'true',
            'logout_endpoint_url' => $systemAdapter->getContainer()->getParameter('sac_oauth2_client.oidc.auth_provider_endpoint_logout'),
        ];

        return new JsonResponse($data);
    }

    private function log(string $text, string $method, string $context): void
    {
        if (null !== $this->logger) {
            $this->logger->info(
                $text,
                ['contao' => new ContaoContext($method, $context, null)]
            );
        }
    }

    private function dispatchInvalidLoginAttemptEvent(string $causeOfError, string $contaoScope, RemoteUser $remoteUser, User $user = null): void
    {
        $event = new InvalidLoginAttemptEvent($causeOfError, $contaoScope, $remoteUser, $user);
        $this->eventDispatcher->dispatch($event, InvalidLoginAttemptEvent::NAME);
    }
}
