<?php

declare(strict_types=1);

/*
 * This file is part of Swiss Alpine Club Contao Login Client Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/swiss-alpine-club-contao-login-client-bundle
 */

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\OAuth2\Client;

use Contao\CoreBundle\ContaoCoreBundle;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\OAuth2\Client\Provider\ProviderFactory;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\Authenticator\Exception\InvalidStateAuthenticationException;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\Authenticator\Exception\MissingAuthCodeAuthenticationException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionBagInterface;

class OAuth2Client
{
    public const OAUTH2_SESSION_STATE_KEY = 'oauth2state';
    private AbstractProvider|null $oAuthProvider = null;

    public function __construct(
        private readonly ProviderFactory $providerFactory,
        private readonly Request $request,
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
     *
     * @throws \Exception
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
     * @throws IdentityProviderException
     */
    public function getAccessToken(array $options = []): AccessToken|AccessTokenInterface
    {
        $expectedState = $this->getSession()->get(self::OAUTH2_SESSION_STATE_KEY);
        $actualState = $this->request->get('state');

        if (!$actualState || ($actualState !== $expectedState)) {
            throw new InvalidStateAuthenticationException(InvalidStateAuthenticationException::MESSAGE);
        }

        $code = $this->request->get('code');

        if (!$code) {
            throw new MissingAuthCodeAuthenticationException(MissingAuthCodeAuthenticationException::MESSAGE);
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
     *
     * @throws IdentityProviderException
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

        $this->oAuthProvider = $this->providerFactory->createProvider();

        return $this->oAuthProvider;
    }

    public function hasValidOAuth2State(): bool
    {
        if (empty($this->request->query->get('state'))) {
            return false;
        }

        $bag = $this->getSession();

        if (empty($bag->get('oauth2state'))) {
            return false;
        }

        if ($this->request->query->get('state') !== $bag->get('oauth2state')) {
            return false;
        }

        return true;
    }

    public function getAlwaysUseTargetPath(): string
    {
        return $this->getSession()->get('_always_use_target_path', '0') ? '1' : '0';
    }

    public function getTargetPath(): string
    {
        return $this->getSession()->get('_target_path', '');
    }

    public function getFailurePath(): string
    {
        return $this->getSession()->get('_failure_path', '');
    }

    public function getModuleId(): string|null
    {
        return $this->getSession()->get('_module_id', null);
    }

    public function setAlwaysUseTargetPath(bool $blnAlwaysUseTargetPath): void
    {
        $this->getSession()->set('_always_use_target_path', (string) $blnAlwaysUseTargetPath);
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
        $session = $this->request->getSession();

        $bag = match ($this->request->attributes->get('_scope')) {
            ContaoCoreBundle::SCOPE_BACKEND => $session->getBag('sac_oauth2_client_attr_backend'),
            ContaoCoreBundle::SCOPE_FRONTEND => $session->getBag('sac_oauth2_client_attr_frontend'),
            default => null,
        };

        if (null === $bag) {
            throw new \Exception('Scope must be "backend" or "frontend".');
        }

        return $bag;
    }
}
