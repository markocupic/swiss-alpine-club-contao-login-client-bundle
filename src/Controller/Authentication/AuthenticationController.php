<?php

declare(strict_types=1);

/*
 * This file is part of Swiss Alpine Club Contao Login Client Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/swiss-alpine-club-contao-login-client-bundle
 */

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Controller\Authentication;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\ModuleModel;
use Contao\System;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\InteractiveLogin\InteractiveLogin;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Oidc\Oidc;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\User\RemoteUser;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\User\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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

    private ContaoFramework $framework;
    private RequestStack $requestStack;
    private RemoteUser $remoteUser;
    private User $user;
    private InteractiveLogin $interactiveLogin;
    private Oidc $oidc;

    /**
     * AuthenticationController constructor.
     */
    public function __construct(ContaoFramework $framework, RequestStack $requestStack, RemoteUser $remoteUser, User $user, InteractiveLogin $interactiveLogin, Oidc $oidc)
    {
        $this->framework = $framework;
        $this->requestStack = $requestStack;
        $this->remoteUser = $remoteUser;
        $this->user = $user;
        $this->interactiveLogin = $interactiveLogin;
        $this->oidc = $oidc;
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
        $this->framework->initialize();

        $contaoScope = $_scope;

        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        /** @var ModuleModel $moduleModelAdapter */
        $moduleModelAdapter = $this->framework->getAdapter(ModuleModel::class);

        $container = $systemAdapter->getContainer();

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

        $this->remoteUser->create($arrData, $contaoScope);
        //$this->remoteUser->create($this->remoteUser->getMockUserData(false)); // Should end in an error message

        // Check if uuid/sub is set
        if (!$this->remoteUser->checkHasUuid()) {
            return new RedirectResponse($session->get('failurePath'));
        }

        // Check if user is SAC member
        if ($blnAllowLoginToSacMembersOnly) {
            if (!$this->remoteUser->checkIsSacMember()) {
                return new RedirectResponse($session->get('failurePath'));
            }
        }

        // Check if user is member of an allowed section
        if ($blnAllowLoginToPredefinedSectionsOnly) {
            if (!$this->remoteUser->checkIsMemberInAllowedSection()) {
                return new RedirectResponse($session->get('failurePath'));
            }
        }

        // Check has valid email address
        // This test should always be positive,
        // because creating an account at www.sac-cas.ch
        // requires a valid email address
        if (!$this->remoteUser->checkHasValidEmail()) {
            return new RedirectResponse($session->get('failurePath'));
        }

        // Initialize user
        $this->user->initialize($this->remoteUser, $contaoScope);

        // Create User if it not exists
        if ($blnAutocreate) {
            $this->user->createIfNotExists();
        }

        // Check if user exists
        if (!$this->user->checkUserExists()) {
            return new RedirectResponse($session->get('failurePath'));
        }

        // Allow login: set tl_member.disable = ''
        $this->user->enableLogin();

        // Set tl_member.locked=0
        $this->user->unlock();

        // Set tl_member.loginAttempts=0
        $this->user->resetLoginAttempts();

        // Set tl_member.login='1'
        if ($blnAllowFrontendLoginIfContaoAccountIsDisabled) {
            $this->user->activateMemberAccount();
        }

        // Update tl_member and tl_user
        $this->user->updateFrontendUser();
        $this->user->updateBackendUser();

        // Check if tl_member.disable == '' or tl_member.login == '1' or tl_member.start and tl_member.stop are not in an allowed time range
        if (!$this->user->checkIsAccountEnabled() && !$blnAllowFrontendLoginIfContaoAccountIsDisabled) {
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

        // All ok. user has logged in
        // Let's redirect to the target page now
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
        $this->framework->initialize();

        $contaoScope = $_scope;

        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        $container = $systemAdapter->getContainer();

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

        $this->remoteUser->create($arrData, $contaoScope);

        // Check if uuid/sub is set
        if (!$this->remoteUser->checkHasUuid()) {
            return new RedirectResponse($session->get('failurePath'));
        }

        // Check if user is SAC member
        if ($blnAllowLoginToSacMembersOnly) {
            if (!$this->remoteUser->checkIsSacMember()) {
                return new RedirectResponse($session->get('failurePath'));
            }
        }

        // Check if user is member of an allowed section
        if ($blnAllowLoginToPredefinedSectionsOnly) {
            if (!$this->remoteUser->checkIsMemberInAllowedSection()) {
                return new RedirectResponse($session->get('failurePath'));
            }
        }

        // Check has valid email address
        // This test should always be positive,
        // because creating an account at www.sac-cas.ch
        // requires a valid email address
        if (!$this->remoteUser->checkHasValidEmail()) {
            return new RedirectResponse($session->get('failurePath'));
        }

        // Initialize user
        $this->user->initialize($this->remoteUser, $contaoScope);

        // Create user if it not exists
        // Not allowed to backend users!
        if ($blnAutocreate) {
            // $this->user->createIfNotExists();
        }

        // Check if user exists
        if (!$this->user->checkUserExists()) {
            return new RedirectResponse($session->get('failurePath'));
        }

        // Allow login: set tl_user.disable = ''
        //$this->user->enableLogin();

        // Set tl_user.locked=0
        $this->user->unlock();

        // Set tl_user.loginAttempts=0
        $this->user->resetLoginAttempts();

        // Update tl_member and tl_user
        $this->user->updateFrontendUser();
        $this->user->updateBackendUser();

        // Check if tl_user.disable == '' or tl_user.login == '1' or tl_user.start and tl_user.stop are not in an allowed time range
        if (!$this->user->checkIsAccountEnabled() && !$blnAllowBackendLoginIfContaoAccountIsDisabled) {
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

        // All ok. user has logged in
        // Let's redirect to the target page now
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
}
