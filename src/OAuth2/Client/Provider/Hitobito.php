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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\OAuth2\Client\Provider;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Token\AccessTokenInterface;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Event\ParseAccessTokenEvent;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\OAuth\OAuthUser;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class Hitobito extends AbstractProvider
{
    use BearerAuthorizationTrait;

    public const string ACCESS_TOKEN_RESOURCE_OWNER_ID = 'sub';

    protected string $scopeSeparator = ' ';

    protected string $urlAuthorize;

    protected string $urlAccessToken;

    protected string $urlResourceOwnerDetails;

    protected array $scopes = [];

    protected string $responseError = 'error';

    protected EventDispatcherInterface $eventDispatcher;

    public function __construct(array $providerConfiguration, array $collaborators, EventDispatcherInterface $eventDispatcher)
    {
        foreach ($providerConfiguration as $key => $value) {
            $this->$key = $value;
        }

        $this->eventDispatcher = $eventDispatcher;

        parent::__construct($providerConfiguration, $collaborators);
    }

    public function getScopeSeparator(): string
    {
        return $this->scopeSeparator;
    }

    public function getBaseAuthorizationUrl(): string
    {
        return $this->urlAuthorize;
    }

    public function getBaseAccessTokenUrl(array $params): string
    {
        return $this->urlAccessToken;
    }

    /**
     * Requests an access token using a specified grant and option set.
     *
     * @param array<string, mixed> $options
     *
     * @return AccessTokenInterface
     *
     * @throws IdentityProviderException
     */
    public function getAccessToken($grant, array $options = [])
    {
        $grant = $this->verifyGrant($grant);

        $params = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUri,
        ];

        if (!empty($this->pkceCode)) {
            $params['code_verifier'] = $this->pkceCode;
        }

        $params = $grant->prepareRequestParameters($params, $options);
        $request = $this->getAccessTokenRequest($params);

        $response = $this->getParsedResponse($request);

        if (false === \is_array($response)) {
            throw new \UnexpectedValueException('Invalid response received from Authorization Server. Expected JSON.');
        }

        $event = new ParseAccessTokenEvent($request, $response);
        $this->eventDispatcher->dispatch($event);

        $prepared = $this->prepareAccessTokenResponse($response);

        return $this->createAccessToken($prepared, $grant);
    }

    public function getResourceOwnerDetailsUrl(AccessToken $token): string
    {
        return $this->urlResourceOwnerDetails;
    }

    protected function createResourceOwner(array $response, AccessToken $token): ResourceOwnerInterface
    {
        return new OAuthUser($response, self::ACCESS_TOKEN_RESOURCE_OWNER_ID);
    }

    protected function getDefaultScopes(): array
    {
        return $this->scopes;
    }

    protected function checkResponse(ResponseInterface $response, $data): void
    {
        if (!empty($data[$this->responseError])) {
            $error = $data[$this->responseError];

            if (!\is_string($error)) {
                $error = var_export($error, true);
            }

            $code = isset($this->responseCode) && !empty($data[$this->responseCode]) ? $data[$this->responseCode] : 0;

            if (!\is_int($code)) {
                $code = (int) $code;
            }

            throw new IdentityProviderException($error, $code, $data);
        }
    }
}
