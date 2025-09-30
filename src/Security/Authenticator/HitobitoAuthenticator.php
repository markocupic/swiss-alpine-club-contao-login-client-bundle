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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\Authenticator;

use Codefog\HasteBundle\UrlParser;
use Contao\CoreBundle\ContaoCoreBundle;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\CoreBundle\Security\Authentication\AuthenticationSuccessHandler;
use Contao\User;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\Types;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Config\ContaoLogConfig;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Controller\RedirectController;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\ErrorMessage\ErrorMessage;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\ErrorMessage\ErrorMessageManager;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Event\AuthenticationFailureEvent;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\OAuth2\Client\OAuth2Client;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\OAuth2\Client\OAuth2ClientFactory;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\OAuth2\Client\Provider\Hitobito;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\Authenticator\Exception\ContaoBackendUserNotFoundAuthenticationException;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\Authenticator\Exception\ContaoFrontendUserLoginNotEnabledAuthenticationException;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\Authenticator\Exception\ContaoFrontendUserNotFoundAuthenticationException;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\Authenticator\Exception\ContaoUserDisabledAuthenticationException;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\Authenticator\Exception\InvalidStateAuthenticationException;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\Authenticator\Exception\MissingSacMembershipAuthenticationException;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\Authenticator\Exception\NotMemberOfAllowedSectionAuthenticationException;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\Authenticator\Exception\ResourceOwnerHasInvalidEmailAuthenticationException;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\Authenticator\Exception\ResourceOwnerHasInvalidSacMemberIdException;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\Authenticator\Exception\UnexpectedAuthenticationException;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\OAuth\OAuthUser;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\OAuth\OAuthUserChecker;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\User\ContaoUserFactory;
use Psr\Log\LoggerInterface;
use Scheb\TwoFactorBundle\Security\Http\Authenticator\TwoFactorAuthenticator;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Autoconfigure(public: true)]
class HitobitoAuthenticator extends AbstractAuthenticator
{
    public const string NAME = 'SAC_OAUTH2_AUTHENTICATOR';

    public function __construct(
        #[Autowire('@contao.security.authentication_success_handler')]
        private readonly AuthenticationSuccessHandler $authenticationSuccessHandler,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly Connection $connection,
        private readonly ContaoFramework $framework,
        #[Autowire('@markocupic.sac_oauth2_client.security.user.contao_user_factory')]
        private readonly ContaoUserFactory $contaoUserFactory,
        #[Autowire('@markocupic.sac_oauth2_client.error_message.error_message_manager')]
        private readonly ErrorMessageManager $errorMessageManager,
        #[Autowire('@markocupic.sac_oauth2_client.oauth2.client.oauth2_client_factory')]
        private readonly OAuth2ClientFactory $oAuth2ClientFactory,
        #[Autowire('@markocupic.sac_oauth2_client.oauth2.security.oauth.oauth_user_checker')]
        private readonly OAuthUserChecker $oauthUserChecker,
        private readonly RouterInterface $router,
        private readonly ScopeMatcher $scopeMatcher,
        private readonly TranslatorInterface $translator,
        private readonly UrlParser $urlParser,
        #[Autowire('%sac_oauth2_client.oidc.debug_mode%')]
        private readonly bool $isDebugMode,
        #[Autowire('%sac_oauth2_client.oidc.auto_create_backend_user%')]
        private readonly bool $autoCreateBackendUser,
        #[Autowire('%sac_oauth2_client.oidc.auto_create_frontend_user%')]
        private readonly bool $autoCreateFrontendUser,
        #[Autowire('%sac_oauth2_client.oidc.allow_backend_login_to_sac_members_only%')]
        private readonly bool $allowBackendLoginToSacMembersOnly,
        #[Autowire('%sac_oauth2_client.oidc.allow_frontend_login_to_sac_members_only%')]
        private readonly bool $allowFrontendLoginToSacMembersOnly,
        #[Autowire('%sac_oauth2_client.oidc.allow_backend_login_to_predefined_section_members_only%')]
        private readonly bool $allowBackendLoginToPredefinedSectionMembersOnly,
        #[Autowire('%sac_oauth2_client.oidc.allow_frontend_login_to_predefined_section_members_only%')]
        private readonly bool $allowFrontendLoginToPredefinedSectionMembersOnly,
        #[Autowire('%sac_oauth2_client.oidc.allow_backend_login_if_contao_account_is_disabled%')]
        private readonly bool $allowBackendLoginIfContaoAccountIsDisabled,
        #[Autowire('%sac_oauth2_client.oidc.allow_frontend_login_if_contao_account_is_disabled%')]
        private readonly bool $allowFrontendLoginIfContaoAccountIsDisabled,
        private readonly LoggerInterface|null $contaoAccessLogger = null,
    ) {
    }

    public function supports(Request $request): bool
    {
        if (empty($request->query->get('code'))) {
            return false;
        }

        $isContaoScope = match ($request->attributes->get('_scope')) {
            ContaoCoreBundle::SCOPE_BACKEND, ContaoCoreBundle::SCOPE_FRONTEND => true,
            default => false,
        };

        if (!$isContaoScope) {
            return false;
        }

        return match ($request->attributes->get('_route')) {
            RedirectController::ROUTE_BACKEND, RedirectController::ROUTE_FRONTEND => true,
            default => false,
        };
    }

    public function authorize(Request $request, AuthenticationException|null $authException = null): RedirectResponse
    {
        $oAuth2Client = $this->oAuth2ClientFactory->createOAuth2Client($request);

        // Fetch the authorization URL from the provider; this returns the urlAuthorize
        // option and generates and applies any necessary parameters (e.g. state).
        // authorizationUrl =
        // $oAuth2Client->getOAuth2Provider()->getAuthorizationUrl(['response_type' =>
        // 'id_token','response_mode' => 'query']);
        $authorizationUrl = $oAuth2Client->getOAuth2Provider()->getAuthorizationUrl(['response_mode' => 'query']);

        $sessionBag = $this->getSessionBag($request);
        $sessionBag->set('oauth2state', $oAuth2Client->getOAuth2Provider()->getState());

        return new RedirectResponse($authorizationUrl);
    }

    public function authenticate(Request $request): Passport
    {
        $this->framework->initialize();

        $contaoScope = $request->attributes->get('_scope');

        $blnAutoCreateContaoUser = ContaoCoreBundle::SCOPE_FRONTEND === $contaoScope ? $this->autoCreateFrontendUser : $this->autoCreateBackendUser;
        $blnAllowLoginToSacMembersOnly = ContaoCoreBundle::SCOPE_FRONTEND === $contaoScope ? $this->allowFrontendLoginToSacMembersOnly : $this->allowBackendLoginToSacMembersOnly;
        $blnAllowLoginToPredefinedSectionsOnly = ContaoCoreBundle::SCOPE_FRONTEND === $contaoScope ? $this->allowFrontendLoginToPredefinedSectionMembersOnly : $this->allowBackendLoginToPredefinedSectionMembersOnly;
        $blnAllowContaoLoginIfAccountIsDisabled = ContaoCoreBundle::SCOPE_FRONTEND === $contaoScope ? $this->allowFrontendLoginIfContaoAccountIsDisabled : $this->allowBackendLoginIfContaoAccountIsDisabled;

        try {
            /** @var OAuth2Client $client */
            $oAuth2Client = $this->oAuth2ClientFactory->createOAuth2Client($request);

            if (!$oAuth2Client->hasValidOAuth2State()) {
                $this->throwWithMessage(
                    $request,
                    ErrorMessage::LEVEL_ERROR,
                    InvalidStateAuthenticationException::class,
                    null,
                );
            }

            $oAuth2Provider = $oAuth2Client->getOAuth2Provider();

            // Try to get an access token using the authorization code grant.
            $accessToken = $oAuth2Provider->getAccessToken('authorization_code', [
                'code' => $request->query->get('code'),
            ]);

            // Get the resource owner object.
            /** @var OAuthUser $resourceOwner */
            $resourceOwner = $oAuth2Client->fetchUserFromToken($accessToken);

            // Create the resource owner wrapper, with which we will now do a handful of checks.
            $oAuthUser = $oAuth2Provider->getResourceOwner($accessToken);

            if ($this->isDebugMode) {
                // Store OAuth claims to Contao system log.
                $logText = \sprintf(
                    'SAC oauth2 debug %s login. NAME: %s - SAC MEMBER ID: %s - ROLES: %s - DATA ALL: %s',
                    $contaoScope,
                    $resourceOwner->getFullName(),
                    $resourceOwner->getSacMemberId(),
                    $resourceOwner->getRolesAsString(),
                    json_encode($resourceOwner->toArray(), JSON_UNESCAPED_SLASHES), // Do not escape slashes in links: https://portal.sac-cas.ch/verify_membership/kfDSFsdf...
                );

                $this->contaoAccessLogger->info(
                    $logText,
                    ['contao' => new ContaoContext(__METHOD__, ContaoLogConfig::SAC_OAUTH2_DEBUG_LOG)],
                );
            }

            // Check if we can find a sac member id in resource owner claims.
            if (!$this->oauthUserChecker->checkHasSacMemberId($oAuthUser)) {
                $this->throwWithMessage(
                    $request,
                    ErrorMessage::LEVEL_WARNING,
                    ResourceOwnerHasInvalidSacMemberIdException::class,
                    $resourceOwner,
                );
            }

            // Capture Login Attempt Check if we can find an email address in the resource
            // owner claims.
            if (!$this->oauthUserChecker->checkHasValidEmailAddress($oAuthUser)) {
                $this->throwWithMessage(
                    $request,
                    ErrorMessage::LEVEL_WARNING,
                    ResourceOwnerHasInvalidEmailAuthenticationException::class,
                    $resourceOwner,
                    [$oAuthUser->getFirstName()],
                );
            }

            // Check if the resource owner is a member of the Swiss Alpine Club (SAC).
            if ($blnAllowLoginToSacMembersOnly) {
                if (!$this->oauthUserChecker->checkIsSacMember($oAuthUser, $contaoScope)) {
                    $this->throwWithMessage(
                        $request,
                        ErrorMessage::LEVEL_WARNING,
                        MissingSacMembershipAuthenticationException::class,
                        $resourceOwner,
                        [$oAuthUser->getFirstName()],
                    );
                }
            }

            // Check if the resource owner is a member of an allowed Swiss Alpine Club section.
            if ($blnAllowLoginToPredefinedSectionsOnly) {
                if (!$this->oauthUserChecker->checkIsMemberOfAllowedSection($oAuthUser, $contaoScope)) {
                    $this->throwWithMessage(
                        $request,
                        ErrorMessage::LEVEL_WARNING,
                        NotMemberOfAllowedSectionAuthenticationException::class,
                        $resourceOwner,
                        [$oAuthUser->getFirstName()],
                    );
                }
            }

            // Create the Contao user wrapper.
            $contaoUser = $this->contaoUserFactory->createContaoUser($oAuthUser, $contaoScope);

            // Create Contao frontend or backend user, if it doesn't exist.
            if ($this->scopeMatcher->isFrontendRequest($request)) {
                if ($blnAutoCreateContaoUser) {
                    $contaoUser->createIfNotExists();
                }
            }

            // Check if we can find the resource owner in Contao.
            if ($this->scopeMatcher->isFrontendRequest($request)) {
                if (!$contaoUser->checkFrontendUserExists()) {
                    $this->throwWithMessage(
                        $request,
                        ErrorMessage::LEVEL_WARNING,
                        ContaoFrontendUserNotFoundAuthenticationException::class,
                        $resourceOwner,
                        [$oAuthUser->getFirstName()],
                    );
                }
            } else {
                if (!$contaoUser->checkBackendUserExists()) {
                    $this->throwWithMessage(
                        $request,
                        ErrorMessage::LEVEL_WARNING,
                        ContaoBackendUserNotFoundAuthenticationException::class,
                        $resourceOwner,
                        [$oAuthUser->getFirstName()],
                    );
                }
            }

            // Allow login to frontend users only if account is not disabled.
            if ($blnAllowContaoLoginIfAccountIsDisabled && $this->scopeMatcher->isFrontendRequest($request)) {
                // Set tl_member.disable = false
                $contaoUser->activateMemberAccount();
            }

            // Check if tl_member.login is set to true
            if ($this->scopeMatcher->isFrontendRequest($request)) {
                if (!$contaoUser->checkFrontendLoginIsEnabled()) {
                    $this->throwWithMessage(
                        $request,
                        ErrorMessage::LEVEL_WARNING,
                        ContaoFrontendUserLoginNotEnabledAuthenticationException::class,
                        $resourceOwner,
                        [$oAuthUser->getFirstName()],
                    );
                }
            }

            // If Contao scope is 'backend': Check if tl_user.disable === false or
            // tl_user.start and tl_user.stop are not in an allowed time range If Contao
            // scope is 'frontend': Check if tl_member.disable === false or tl_member.start
            // and tl_member.stop are not in an allowed time range
            if (!$contaoUser->checkAccountIsNotDisabled() && !$blnAllowContaoLoginIfAccountIsDisabled) {
                $this->throwWithMessage(
                    $request,
                    ErrorMessage::LEVEL_WARNING,
                    ContaoUserDisabledAuthenticationException::class,
                    $resourceOwner,
                    [$oAuthUser->getFirstName()],
                );
            }

            // Update tl_member and tl_user.
            $contaoUser->updateFrontendUser();
            $contaoUser->updateBackendUser();

            return new SelfValidatingPassport(new UserBadge($contaoUser->getIdentifier()));
        } catch (IdentityProviderException|AuthenticationException $e) {
            throw new AuthenticationException($e->getMessage());
        } catch (\Exception $e) {
            $this->contaoAccessLogger->info($e->getMessage());

            $this->throwWithMessage(
                $request,
                ErrorMessage::LEVEL_ERROR,
                UnexpectedAuthenticationException::class,
                $resourceOwner ?? null,
            );
        }
    }

    /**
     * Bypass 2FA for this authenticator.
     */
    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        $token = parent::createToken($passport, $firewallName);

        $token->setAttribute('AUTHENTICATOR', self::NAME);

        $user = $token->getUser();

        if (!$user instanceof User) {
            return $token;
        }

        if ($user->useTwoFactor) {
            $token->setAttribute(TwoFactorAuthenticator::FLAG_2FA_COMPLETE, true);
        }

        return $token;
    }

    /**
     * @throws Exception
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): Response|null
    {
        $oAuth2Client = $this->oAuth2ClientFactory->createOAuth2Client($request);
        $request->request->set('_target_path', $oAuth2Client->getTargetPath());
        $request->request->set('_always_use_target_path', $oAuth2Client->getTargetPath());

        // Clear the session
        $this->getSessionBag($request)->clear();

        // The flash bag should actually be empty. Let's clear it anyway just to be on
        // the safe side.
        $this->errorMessageManager->clearFlash();

        // Reset login attempts
        $user = $token->getUser();
        $user->ssoLoginAttempts = 0;
        $user->save();

        // Get the user identifier aka sac member id
        $userIdentifier = $token->getUser()->getUserIdentifier();

        if ($this->scopeMatcher->isFrontendRequest($request)) {
            $contaoScope = ContaoCoreBundle::SCOPE_FRONTEND;
            $fullName = $this->connection->fetchOne(
                'SELECT CONCAT(firstname, " ", lastname) FROM tl_member WHERE username = :username',
                ['username' => $userIdentifier],
                ['username' => Types::STRING],
            );
        } else {
            $contaoScope = ContaoCoreBundle::SCOPE_BACKEND;
            $fullName = $this->connection->fetchOne(
                'SELECT name FROM tl_user WHERE username = :username',
                ['username' => $userIdentifier],
                ['username' => Types::STRING],
            );
        }

        // Contao system log
        $logSuccess = \sprintf(
            '%s User "%s" [%s] has logged in with SAC OPENID CONNECT APP.',
            strtoupper($contaoScope),
            $fullName,
            $userIdentifier,
        );

        $this->contaoAccessLogger->info($logSuccess);

        // Trigger the on authentication success handler from the Contao Core.
        return $this->authenticationSuccessHandler->onAuthenticationSuccess($request, $token);
    }

    /**
     * Do not use Contao Core's onAuthenticationFailure handler, because this leads to
     * an infinite redirection loop.
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response|null
    {
        $isFrontend = $this->scopeMatcher->isFrontendRequest($request);

        $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);

        // Get the failure path
        $oAuth2Client = $this->oAuth2ClientFactory->createOAuth2Client($request);
        $failurePath = base64_decode($oAuth2Client->getFailurePath(), true);

        // Let's play it safe and make sure we always have a redirect URL.
        if (!$failurePath) {
            if ($isFrontend) {
                $failurePath = $request->getSchemeAndHttpHost();
                $failurePath = $this->urlParser->addQueryString('sac-oidc-error=true', $failurePath);
            } else {
                $failurePath = $this->router->generate('contao_backend', [], UrlGeneratorInterface::ABSOLUTE_URL);
            }
        }

        return new RedirectResponse($failurePath);
    }

    protected function getSessionBag(Request $request): SessionBagInterface
    {
        if ($this->scopeMatcher->isBackendRequest($request)) {
            return $request->getSession()->getBag('sac_oauth2_client_attr_backend');
        }

        return $request->getSession()->getBag('sac_oauth2_client_attr_frontend');
    }

    protected function throwWithMessage(Request $request, string $errLevel, string $exceptionClass, ResourceOwnerInterface|null $resourceOwner = null, array $argsA = [], array $argsB = [], array $argsC = []): void
    {
        // Throw AuthenticationFailureEvent We use a listener to increment
        // tl_user.ssoLoginAttempts & tl_member.ssoLoginAttempts
        $event = new AuthenticationFailureEvent($request, $errLevel, $exceptionClass, $resourceOwner, [$argsA, $argsB, $argsC]);
        $this->eventDispatcher->dispatch($event);

        $msgKeyA = \sprintf('ERR.sacOidcLoginError_%s_matter', $exceptionClass::KEY);
        $msgKeyB = \sprintf('ERR.sacOidcLoginError_%s_howToFix', $exceptionClass::KEY);
        $msgKeyC = \sprintf('ERR.sacOidcLoginError_%s_explain', $exceptionClass::KEY);

        $this->errorMessageManager->add2Flash(
            new ErrorMessage(
                $errLevel,
                $this->translator->trans($msgKeyA, $argsA, 'contao_default'),
                $this->translator->trans($msgKeyB, $argsB, 'contao_default'),
                $this->translator->trans($msgKeyC, $argsC, 'contao_default'),
            ),
        );

        if (null !== $this->contaoAccessLogger && null !== $resourceOwner) {
            $oAuthUser = new OAuthUser($resourceOwner->toArray(), Hitobito::ACCESS_TOKEN_RESOURCE_OWNER_ID);

            // Log user claims, if login fails.
            $logText = \sprintf(
                'SAC %s Login has failed for: %s - SAC MEMBER ID: %s - REASON: %s - EMAIL: %s - ROLES: %s - DATA ALL: %s',
                $this->scopeMatcher->isFrontendRequest($request) ? 'Frontend' : 'Backend',
                $oAuthUser->getFullName(),
                $oAuthUser->getSacMemberId(),
                $exceptionClass::KEY,
                $oAuthUser->getEmail(),
                $this->isDebugMode ? $oAuthUser->getRolesAsString() : 'Please activate the debug mode to get more information about the user.',
                json_encode($oAuthUser->toArray(), JSON_UNESCAPED_SLASHES), // Do not escape slashes in links: https://portal.sac-cas.ch/verify_membership/kfDSFsdf...
            );

            $this->contaoAccessLogger->info($logText, [
                'contao' => new ContaoContext(
                    __METHOD__,
                    $this->scopeMatcher->isFrontendRequest($request) ? ContaoLogConfig::SAC_OAUTH2_FRONTEND_LOGIN_FAIL : ContaoLogConfig::SAC_OAUTH2_BACKEND_LOGIN_FAIL,
                ),
            ]);
        }

        throw new $exceptionClass($exceptionClass::MESSAGE);
    }
}
