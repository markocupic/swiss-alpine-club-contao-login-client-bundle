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
use Contao\BackendUser;
use Contao\CoreBundle\ContaoCoreBundle;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\CoreBundle\Routing\ContentUrlGenerator;
use Contao\CoreBundle\Routing\PageFinder;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\CoreBundle\Security\Authentication\AuthenticationSuccessHandler;
use Contao\PageModel;
use Contao\User;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\Types;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Config\ContaoLogConfig;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Controller\RedirectController;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\ErrorMessage\ErrorMessage;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\ErrorMessage\ErrorMessageManager;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Event\AuthenticationFailureEvent;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\OAuth2\Client\OAuth2ClientFactory;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\OAuth2\Client\Provider\Hitobito;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\Authenticator\Exception\SacLoginAuthenticationException;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\OAuth\OAuthUser;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\OAuth\OAuthUserChecker;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\RedirectPathValidator;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\User\ContaoUserFactory;
use Psr\Log\LoggerInterface;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Scheb\TwoFactorBundle\Security\Http\Authenticator\TwoFactorAuthenticator;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Autoconfigure(public: true)]
class HitobitoAuthenticator extends AbstractAuthenticator
{
    use TargetPathTrait;

    public const string NAME = 'SAC_OAUTH2_AUTHENTICATOR';

    /**
     * Where the member wanted to go, kept across the second factor. Not the target
     * path of the firewall - that one has to point at the page the member is parked
     * on while the code is entered.
     */
    public const string SESSION_KEY_TWO_FACTOR_TARGET_PATH = 'sac_oauth2_client.two_factor.target_path';

    public function __construct(
        #[Autowire('@contao.security.authentication_success_handler')]
        private readonly AuthenticationSuccessHandler $authenticationSuccessHandler,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly Connection $connection,
        private readonly ContaoFramework $framework,
        private readonly ContentUrlGenerator $contentUrlGenerator,
        private readonly PageFinder $pageFinder,
        #[Autowire('@markocupic.sac_oauth2_client.security.user.contao_user_factory')]
        private readonly ContaoUserFactory $contaoUserFactory,
        #[Autowire('@markocupic.sac_oauth2_client.error_message.error_message_manager')]
        private readonly ErrorMessageManager $errorMessageManager,
        #[Autowire('@markocupic.sac_oauth2_client.oauth2.client.oauth2_client_factory')]
        private readonly OAuth2ClientFactory $oAuth2ClientFactory,
        #[Autowire('@markocupic.sac_oauth2_client.oauth2.security.oauth.oauth_user_checker')]
        private readonly OAuthUserChecker $oauthUserChecker,
        private readonly RedirectPathValidator $redirectPathValidator,
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
        #[Autowire('%sac_oauth2_client.oidc.reactivate_disabled_frontend_user_on_login%')]
        private readonly bool $reactivateDisabledFrontendUserOnLogin,
        #[Autowire('%sac_oauth2_client.oidc.enforce_frontend_two_factor%')]
        private readonly bool $enforceFrontendTwoFactor,
        #[Autowire('%sac_oauth2_client.oidc.enforce_backend_two_factor%')]
        private readonly bool $enforceBackendTwoFactor,
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

        // Fetch the authorization URL from the provider;
        // this returns the urlAuthorize option and generates and applies any necessary parameters (e.g. state).
        $authorizationUrl = $oAuth2Client->getOAuth2Provider()->getAuthorizationUrl(['response_mode' => 'query']);

        $sessionBag = $this->getSessionBag($request);
        $sessionBag->set('oauth2state', $oAuth2Client->getOAuth2Provider()->getState());

        // Keep the PKCE code verifier for the token request.
        $oAuth2Client->storePkceCode();

        return new RedirectResponse($authorizationUrl);
    }

    public function authenticate(Request $request): Passport
    {
        $this->framework->initialize();

        $contaoScope = $request->attributes->get('_scope');

        $blnAutoCreateContaoUser = ContaoCoreBundle::SCOPE_FRONTEND === $contaoScope ? $this->autoCreateFrontendUser : $this->autoCreateBackendUser;
        $blnAllowLoginToSacMembersOnly = ContaoCoreBundle::SCOPE_FRONTEND === $contaoScope ? $this->allowFrontendLoginToSacMembersOnly : $this->allowBackendLoginToSacMembersOnly;
        $blnAllowLoginToPredefinedSectionsOnly = ContaoCoreBundle::SCOPE_FRONTEND === $contaoScope ? $this->allowFrontendLoginToPredefinedSectionMembersOnly : $this->allowBackendLoginToPredefinedSectionMembersOnly;
        // In the frontend a disabled account is reactivated on login,
        // in the backend it is only let through.
        // Reactivating implies letting through, hence the one flag.
        $blnReactivateDisabledFrontendUser = ContaoCoreBundle::SCOPE_FRONTEND === $contaoScope && $this->reactivateDisabledFrontendUserOnLogin;
        $blnAllowLoginIfAccountIsDisabled = ContaoCoreBundle::SCOPE_FRONTEND === $contaoScope ? $this->reactivateDisabledFrontendUserOnLogin : $this->allowBackendLoginIfContaoAccountIsDisabled;

        try {
            $oAuth2Client = $this->oAuth2ClientFactory->createOAuth2Client($request);

            if (!$oAuth2Client->hasValidOAuth2State()) {
                $this->throwWithMessage(
                    $request,
                    ErrorMessage::LEVEL_ERROR,
                    LoginFailureReason::InvalidState,
                    null,
                );
            }

            $oAuth2Provider = $oAuth2Client->getOAuth2Provider();

            // Send the PKCE code verifier along with the token request.
            $oAuth2Client->restorePkceCode();

            // Try to get an access token using the authorization code grant.
            $accessToken = $oAuth2Provider->getAccessToken('authorization_code', [
                'code' => $request->query->get('code'),
            ]);

            // The league provider always returns a concrete AccessToken here.
            \assert($accessToken instanceof AccessToken);

            // Get the resource owner. This is an HTTP request to the userinfo endpoint,
            // so we fetch it once and reuse the result.
            $oAuthUser = $oAuth2Provider->getResourceOwner($accessToken);

            if (!$oAuthUser instanceof OAuthUser) {
                $this->throwWithMessage($request, ErrorMessage::LEVEL_ERROR, LoginFailureReason::Unexpected);
            }

            if ($this->isDebugMode) {
                // Store OAuth claims to Contao system log.
                $logText = \sprintf(
                    'SAC oauth2 debug %s login. NAME: %s - SAC MEMBER ID: %s - ROLES: %s - DATA ALL: %s',
                    $contaoScope,
                    $oAuthUser->getFullName(),
                    $oAuthUser->getSacMemberId(),
                    $oAuthUser->getRolesAsString(),
                    json_encode($oAuthUser->toArray(), JSON_UNESCAPED_SLASHES), // Do not escape slashes in links: https://portal.sac-cas.ch/verify_membership/kfDSFsdf...
                );

                $this->contaoAccessLogger?->info(
                    $logText,
                    ['contao' => new ContaoContext(__METHOD__, ContaoLogConfig::SAC_OAUTH2_DEBUG_LOG)],
                );
            }

            // Check if we can find a sac member id in resource owner claims.
            if (!$this->oauthUserChecker->checkHasSacMemberId($oAuthUser)) {
                $this->throwWithMessage(
                    $request,
                    ErrorMessage::LEVEL_WARNING,
                    LoginFailureReason::ResourceOwnerHasInvalidSacMemberId,
                    $oAuthUser,
                );
            }

            // Capture Login Attempt Check if we can find an email address
            // in the resource owner claims.
            if (!$this->oauthUserChecker->checkHasValidEmailAddress($oAuthUser)) {
                $this->throwWithMessage(
                    $request,
                    ErrorMessage::LEVEL_WARNING,
                    LoginFailureReason::ResourceOwnerHasInvalidEmail,
                    $oAuthUser,
                    [$oAuthUser->getFirstName()],
                );
            }

            // Check if the resource owner is a member of the Swiss Alpine Club (SAC).
            if ($blnAllowLoginToSacMembersOnly) {
                if (!$this->oauthUserChecker->checkIsSacMember($oAuthUser, $contaoScope)) {
                    $this->throwWithMessage(
                        $request,
                        ErrorMessage::LEVEL_WARNING,
                        LoginFailureReason::MissingSacMembership,
                        $oAuthUser,
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
                        LoginFailureReason::NotMemberOfAllowedSection,
                        $oAuthUser,
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
                        LoginFailureReason::ContaoFrontendUserNotFound,
                        $oAuthUser,
                        [$oAuthUser->getFirstName()],
                    );
                }
            } else {
                if (!$contaoUser->checkBackendUserExists()) {
                    $this->throwWithMessage(
                        $request,
                        ErrorMessage::LEVEL_WARNING,
                        LoginFailureReason::ContaoBackendUserNotFound,
                        $oAuthUser,
                        [$oAuthUser->getFirstName()],
                    );
                }
            }

            // Permanently reactivate the member account, see the configuration reference.
            if ($blnReactivateDisabledFrontendUser) {
                // Set tl_member.disable = false
                $contaoUser->activateMemberAccount();
            }

            // Check if tl_member.login is set to true
            if ($this->scopeMatcher->isFrontendRequest($request)) {
                if (!$contaoUser->checkFrontendLoginIsEnabled()) {
                    $this->throwWithMessage(
                        $request,
                        ErrorMessage::LEVEL_WARNING,
                        LoginFailureReason::ContaoFrontendUserLoginNotEnabled,
                        $oAuthUser,
                        [$oAuthUser->getFirstName()],
                    );
                }
            }

            // If Contao scope is 'backend': Check if tl_user.disable === false or
            // tl_user.start and tl_user.stop are not in an allowed time range If Contao
            // scope is 'frontend': Check if tl_member.disable === false or tl_member.start
            // and tl_member.stop are not in an allowed time range
            if (!$contaoUser->checkAccountIsNotDisabled() && !$blnAllowLoginIfAccountIsDisabled) {
                $this->throwWithMessage(
                    $request,
                    ErrorMessage::LEVEL_WARNING,
                    LoginFailureReason::ContaoUserDisabled,
                    $oAuthUser,
                    [$oAuthUser->getFirstName()],
                );
            }

            // Update tl_member and tl_user.
            $contaoUser->updateFrontendUser();
            $contaoUser->updateBackendUser();

            return new SelfValidatingPassport(new UserBadge($contaoUser->getIdentifier()));
        } catch (AuthenticationException $e) {
            // A login policy has rejected the resource owner. The event has been dispatched,
            // the flash message is set and the attempt has been logged by
            // throwWithMessage(), so we must not swallow the reason here.
            throw $e;
        } catch (IdentityProviderException $e) {
            // The identity provider refused the token or the userinfo request.
            $this->logUnexpectedError($request, 'the identity provider returned an error', $e);

            $this->throwWithMessage(
                $request,
                ErrorMessage::LEVEL_ERROR,
                LoginFailureReason::Unexpected,
                $oAuthUser ?? null,
                previous: $e,
            );
        } catch (\Throwable $e) {
            // Anything else is a bug or an infrastructure failure. The user gets a friendly
            // message, but the log has to keep the stack trace.
            $this->logUnexpectedError($request, 'an unexpected error occurred', $e);

            $this->throwWithMessage(
                $request,
                ErrorMessage::LEVEL_ERROR,
                LoginFailureReason::Unexpected,
                $oAuthUser ?? null,
                previous: $e,
            );
        }
    }

    /**
     * Decide whether Contao asks for a second factor after an SSO login.
     *
     * The identity provider is the strong factor here. Whether Contao adds its own
     * second factor on top is a per scope decision, hence the two switches. Both are
     * off by default, which keeps the behaviour this authenticator has always had.
     */
    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        $token = parent::createToken($passport, $firewallName);

        $token->setAttribute('AUTHENTICATOR', self::NAME);

        $user = $token->getUser();

        if (!$user instanceof User || !$user->useTwoFactor) {
            return $token;
        }

        $enforceTwoFactor = $user instanceof BackendUser
            ? $this->enforceBackendTwoFactor
            : $this->enforceFrontendTwoFactor;

        // An account can carry useTwoFactor without ever having been enrolled: the flag
        // is editable in the back end, the TOTP secret is not. Contao does not guard
        // against that combination - Authenticator::getUpperUnpaddedSecretForUser()
        // hands a null secret to Base32 and the request dies with a TypeError. There is
        // nothing to verify against here, so let the login through and make the
        // inconsistency visible instead of locking the account out of a portal it
        // cannot reach to fix it.
        if ($enforceTwoFactor && empty($user->secret)) {
            $this->contaoAccessLogger?->warning(\sprintf(
                'User "%s" has two factor authentication enabled, but no TOTP secret. The second factor was skipped.',
                $user->getUserIdentifier(),
            ));

            $enforceTwoFactor = false;
        }

        if ($enforceTwoFactor) {
            // Leaving the flag unset hands the flow to the two factor bundle: its
            // AuthenticationTokenListener swaps the token for a TwoFactorToken, and
            // Contao's authentication success handler redirects to the second factor.
            return $token;
        }

        // Tell the two factor bundle that the second factor has already been provided.
        $token->setAttribute(TwoFactorAuthenticator::FLAG_2FA_COMPLETE, true);

        return $token;
    }

    /**
     * @throws Exception
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): Response|null
    {
        $oAuth2Client = $this->oAuth2ClientFactory->createOAuth2Client($request);

        // The target path has already been validated in the StartController, but we
        // check it again, because Contao's authentication success handler redirects to
        // it without any further validation.
        $targetPath = $this->redirectPathValidator->getSafePath($oAuth2Client->getTargetPath(), $request);

        if (null === $targetPath) {
            $targetPath = $this->scopeMatcher->isBackendRequest($request)
                ? $this->router->generate('contao_backend', [], UrlGeneratorInterface::ABSOLUTE_URL)
                : $request->getSchemeAndHttpHost();
        }

        $request->request->set('_target_path', base64_encode($targetPath));
        $request->request->set('_always_use_target_path', $oAuth2Client->getAlwaysUseTargetPath());

        // Clear the session
        $this->getSessionBag($request)->clear();

        // The flash bag should actually be empty. Let's clear it anyway just to be on
        // the safe side.
        $this->errorMessageManager->clearFlash();

        // Reset login attempts
        $user = $token->getUser();

        if ($user instanceof User) {
            // @phpstan-ignore property.notFound (ssoLoginAttempts is added to tl_member and tl_user by this bundle)
            $user->ssoLoginAttempts = 0;
            $user->save();
        }

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

        if ($token instanceof TwoFactorTokenInterface) {
            // The first factor is done, the second one is not. Contao's success handler
            // would redirect back to the callback url, whose authorization code the
            // identity provider has already consumed - the second run fails with
            // "invalid state". Send the user to the target path instead. The request
            // carries no authorization code, so this authenticator stays out of it and
            // the two factor access listener asks for the code.
            // Keep where the member actually wanted to go. The frontend module puts it
            // into the "_target_path" field of the second factor form, so Contao goes
            // there once the code has been accepted.
            $request->getSession()->set(self::SESSION_KEY_TWO_FACTOR_TARGET_PATH, $targetPath);

            $parkingUrl = $this->scopeMatcher->isFrontendRequest($request)
                ? $this->getTwoFactorParkingUrl($request)
                : null;

            $parkingUrl ??= $targetPath;

            $this->saveTargetPath($request->getSession(), $firewallName, $parkingUrl);

            $this->contaoAccessLogger?->info(\sprintf(
                '%s User "%s" [%s] has passed the SAC OPENID CONNECT APP login. Waiting for the second factor.',
                strtoupper($contaoScope),
                $fullName,
                $userIdentifier,
            ));

            return new RedirectResponse($parkingUrl);
        }

        // Contao system log
        $logSuccess = \sprintf(
            '%s User "%s" [%s] has logged in with SAC OPENID CONNECT APP.',
            strtoupper($contaoScope),
            $fullName,
            $userIdentifier,
        );

        $this->contaoAccessLogger?->info($logSuccess);

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

        // Get the failure path. Even though the path has already been validated in the
        // StartController, we check it again because an unvalidated redirect target
        // would turn the login flow into an open redirect.
        $oAuth2Client = $this->oAuth2ClientFactory->createOAuth2Client($request);
        $failurePath = $this->redirectPathValidator->getSafePath($oAuth2Client->getFailurePath(), $request);

        // Let's play it safe and make sure we always have a valid redirect URL.
        if (null === $failurePath) {
            if ($isFrontend) {
                $failurePath = $request->getSchemeAndHttpHost();
                $failurePath = $this->urlParser->addQueryString('sac-oidc-error=true', $failurePath);
            } else {
                $failurePath = $this->router->generate('contao_backend', [], UrlGeneratorInterface::ABSOLUTE_URL);
            }
        }

        return new RedirectResponse($failurePath);
    }

    protected function getSessionBag(Request $request): AttributeBagInterface
    {
        $name = $this->scopeMatcher->isBackendRequest($request)
            ? 'sac_oauth2_client_attr_backend'
            : 'sac_oauth2_client_attr_frontend';

        $bag = $request->getSession()->getBag($name);

        if (!$bag instanceof AttributeBagInterface) {
            throw new \LogicException(\sprintf('Expected the session bag "%s" to be an instance of "%s", got "%s".', $name, AttributeBagInterface::class, get_debug_type($bag)));
        }

        return $bag;
    }

    protected function throwWithMessage(Request $request, string $errLevel, LoginFailureReason $reason, ResourceOwnerInterface|null $resourceOwner = null, array $argsA = [], array $argsB = [], array $argsC = [], \Throwable|null $previous = null): never
    {
        // Dispatch the AuthenticationFailureEvent.
        // We use a listener to increment tl_user.ssoLoginAttempts & tl_member.ssoLoginAttempts
        $event = new AuthenticationFailureEvent($request, $errLevel, $reason, $resourceOwner, [$argsA, $argsB, $argsC]);
        $this->eventDispatcher->dispatch($event);

        $this->errorMessageManager->add2Flash(
            new ErrorMessage(
                $errLevel,
                $this->translator->trans($reason->getMatterTranslationKey(), $argsA, 'contao_default'),
                $this->translator->trans($reason->getHowToFixTranslationKey(), $argsB, 'contao_default'),
                $this->translator->trans($reason->getExplainTranslationKey(), $argsC, 'contao_default'),
            ),
        );

        if (null !== $this->contaoAccessLogger && null !== $resourceOwner) {
            $oAuthUser = new OAuthUser($resourceOwner->toArray(), Hitobito::ACCESS_TOKEN_RESOURCE_OWNER_ID);

            // Who failed to log in and why. That is what the system log is for.
            $logText = \sprintf(
                'SAC %s Login has failed for: %s - SAC MEMBER ID: %s - REASON: %s',
                $this->scopeMatcher->isFrontendRequest($request) ? 'Frontend' : 'Backend',
                $oAuthUser->getFullName(),
                $oAuthUser->getSacMemberId(),
                $reason->value,
            );

            // The claims contain personal data (address, date of birth, phone number),
            // which every backend user with access to the system log would get to see.
            // They are only of interest while debugging, so that is where they stay.
            if ($this->isDebugMode) {
                $logText .= \sprintf(
                    ' - EMAIL: %s - ROLES: %s - DATA ALL: %s',
                    $oAuthUser->getEmail(),
                    $oAuthUser->getRolesAsString(),
                    json_encode($oAuthUser->toArray(), JSON_UNESCAPED_SLASHES), // Do not escape slashes in links: https://portal.sac-cas.ch/verify_membership/kfDSFsdf...
                );
            }

            $this->contaoAccessLogger->info($logText, [
                'contao' => new ContaoContext(__METHOD__, $this->getLoginFailLogCategory($request)),
            ]);
        }

        throw new SacLoginAuthenticationException($reason, $previous);
    }

    /**
     * Log a technical failure with its stack trace, so it does not hide behind the
     * friendly "unexpected error" message the user gets to see.
     */
    /**
     * Find a page the member can wait on while the second factor is pending.
     *
     * Contao's TwoFactorFrontendListener redirects the member back to the firewall's
     * target path on every single request until the code has been entered. If that path
     * points at a page which rewrites its own query string - a checkout stepping from
     * "?action=login" to "?action=register", for instance - the two redirect at each
     * other forever: the page moves the member on, the listener pulls them back.
     *
     * So the member is parked on a page that stays put. The login module sits in the
     * page header, hence any regular page will render the form. The root page's
     * "twoFactorJumpTo" wins if it is set, otherwise the root page itself is used.
     */
    private function getTwoFactorParkingUrl(Request $request): string|null
    {
        $rootPage = $this->pageFinder->findRootPageForRequest($request);

        if (!$rootPage instanceof PageModel) {
            return null;
        }

        $pageModelAdapter = $this->framework->getAdapter(PageModel::class);

        $parkingPage = $rootPage->twoFactorJumpTo
            ? $pageModelAdapter->findPublishedById($rootPage->twoFactorJumpTo)
            : null;

        try {
            return $this->contentUrlGenerator->generate(
                $parkingPage instanceof PageModel ? $parkingPage : $rootPage,
                [],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );
        } catch (\Throwable $e) {
            $this->logUnexpectedError($request, 'could not generate the two factor parking url', $e);

            return null;
        }
    }

    private function logUnexpectedError(Request $request, string $what, \Throwable $e): void
    {
        $this->contaoAccessLogger?->error(
            \sprintf(
                'SAC oauth2 %s login failed, %s: %s',
                $this->scopeMatcher->isFrontendRequest($request) ? 'frontend' : 'backend',
                $what,
                $e->getMessage(),
            ),
            [
                'contao' => new ContaoContext(__METHOD__, $this->getLoginFailLogCategory($request)),
                'exception' => $e,
            ],
        );
    }

    private function getLoginFailLogCategory(Request $request): string
    {
        return $this->scopeMatcher->isFrontendRequest($request)
            ? ContaoLogConfig::SAC_OAUTH2_FRONTEND_LOGIN_FAIL
            : ContaoLogConfig::SAC_OAUTH2_BACKEND_LOGIN_FAIL;
    }
}
