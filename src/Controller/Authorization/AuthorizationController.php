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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Controller\Authorization;

use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\ModuleModel;
use Contao\System;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Exception\AppCheckFailedException;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Exception\InvalidRequestTokenException;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\InteractiveLogin\InteractiveLogin;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Oidc\Oidc;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\User\RemoteUser;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\User\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class AuthorizationController.
 */
class AuthorizationController extends AbstractController
{
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var Session
     */
    private $session;

    /**
     * @var RemoteUser
     */
    private $remoteUser;

    /**
     * @var User
     */
    private $user;

    /**
     * @var InteractiveLogin
     */
    private $interactiveLogin;

    /**
     * @var Oidc
     */
    private $oidc;

    /**
     * AuthorizationController constructor.
     */
    public function __construct(ContaoFramework $framework, Session $session, RemoteUser $remoteUser, User $user, InteractiveLogin $interactiveLogin, Oidc $oidc)
    {
        $this->framework = $framework;
        $this->session = $session;
        $this->remoteUser = $remoteUser;
        $this->user = $user;
        $this->interactiveLogin = $interactiveLogin;
        $this->oidc = $oidc;

        $this->framework->initialize();
    }

    /**
     * Login frontend user.
     *
     * @throws AppCheckFailedException
     * @throws InvalidRequestTokenException
     * @Route("/ssoauth/frontend", name="swiss_alpine_club_sso_login_frontend", defaults={"_scope" = "frontend", "_token_check" = false})
     */
    public function frontendUserAuthenticationAction(): void
    {
        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        /** @var ModuleModel $moduleModelAdapter */
        $moduleModelAdapter = $this->framework->getAdapter(ModuleModel::class);

        $contaoScope = 'frontend';

        $bagName = $systemAdapter->getContainer()->getParameter('markocupic_sac_sso_login.session.attribute_bag_name');

        /** @var Session $session */
        $session = $this->session->getBag($bagName);

        $blnAutocreate = $systemAdapter
            ->getContainer()
            ->getParameter('markocupic_sac_sso_login.oidc.autocreate_frontend_user')
        ;

        $blnAllowLoginToSacMembersOnly = $systemAdapter
            ->getContainer()
            ->getParameter('markocupic_sac_sso_login.oidc.allow_frontend_login_to_sac_members_only')
        ;

        $blnAllowLoginToPredefinedSectionsOnly = $systemAdapter
            ->getContainer()
            ->getParameter('markocupic_sac_sso_login.oidc.allow_frontend_login_to_predefined_section_members_only')
        ;

        // Set redirect uri
        $this->oidc->setProviderData(['redirectUri' => $systemAdapter->getContainer()->getParameter('markocupic_sac_sso_login.oidc.redirect_uri_frontend')]);

        // Run the authorization code flow
        if ($this->oidc->runOpenIdConnectFlow()) {
            $arrData = $session->get('arrData');

            $this->remoteUser->create($arrData, $contaoScope);
            //$this->remoteUser->create($this->remoteUser->getMockUserData(false)); // Should end in an error message

            // Check if uuid/sub is set
            $this->remoteUser->checkHasUuid();

            // Check if user is SAC member
            if ($blnAllowLoginToSacMembersOnly) {
                $this->remoteUser->checkIsSacMember();
            }

            // Check if user is member of an allowed section
            if ($blnAllowLoginToPredefinedSectionsOnly) {
                $this->remoteUser->checkIsMemberInAllowedSection();
            }

            // Check has valid email address
            // This test should be always positive,
            // beacause creating an account at www.sac-cas.ch
            // requires a valid email address
            $this->remoteUser->checkHasValidEmail();

            // Initialize user
            $this->user->initialize($this->remoteUser, $contaoScope);

            // Create User if it not exists
            if ($blnAutocreate) {
                $this->user->createIfNotExists();
            }

            // Check if user exists
            $this->user->checkUserExists();

            // Allow login: set tl_member.disable = ''
            $this->user->enableLogin();

            // Set tl_member.locked=0
            $this->user->unlock();

            // Set tl_member.loginAttempts=0
            $this->user->resetLoginAttempts();

            // Set tl_member.login='1'
            $this->user->activateLogin();

            // Update tl_member and tl_user
            $this->user->updateFrontendUser();
            $this->user->updateBackendUser();

            // Check if tl_member.disable == '' & tl_member.locked == 0 & tl_member.login == '1'
            $this->user->checkIsLoginAllowed();

            // Log in user
            $this->interactiveLogin->login($this->user);

            // Add predefined frontend groups to contao frontend user
            // The groups have to be predefined in the frontend module settings
            $moduleModel = $moduleModelAdapter->findByPk($session->get('moduleId'));
            $this->user->addFrontendGroups($moduleModel);

            $jumpToPath = $session->get('targetPath');
            $session->clear();

            // All ok. User has logged in
            // Let's redirect to target page now
            $controllerAdapter->redirect($jumpToPath);
        } else {
            $errorPage = $session->get('failurePath');
            $arrError = $session->get('lastOidcError', []);
            $flashBagKey = $systemAdapter->getContainer()->getParameter('markocupic_sac_sso_login.session.flash_bag_key');
            $this->session->getFlashBag()->add($flashBagKey, $arrError);
            $controllerAdapter->redirect($errorPage);
        }
    }

    /**
     * Login backend user.
     *
     * @throws AppCheckFailedException
     * @throws InvalidRequestTokenException
     * @Route("/ssoauth/backend", name="swiss_alpine_club_sso_login_backend", defaults={"_scope" = "backend", "_token_check" = false})
     */
    public function backendUserAuthenticationAction(): void
    {
        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        $contaoScope = 'backend';

        $bagName = $systemAdapter->getContainer()->getParameter('markocupic_sac_sso_login.session.attribute_bag_name');

        /** @var Session $session */
        $session = $this->session->getBag($bagName);

        $blnAutocreate = $systemAdapter
            ->getContainer()
            ->getParameter('markocupic_sac_sso_login.oidc.autocreate_backend_user')
        ;

        $blnAllowLoginToSacMembersOnly = $systemAdapter
            ->getContainer()
            ->getParameter('markocupic_sac_sso_login.oidc.allow_backend_login_to_sac_members_only')
        ;

        $blnAllowLoginToPredefinedSectionsOnly = $systemAdapter
            ->getContainer()
            ->getParameter('markocupic_sac_sso_login.oidc.allow_backend_login_to_predefined_section_members_only')
        ;

        // Set redirect uri
        $this->oidc->setProviderData(['redirectUri' => $systemAdapter->getContainer()->getParameter('markocupic_sac_sso_login.oidc.redirect_uri_backend')]);

        // Run the authorization code flow
        if ($this->oidc->runOpenIdConnectFlow()) {
            $arrData = $session->get('arrData');

            $this->remoteUser->create($arrData, $contaoScope);

            // Check if uuid/sub is set
            $this->remoteUser->checkHasUuid();

            // Check if user is SAC member
            if ($blnAllowLoginToSacMembersOnly) {
                $this->remoteUser->checkIsSacMember();
            }

            // Check if user is member of an allowed section
            if ($blnAllowLoginToPredefinedSectionsOnly) {
                $this->remoteUser->checkIsMemberInAllowedSection();
            }

            // Check has valid email address
            // This test should be always positive,
            // beacause creating an account at www.sac-cas.ch
            // requires a valid email address
            $this->remoteUser->checkHasValidEmail();

            // Initialize user
            $this->user->initialize($this->remoteUser, $contaoScope);

            // Create User if it not exists
            //Not allowed for backend users!
            if ($blnAutocreate) {
                // $this->user->createIfNotExists();
            }

            // Check if user exists
            $this->user->checkUserExists();

            // Allow login: set tl_user.disable = ''
            //$this->user->enableLogin();

            // Set tl_user.locked=0
            $this->user->unlock();

            // Set tl_user.loginAttempts=0
            $this->user->resetLoginAttempts();

            // Update tl_member and tl_user
            $this->user->updateFrontendUser();
            $this->user->updateBackendUser();

            // Check if tl_user.disable == '' & tl_user.locked == 0
            $this->user->checkIsLoginAllowed();

            // Log in user
            $this->interactiveLogin->login($this->user);

            $jumpToPath = $session->get('targetPath');

            $session->clear();

            // All ok. User has logged in
            // Let's redirect to target page now
            $controllerAdapter->redirect($jumpToPath);
        } else {
            $errorPage = $session->get('failurePath');
            $arrError = $session->get('lastOidcError', []);
            $flashBagKey = $systemAdapter->getContainer()->getParameter('markocupic_sac_sso_login.session.flash_bag_key');
            $this->session->getFlashBag()->add($flashBagKey, $arrError);
            $controllerAdapter->redirect($errorPage);
        }
    }

    /**
     * @Route("/ssoauth/send_logout_endpoint", name="swiss_alpine_club_sso_login_send_logout_endpoint")
     */
    public function sendLogoutEndpointAction(): JsonResponse
    {
        /** @var System $configAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        $data = [
            'success' => 'true',
            'logout_endpoint_url' => $systemAdapter->getContainer()->getParameter('markocupic_sac_sso_login.oidc.url_logout'),
        ];

        return new JsonResponse($data);
    }
}
