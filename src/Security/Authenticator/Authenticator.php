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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\Authenticator;

use Codefog\HasteBundle\UrlParser;
use Contao\CoreBundle\ContaoCoreBundle;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\CoreBundle\Security\Authentication\AuthenticationSuccessHandler;
use Contao\MemberModel;
use Contao\System;
use Contao\UserModel;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Config\ContaoLogConfig;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Controller\SacLoginRedirectController;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\ErrorMessage\ErrorMessage;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\ErrorMessage\ErrorMessageManager;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\OAuth2\Client\OAuth2Client;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\OAuth2\Client\OAuth2ClientFactory;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\Authenticator\Exception\ContaoUserDisabledAuthenticationException;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\Authenticator\Exception\ContaoUserNotFoundAuthenticationException;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\Authenticator\Exception\InvalidStateAuthenticationException;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\Authenticator\Exception\MissingAuthCodeAuthenticationException;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\Authenticator\Exception\NotMemberOfAllowedSectionAuthenticationException;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\Authenticator\Exception\NotSacMemberAuthenticationException;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\Authenticator\Exception\UuidNotFoundAuthenticationException;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\OAuth\OAuthUserChecker;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\User\ContaoUserFactory;
use Psr\Log\LoggerInterface;
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

class Authenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly AuthenticationSuccessHandler $authenticationSuccessHandler,
        private readonly Connection $connection,
        private readonly ContaoFramework $framework,
        private readonly ContaoUserFactory $contaoUserFactory,
        private readonly ErrorMessageManager $errorMessageManager,
        private readonly OAuth2ClientFactory $oAuth2ClientFactory,
        private readonly OAuthUserChecker $oAuthUserChecker,
        private readonly RouterInterface $router,
        private readonly ScopeMatcher $scopeMatcher,
        private readonly TranslatorInterface $translator,
        private readonly UrlParser $urlParser,
        private readonly LoggerInterface|null $contaoAccessLogger = null,
    ) {
    }

    public function supports(Request $request): bool
    {
        if (!$request->attributes->has('_scope')) {
            return false;
        }

        return match ($request->attributes->get('_route')) {
            SacLoginRedirectController::ROUTE_BACKEND => true,
            SacLoginRedirectController::ROUTE_FRONTEND => true,
            default => false,
        };
    }

    /**
     * @param AuthenticationException|null $authException
     */
    public function start(Request $request, AuthenticationException|null $authException = null): RedirectResponse
    {
        $oAuth2Client = $this->oAuth2ClientFactory->createOAuth2Client($request);

        // Fetch the authorization URL from the provider;
        // this returns the urlAuthorize option and generates and applies any necessary parameters
        // (e.g. state).
        $authorizationUrl = $oAuth2Client->getOAuth2Provider()->getAuthorizationUrl();

        $sessionBag = $this->getSessionBag($request);
        $sessionBag->set('oauth2state', $oAuth2Client->getOAuth2Provider()->getState());

        return new RedirectResponse($authorizationUrl);
    }

    public function authenticate(Request $request): Passport
    {
        $this->framework->initialize();

        $contaoScope = $request->attributes->get('_scope');

        $system = $this->framework->getAdapter(System::class);

        $container = $system->getContainer();

        /** @var bool $isDebugMode */
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
            /** @var OAuth2Client $client */
            $oAuth2Client = $this->oAuth2ClientFactory->createOAuth2Client($request);

            if (empty($request->query->get('code'))) {
                throw new MissingAuthCodeAuthenticationException(MissingAuthCodeAuthenticationException::MESSAGE);
            }

            if (!$oAuth2Client->hasValidOAuth2State()) {
                throw new InvalidStateAuthenticationException(InvalidStateAuthenticationException::MESSAGE);
            }

            $oAuth2Provider = $oAuth2Client->getOAuth2Provider();

            // Try to get an access token using the authorization code grant.
            $accessToken = $oAuth2Provider->getAccessToken('authorization_code', [
                'code' => $request->query->get('code'),
            ]);

            // Get the resource owner object.
            $resourceOwner = $oAuth2Client->fetchUserFromToken($accessToken);

            $oAuthUser = $oAuth2Provider->getResourceOwner($accessToken);

            if ($isDebugMode) {
                // Add OAuth claims to Contao system log
                $logText = sprintf(
                    'SAC oauth2 debug %s login. NAME: %s - SAC MEMBER ID: %s - ROLES: %s - DATA ALL: %s',
                    $contaoScope,
                    $resourceOwner->getFullName(),
                    $resourceOwner->getSacMemberId(),
                    $resourceOwner->getRolesAsString(),
                    json_encode($resourceOwner->toArray()),
                );

	            $this->contaoAccessLogger->info(
		            $logText,
		            ['contao' => new ContaoContext(__METHOD__, ContaoLogConfig::SAC_OAUTH2_DEBUG_LOG)],
	            );
            }

            // Check if uuid/sub is set
            if (!$this->oAuthUserChecker->checkHasUuid($oAuthUser)) {
                throw new UuidNotFoundAuthenticationException(UuidNotFoundAuthenticationException::MESSAGE);
            }

            // Check if user is a SAC member
            if ($blnAllowLoginToSacMembersOnly) {
                if (!$this->oAuthUserChecker->checkIsSacMember($oAuthUser)) {
                    throw new NotSacMemberAuthenticationException(NotSacMemberAuthenticationException::MESSAGE);
                }
            }

            // Check if user is member of an allowed section
            if ($blnAllowLoginToPredefinedSectionsOnly) {
                if (!$this->oAuthUserChecker->checkIsMemberOfAllowedSection($oAuthUser, $contaoScope)) {
                    throw new NotMemberOfAllowedSectionAuthenticationException(NotMemberOfAllowedSectionAuthenticationException::MESSAGE);
                }
            }

            $this->errorMessageManager->add2Flash(
                new ErrorMessage(
                    ErrorMessage::LEVEL_WARNING,
                    $this->translator->trans('ERR.sacOidcLoginError_invalidEmail_matter', [$oAuthUser->getFirstName()], 'contao_default'),
                    $this->translator->trans('ERR.sacOidcLoginError_invalidEmail_howToFix', [], 'contao_default'),
                    $this->translator->trans('ERR.sacOidcLoginError_invalidEmail_explain', [], 'contao_default'),
                )
            );

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
                throw new ContaoUserNotFoundAuthenticationException(ContaoUserNotFoundAuthenticationException::MESSAGE);
            }

            // Allow login to frontend users only if account is not disabled
            if (ContaoCoreBundle::SCOPE_FRONTEND === $contaoScope) {
                // Set tl_member.disable = ''
                $contaoUser->enableLogin();
            }

            // Set tl_user.loginAttempts = 0
            $contaoUser->resetLoginAttempts();

            // Update tl_member and tl_user
            $contaoUser->updateFrontendUser();
            $contaoUser->updateBackendUser();

            // if $contaoScope === 'backend': Check if tl_user.disable === '' or tl_user.login === '1' or tl_user.start and tl_user.stop are not in an allowed time range
            // if $contaoScope === 'frontend': Check if tl_member.disable === '' or tl_member.login === '1' or tl_member.start and tl_member.stop are not in an allowed time range
            if (!$contaoUser->checkIsAccountEnabled() && !$blnAllowContaoLoginIfAccountIsDisabled) {
                throw new ContaoUserDisabledAuthenticationException(ContaoUserDisabledAuthenticationException::MESSAGE);
            }
        } catch (IdentityProviderException|AuthenticationException $e) {
            throw new AuthenticationException($e->getMessage());
        } catch (\Exception $e) {
            $this->errorMessageManager->add2Flash(
                new ErrorMessage(
                    ErrorMessage::LEVEL_WARNING,
                    $this->translator->trans('ERR.sacOidcLoginError_unexpectedError_matter', [], 'contao_default'),
                    $this->translator->trans('ERR.sacOidcLoginError_unexpectedError_howToFix', [], 'contao_default'),
                    $this->translator->trans('ERR.sacOidcLoginError_unexpectedError_explain', [], 'contao_default'),
                )
            );

            throw new AuthenticationException($e->getMessage());
        }

        $strTable = ContaoCoreBundle::SCOPE_BACKEND === $contaoScope ? UserModel::getTable() : MemberModel::getTable();

        return new SelfValidatingPassport(new UserBadge($contaoUser->getModel($strTable)->username));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $firewallName): Response|null
    {
        $oAuth2Client = $this->oAuth2ClientFactory->createOAuth2Client($request);
        $request->request->set('_target_path', $oAuth2Client->getTargetPath());
        $request->request->set('_always_use_target_path', $oAuth2Client->getTargetPath());
        $request->request->set('_failure_path', $oAuth2Client->getFailurePath());

        // Clear the session
        $this->getSessionBag($request)->clear();

        // The flash bag should actually be empty.
        // Let's clear it anyway be on the safe side.
        $this->errorMessageManager->clearFlash();

        if ($this->scopeMatcher->isFrontendRequest($request)) {
            $contaoScope = ContaoCoreBundle::SCOPE_FRONTEND;
            $queryName = 'SELECT CONCAT(firstname, " ", lastname) FROM tl_member WHERE username = :username';
            $querySacMemberId = 'SELECT sacMemberId FROM tl_member WHERE username = :username';
        } else {
            $contaoScope = ContaoCoreBundle::SCOPE_BACKEND;
            $queryName = 'SELECT name FROM tl_user WHERE username = :username';
            $querySacMemberId = 'SELECT sacMemberId FROM tl_user WHERE username = :username';
        }

        $userIdentifier = $token->getUser()->getUserIdentifier();

        $fullName = $this->connection->fetchOne($queryName, ['username' => $userIdentifier], ['username' => Types::STRING]);
        $sacMemberId = $this->connection->fetchOne($querySacMemberId, ['username' => $userIdentifier], ['username' => Types::STRING]);

        // Contao system log
        $logSuccess = sprintf(
            '%s User "%s" [%s] has logged in with SAC OPENID CONNECT APP.',
            strtoupper($contaoScope),
            $fullName,
            $sacMemberId
        );

        $this->contaoAccessLogger->info($logSuccess);

        // Trigger the on authentication success handler from the Contao Core.
        return $this->authenticationSuccessHandler->onAuthenticationSuccess($request, $token);
    }

    /**
     * Do not use Contao Core's onAuthenticationFailure handler,
     * because this leads to an infinite redirection loop.
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response|null
    {
        $isFrontend = $this->scopeMatcher->isFrontendRequest($request);

        // Add a new entry to the Contao system log.
        $this->contaoAccessLogger->info(
            $exception->getMessage(),
            ['contao' => new ContaoContext(__METHOD__, $isFrontend ? ContaoLogConfig::SAC_OAUTH2_FRONTEND_LOGIN_FAIL : ContaoLogConfig::SAC_OAUTH2_BACKEND_LOGIN_FAIL)],
        );

        $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);

        // Get the failure path
        $oAuth2Client = $this->oAuth2ClientFactory->createOAuth2Client($request);
        $failurePath = $oAuth2Client->getFailurePath();

        // Let's play it safe and make sure we always have a redirect URL.
        if (!$failurePath) {
            if ($isFrontend) {
                $failurePath = $request->getSchemeAndHttpHost();
                $failurePath = $this->urlParser->addQueryString('sso_error=true', $failurePath);
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
}
