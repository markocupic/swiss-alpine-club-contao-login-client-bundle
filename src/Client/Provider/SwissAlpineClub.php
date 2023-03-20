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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Client\Provider;

use JetBrains\PhpStorm\Pure;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\OAuth\OAuthUser;
use Psr\Http\Message\ResponseInterface;

class SwissAlpineClub extends AbstractProvider
{
    use BearerAuthorizationTrait;

    protected string $urlAuthorize;
    protected string $urlAccessToken;
    protected string $urlResourceOwnerDetails;
    protected array $scopes = [];
    protected string $responseError = 'error';
    protected string $responseResourceOwnerId = 'sub';

    public function __construct(array $providerConfiguration = [], array $collaborators = [])
    {
        foreach ($providerConfiguration as $key => $value) {
            $this->$key = $value;
        }

        parent::__construct($providerConfiguration, $collaborators);
    }

    public function getBaseAuthorizationUrl(): string
    {
        return $this->urlAuthorize;
    }

    public function getBaseAccessTokenUrl(array $params): string
    {
        return $this->urlAccessToken;
    }

    public function getResourceOwnerDetailsUrl(AccessToken $token): string
    {
        return $this->urlResourceOwnerDetails;
    }

    /**
     * Requests and returns the resource owner of given access token.
     */
    public function getResourceOwner(AccessToken $token): ResourceOwnerInterface
    {
        $response = $this->fetchResourceOwnerDetails($token);

        return $this->createResourceOwner($response, $token);
    }

    #[Pure]
    protected function createResourceOwner(array $response, AccessToken $token): ResourceOwnerInterface
    {
        return new OAuthUser($response, $this->responseResourceOwnerId);
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
