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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\OAuth;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;

/**
 * https://github.com/hitobito/hitobito/blob/master/doc/developer/people/oauth.md#openid-connect-oidc.
 */
class OAuthUser implements ResourceOwnerInterface
{
    public function __construct(
        protected array $arrData,
        protected string $resourceOwnerId,
    ) {
    }

    /**
     * Returns the identifier of the authorized resource owner.
     */
    public function getId(): string
    {
        return $this->arrData[$this->resourceOwnerId];
    }

    /**
     * Returns the raw resource owner response.
     */
    public function toArray(): array
    {
        return $this->arrData;
    }

    public function getGender(): string
    {
        return match ($this->arrData['gender'] ?? '') {
            'm' => 'male',
            'w' => 'female',
            default => 'other',
        };
    }

    public function getLastName(): string
    {
        return $this->arrData['last_name'] ?? '';
    }

    public function getFirstName(): string
    {
        return $this->arrData['first_name'] ?? '';
    }

    /**
     * Returns the full name (e.g Muster Fritz).
     */
    public function getFullName(): string
    {
        return trim($this->arrData['last_name'].' '.$this->arrData['first_name']);
    }

    public function getStreet(): string
    {
        return $this->arrData['address'] ?? '';
    }

    public function getPostal(): string
    {
        return $this->arrData['zip_code'] ?? '';
    }

    public function getCity(): string
    {
        return $this->arrData['town'] ?? '';
    }

    public function getCountryCode(): string
    {
        return strtolower($this->arrData['country'] ?? '');
    }

    public function getLanguage(): string
    {
        return strtolower($this->arrData['language'] ?? 'de');
    }

    public function getDateOfBirth(): string
    {
        return $this->arrData['birthday'] ?? '';
    }

    public function getSacMemberId(): string
    {
        return ltrim($this->arrData['sub'] ?? '', '0');
    }

    public function getEmail(): string
    {
        return $this->arrData['email'] ?? '';
    }

    public function getPhone(): string
    {
        return $this->arrData['phone'] ?? '';
    }

    public function getRolesAsString(): string
    {
        return json_encode($this->getRolesAsArray());
    }

    public function getRolesAsArray(): array
    {
        return $this->arrData['roles'] ?? [];
    }

    /**
     * The raw Hitobito layer group ids of all roles the user holds. Mapping them
     * to SAC section ids is the job of the OAuthUserChecker.
     *
     * @param list<string> $allowedRoles
     *
     * @return list<int>
     */
    public function getHitobitoLayerGroupIds(array $allowedRoles): array
    {
        $roles = $this->getRolesAsArray();

        if (empty($roles)) {
            return [];
        }

        $arrIds = [];

        foreach ($roles as $role) {
            if (empty($role['role']) || empty($role['layer_group_id'])) {
                continue;
            }

            if (!\in_array($role['role'], $allowedRoles, true)) {
                continue;
            }

            $arrIds[] = (int) $role['layer_group_id'];
        }

        return $arrIds;
    }
}
