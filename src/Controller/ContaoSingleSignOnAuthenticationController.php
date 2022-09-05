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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Controller;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\System;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Config\ContaoLogConfig;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Event\InvalidLoginAttemptEvent;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Exception\BadQueryStringException;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Initializer;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\InteractiveLogin\InteractiveLogin;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Provider\SwissAlpineClub;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Provider\SwissAlpineClubResourceOwner;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\User\ContaoUser;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\User\ContaoUserFactory;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Validator\LoginValidator;
use Psr\Log\LoggerInterface;
use Safe\Exceptions\JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Annotation\Route;
use function Safe\json_encode;

/**
 * This controller handles the SSO authentication flow.
 * Add your custom controller with a higher priority, if you want to override the existing controller.
 *
 * @Route("/ssoauth/frontend", priority=10, name="swiss_alpine_club_sso_login_frontend", defaults={"_scope" = "frontend", "_token_check" = false})
 * @Route("/ssoauth/backend", priority=10, name="swiss_alpine_club_sso_login_backend", defaults={"_scope" = "backend", "_token_check" = false})
 */
class ContaoSingleSignOnAuthenticationController extends AbstractController
{
    private ContaoFramework $framework;
    private Initializer $initializer;
    private RequestStack $requestStack;
    private LoginValidator $loginValidator;
    private ContaoUserFactory $contaoUserFactory;
    private InteractiveLogin $interactiveLogin;
    private EventDispatcherInterface $eventDispatcher;
    private LoggerInterface|null $logger;

    // Adapter
    private Adapter $system;

    public function __construct(ContaoFramework $framework, Initializer $initializer, RequestStack $requestStack, LoginValidator $loginValidator, ContaoUserFactory $contaoUserFactory, InteractiveLogin $interactiveLogin, EventDispatcherInterface $eventDispatcher, LoggerInterface $logger = null)
    {
        $this->framework = $framework;
        $this->initializer = $initializer;
        $this->requestStack = $requestStack;
        $this->loginValidator = $loginValidator;
        $this->contaoUserFactory = $contaoUserFactory;
        $this->interactiveLogin = $interactiveLogin;
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;

        // Adapters
        $this->system = $this->framework->getAdapter(System::class);
    }

    /**
     * @throws JsonException
     */
    public function __invoke(string $_scope): void
    {
        $contaoScope = $_scope;

        $this->initializer->initialize();
        $container = $this->system->getContainer();

        $isDebugMode = $container->getParameter('sac_oauth2_client.oidc.debug_mode');
        $bagName = $container->getParameter('sac_oauth2_client.session.attribute_bag_name');
        $flashBagKey = $container->getParameter('sac_oauth2_client.session.flash_bag_key');

        /** @var Session $session */
        $session = $this->requestStack->getCurrentRequest()->getSession()->getBag($bagName);
        $flashBag = $this->requestStack->getCurrentRequest()->getSession()->getFlashBag();

        /** @var bool $blnAutoCreateContaoUser */
        $blnAutoCreateContaoUser = $container->getParameter('sac_oauth2_client.oidc.auto_create_'.$contaoScope.'_user');

        /** @var bool $blnAllowLoginToSacMembersOnly */
        $blnAllowLoginToSacMembersOnly = $container->getParameter('sac_oauth2_client.oidc.allow_'.$contaoScope.'_login_to_sac_members_only');

        /** @var bool $blnAllowLoginToPredefinedSectionsOnly */
        $blnAllowLoginToPredefinedSectionsOnly = $container->getParameter('sac_oauth2_client.oidc.allow_'.$contaoScope.'_login_to_predefined_section_members_only');

        /** @var bool $blnAllowContaoLoginIfAccountIsDisabled */
        $blnAllowContaoLoginIfAccountIsDisabled = $container->getParameter('sac_oauth2_client.oidc.allow_'.$contaoScope.'_login_if_contao_account_is_disabled');

        // Set redirect uri
        $provider = $this->createProvider(['redirectUri' => $container->getParameter('sac_oauth2_client.oidc.client_auth_endpoint_'.$contaoScope)]);

        // Redirect user to the authorization endpoint
        if (!$this->hasAuthCode()) {
            $this->redirectToAuthorizationUrl($provider);
        }

        // Get the access Token
        $accessToken = $this->getAccessToken($provider);

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

        $this->loginValidator->setContaoScope($contaoScope);

        // Check if uuid/sub is set
        if (!$this->loginValidator->checkHasUuid($resourceOwner)) {
            $this->dispatchInvalidLoginAttemptEvent(InvalidLoginAttemptEvent::FAILED_CHECK_HAS_UUID, $contaoScope, $resourceOwner);

            throw new RedirectResponseException($session->get('failurePath'));
        }

        // Check if user is a SAC member
        if ($blnAllowLoginToSacMembersOnly) {
            if (!$this->loginValidator->checkIsSacMember($resourceOwner)) {
                $this->dispatchInvalidLoginAttemptEvent(InvalidLoginAttemptEvent::FAILED_CHECK_IS_SAC_MEMBER, $contaoScope, $resourceOwner);

                throw new RedirectResponseException($session->get('failurePath'));
            }
        }

        // Check if user is member of an allowed section
        if ($blnAllowLoginToPredefinedSectionsOnly) {
            if (!$this->loginValidator->checkIsMemberOfAllowedSection($resourceOwner)) {
                $this->dispatchInvalidLoginAttemptEvent(InvalidLoginAttemptEvent::FAILED_CHECK_IS_MEMBER_OF_ALLOWED_SECTION, $contaoScope, $resourceOwner);

                throw new RedirectResponseException($session->get('failurePath'));
            }
        }

        // Check has valid email address
        // This test should always be positive,
        // because creating an account at https://www.sac-cas.ch
        // requires already a valid email address
        if (!$this->loginValidator->checkHasValidEmailAddress($resourceOwner)) {
            $this->dispatchInvalidLoginAttemptEvent(InvalidLoginAttemptEvent::FAILED_CHECK_HAS_VALID_EMAIL_ADDRESS, $contaoScope, $resourceOwner);

            throw new RedirectResponseException($session->get('failurePath'));
        }

        // Create the user wrapper object
        $contaoUser = $this->contaoUserFactory->createContaoUser($resourceOwner, $contaoScope);

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

    protected function createProvider(array $arrData = []): AbstractProvider
    {
        $arrProviderConfig = array_merge(
            [
                // The client ID assigned to you by the provider
                'clientId' => $this->system->getContainer()->getParameter('sac_oauth2_client.oidc.client_id'),
                // The client password assigned to you by the provider
                'clientSecret' => $this->system->getContainer()->getParameter('sac_oauth2_client.oidc.client_secret'),
                // Absolute callback url to your system (must be registered by service provider.)
                'urlAuthorize' => $this->system->getContainer()->getParameter('sac_oauth2_client.oidc.auth_provider_endpoint_authorize'),
                'urlAccessToken' => $this->system->getContainer()->getParameter('sac_oauth2_client.oidc.auth_provider_endpoint_token'),
                'urlResourceOwnerDetails' => $this->system->getContainer()->getParameter('sac_oauth2_client.oidc.auth_provider_endpoint_userinfo'),
                'scopes' => ['openid'],
            ],
            $arrData
        );

        return new SwissAlpineClub($arrProviderConfig, []);
    }

    /**
     * @throws \Exception
     */
    protected function hasAuthCode(): bool
    {
        $request = $this->requestStack->getCurrentRequest();

        return $request->query->has('code');
    }

    /**
     * @throws \Exception
     */
    protected function redirectToAuthorizationUrl(AbstractProvider $provider): void
    {
        /** @var string $bagName */
        $bagName = $this->system->getContainer()->getParameter('sac_oauth2_client.session.attribute_bag_name');

        /** @var Session $session */
        $session = $this->requestStack->getCurrentRequest()->getSession()->getBag($bagName);

        // Fetch the authorization URL from the provider;
        // this returns the urlAuthorize option and generates and applies any necessary parameters
        // (e.g. state).
        $authorizationUrl = $provider->getAuthorizationUrl();

        // Get the state and store it to the session.
        $session->set('oauth2state', $provider->getState());

        // Redirect the user to the authorization URL.
        throw new RedirectResponseException($authorizationUrl);
    }

    /**
     * @throws \Exception
     */
    protected function getAccessToken(AbstractProvider $provider): AccessToken
    {
        $request = $this->requestStack->getCurrentRequest();

        /** @var string $bagName */
        $bagName = $this->system->getContainer()->getParameter('sac_oauth2_client.session.attribute_bag_name');

        /** @var Session $session */
        $session = $this->requestStack->getCurrentRequest()->getSession()->getBag($bagName);

        try {
            if (!$this->hasAuthCode()) {
                throw new BadQueryStringException('Authorization code not found.');
            }

            if (empty($request->query->get('state')) || ($request->query->get('state') !== $session->get('oauth2state'))) {
                throw new BadQueryStringException('Invalid OAuth2 state.');
            }

            // Try to get an access token using the authorization code grant.
            $accessToken = $provider->getAccessToken('authorization_code', [
                'code' => $request->query->get('code'),
            ]);
        } catch (BadQueryStringException|IdentityProviderException $e) {
            exit($e->getMessage());
        }

        return $accessToken;
    }

    protected function log(string $logText, string $method, string $context): void
    {
        if (null !== $this->logger) {
            $this->logger->info(
                $logText,
                ['contao' => new ContaoContext($method, $context, null)]
            );
        }
    }

    protected function dispatchInvalidLoginAttemptEvent(string $causeOfError, string $contaoScope, SwissAlpineClubResourceOwner $resourceOwner, ContaoUser $contaoUser = null): void
    {
        $event = new InvalidLoginAttemptEvent($causeOfError, $contaoScope, $resourceOwner, $contaoUser);
        $this->eventDispatcher->dispatch($event, InvalidLoginAttemptEvent::NAME);
    }
}
