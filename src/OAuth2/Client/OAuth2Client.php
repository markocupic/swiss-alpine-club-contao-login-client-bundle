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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\OAuth2\Client;

use Contao\CoreBundle\ContaoCoreBundle;
use League\OAuth2\Client\Provider\AbstractProvider;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\OAuth2\Client\Provider\ProviderFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionBagInterface;

class OAuth2Client
{
    public const string OAUTH2_SESSION_STATE_KEY = 'oauth2state';

    public const string OAUTH2_SESSION_PKCE_CODE_KEY = 'oauth2pkceCode';

    private AbstractProvider|null $oAuthProvider = null;

    public function __construct(
        private readonly ProviderFactory $providerFactory,
        private readonly Request $request,
    ) {
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

    /**
     * Store the PKCE code verifier, which has been generated while building the
     * authorization url. The provider is recreated on every request, therefore the
     * verifier has to survive the redirect to the authorization server.
     */
    public function storePkceCode(): void
    {
        $this->getSession()->set(
            self::OAUTH2_SESSION_PKCE_CODE_KEY,
            $this->getOAuth2Provider()->getPkceCode(),
        );
    }

    /**
     * Restore the PKCE code verifier, so it can be sent to the token endpoint.
     */
    public function restorePkceCode(): void
    {
        $pkceCode = $this->getSession()->get(self::OAUTH2_SESSION_PKCE_CODE_KEY);

        if (\is_string($pkceCode) && '' !== $pkceCode) {
            $this->getOAuth2Provider()->setPkceCode($pkceCode);
        }
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
