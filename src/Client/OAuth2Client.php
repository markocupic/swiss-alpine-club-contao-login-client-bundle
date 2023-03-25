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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Client;

use Contao\CoreBundle\ContaoCoreBundle;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Client\Exception\InvalidStateException;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Client\Exception\MissingAuthorizationCodeException;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Client\Provider\ProviderFactory;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionBagInterface;

class OAuth2Client
{
    public const OAUTH2_SESSION_STATE_KEY = 'oauth2state';
    private AbstractProvider|null $oAuthProvider = null;

    public function __construct(
        private readonly ProviderFactory $providerFactory,
        private readonly RequestStack $requestStack,
        private readonly string $contaoScope,
    ) {
    }

    /**
     * Creates a RedirectResponse that will send the user to the
     * OAuth2 server at https://login-dev.sac-cas.ch/.
     *
     * @param array $scopes  The scopes you want (leave empty to use default)
     * @param array $options Extra options to pass to the Provider's getAuthorizationUrl()
     *                       method. For example, <code>scope</code> is a common option.
     *                       Generally, these become query parameters when redirecting.
     */
    public function redirect(array $scopes = [], array $options = []): RedirectResponse
    {
        if (!empty($scopes)) {
            $options['scope'] = $scopes;
        }

        $url = $this->getOAuth2Provider()->getAuthorizationUrl($options);

        $this->getSession()->set(
            self::OAUTH2_SESSION_STATE_KEY,
            $this->getOAuth2Provider()->getState()
        );

        return new RedirectResponse($url);
    }

    /**
     * Call this after the user is redirected back to get the access token.
     * Add additional options ($options) that should be passed to the getAccessToken() of the underlying provider.
     *
     * @throws InvalidStateException
     * @throws MissingAuthorizationCodeException
     * @throws IdentityProviderException
     */
    public function getAccessToken(array $options = []): AccessToken|AccessTokenInterface
    {
        $expectedState = $this->getSession()->get(self::OAUTH2_SESSION_STATE_KEY);
        $actualState = $this->getCurrentRequest()->get('state');

        if (!$actualState || ($actualState !== $expectedState)) {
            throw new InvalidStateException('Invalid state');
        }

        $code = $this->getCurrentRequest()->get('code');

        if (!$code) {
            throw new MissingAuthorizationCodeException('No "code" parameter was found (usually this is a query parameter)!');
        }

        return $this->getOAuth2Provider()->getAccessToken(
            'authorization_code',
            array_merge(['code' => $code], $options)
        );
    }

    /**
     * Get a new AccessToken from a refresh token.
     *
     * @param array $options Additional options that should be passed to the getAccessToken() of the underlying provider
     *
     * @throws IdentityProviderException If token cannot be fetched
     */
    public function refreshAccessToken(string $refreshToken, array $options = []): AccessToken|AccessTokenInterface
    {
        return $this->getOAuth2Provider()->getAccessToken(
            'refresh_token',
            array_merge(['refresh_token' => $refreshToken], $options)
        );
    }

    /**
     * Returns the "User" information (called a resource owner).
     */
    public function fetchUserFromToken(AccessToken $accessToken): ResourceOwnerInterface
    {
        return $this->getOAuth2Provider()->getResourceOwner($accessToken);
    }

    /**
     * Shortcut to fetch the access token and user all at once.
     *
     * Only use this if you don't need the access token, but only
     * need the user.
     */
    public function fetchUser(): ResourceOwnerInterface
    {
        /** @var AccessToken $token */
        $token = $this->getAccessToken();

        return $this->fetchUserFromToken($token);
    }

    /**
     * Returns the underlying OAuth2 provider.
     */
    public function getOAuth2Provider(): AbstractProvider
    {
        if (null !== $this->oAuthProvider) {
            return $this->oAuthProvider;
        }

        $this->oAuthProvider = $this->providerFactory->createProvider($this->contaoScope);

        return $this->oAuthProvider;
    }

    public function getContaoScope(): string|null
    {
        return $this->contaoScope;
    }

    public function getTargetPath(): string
    {
        return $this->getSession()->get('_target_path', null);
    }

    public function getFailurePath(): string
    {
        return $this->getSession()->get('_failure_path', null);
    }

    public function getModuleId(): string
    {
        return $this->getSession()->get('_module_id', null);
    }

    public function setTargetPath(string $targetPath): void
    {
        $this->getSession()->set('_target_path', $targetPath);
    }

    public function setFailurePath(string $failurePath): void
    {
        $this->getSession()->set('_failure_path', $failurePath);
    }

    public function setModuleId(string $moduleId): void
    {
        $this->getSession()->set('_module_id', $moduleId);
    }

    public function getSession(): SessionBagInterface
    {
        if (ContaoCoreBundle::SCOPE_BACKEND === $this->contaoScope) {
            $session = $this->getCurrentRequest()->getSession()->getBag('sac_oauth2_client_attr_backend');
        } else {
            $session = $this->getCurrentRequest()->getSession()->getBag('sac_oauth2_client_attr_frontend');
        }

        return $session;
    }

    private function getCurrentRequest(): Request
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request) {
            throw new \LogicException('There is no "current request", and it is needed to perform this action');
        }

        return $request;
    }
}
