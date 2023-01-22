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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Provider;

use JetBrains\PhpStorm\Pure;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Psr\Http\Message\ResponseInterface;

class SwissAlpineClub extends AbstractProvider
{
    use BearerAuthorizationTrait;

    private array $scopes = [];
    private string $responseError = 'error';
    private string $responseResourceOwnerId = 'sub';
    private string $urlAccessToken;
    private string $urlAuthorize;
    private string $urlResourceOwnerDetails;

    public function __construct(array $options = [], array $collaborators = [])
    {
        $this->assertRequiredOptions($options);

        $possible = $this->getConfigurableOptions();
        $configured = array_intersect_key($options, array_flip($possible));

        foreach ($configured as $key => $value) {
            $this->$key = $value;
        }

        // Remove all options that are only used locally
        $options = array_diff_key($options, $configured);

        parent::__construct($options, $collaborators);
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

    /**
     * Returns all options that are required.
     */
    protected function getRequiredOptions(): array
    {
        return [
            'clientId',
            'clientSecret',
            'urlAuthorize',
            'urlAccessToken',
            'urlResourceOwnerDetails',
            'redirectUri',
            'scopes',
        ];
    }

    /**
     * Returns all options that can be configured.
     */
    #[Pure]
    protected function getConfigurableOptions(): array
    {
        return array_merge($this->getRequiredOptions(), [
            // empty: no configurable options
        ]);
    }

    #[Pure]
    protected function createResourceOwner(array $response, AccessToken $token): ResourceOwnerInterface
    {
        return new SwissAlpineClubResourceOwner($response, $this->responseResourceOwnerId);
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

    /**
     * Verifies that all required options have been passed.
     *
     * @throws \InvalidArgumentException
     */
    private function assertRequiredOptions(array $options): void
    {
        $missing = array_diff_key(array_flip($this->getRequiredOptions()), $options);

        if (!empty($missing)) {
            throw new \InvalidArgumentException('Required options not defined: '.implode(', ', array_keys($missing)));
        }
    }
}
