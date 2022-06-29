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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Oidc;

use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\System;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Exception\BadQueryStringException;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Provider\SwissAlpineClub;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Provider\SwissAlpineClubResourceOwner;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;

class Oidc
{
    private ContaoFramework $framework;
    private RequestStack $requestStack;
    private Adapter $system;
    private SwissAlpineClub|null $provider = null;
    private SwissAlpineClubResourceOwner|null $resourceOwner = null;

    public function __construct(ContaoFramework $framework, RequestStack $requestStack)
    {
        $this->framework = $framework;
        $this->requestStack = $requestStack;

        // Adapters
        $this->system = $this->framework->getAdapter(System::class);
    }

    public function createProvider(array $arrData = []): void
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

        $this->provider = new SwissAlpineClub($arrProviderConfig, []);
    }

    public function getProvider(): SwissAlpineClub|null
    {
        return $this->provider;
    }

    public function hasAuthCode(): bool
    {
        $request = $this->requestStack->getCurrentRequest();

        return $request->query->has('code');
    }

    public function getAuthCode(): RedirectResponse
    {
        /** @var string $bagName */
        $bagName = $this->system->getContainer()->getParameter('sac_oauth2_client.session.attribute_bag_name');

        /** @var Session $session */
        $session = $this->requestStack->getCurrentRequest()->getSession()->getBag($bagName);

        // Fetch the authorization URL from the provider;
        // this returns the urlAuthorize option and generates and applies any necessary parameters
        // (e.g. state).
        $authorizationUrl = $this->provider->getAuthorizationUrl();

        // Get the state and store it to the session.
        $session->set('oauth2state', $this->provider->getState());

        // Redirect the user to the authorization URL.
        return new RedirectResponse($authorizationUrl);
    }

    public function getAccessToken(): AccessToken
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
            $accessToken = $this->provider->getAccessToken('authorization_code', [
                'code' => $request->query->get('code'),
            ]);
        } catch (BadQueryStringException | IdentityProviderException $e) {
            exit($e->getMessage());
        }

        return $accessToken;
    }

    public function getResourceOwner($accessToken): SwissAlpineClubResourceOwner
    {
        if (null === $this->resourceOwner) {
            $this->resourceOwner = $this->provider->getResourceOwner($accessToken);
        }

        return $this->resourceOwner;
    }
}
