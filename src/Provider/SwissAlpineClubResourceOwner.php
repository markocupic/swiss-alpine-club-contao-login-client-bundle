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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Provider;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;

class SwissAlpineClubResourceOwner implements ResourceOwnerInterface
{
    protected array $arrData;
    protected string $resourceOwnerId;

    public function __construct(array $response, string $resourceOwnerId)
    {
        $this->resourceOwnerId = $resourceOwnerId;
        $this->arrData = $response;
    }

    /**
     * For testing purposes it is usefull
     * to override the user data with dummy data.
     */
    public function overrideData($arrData): void
    {
        $this->arrData = $arrData;
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

    public function getSalutation(): string
    {
        return $this->arrData['anredecode'] ?? '';
    }

    public function getLastName(): string
    {
        return $this->arrData['familienname'] ?? '';
    }

    public function getFirstName(): string
    {
        return $this->arrData['vorname'] ?? '';
    }

    /**
     * Returns the full name (e.g Fritz Muster).
     */
    public function getFullName(): string
    {
        return $this->arrData['name'] ?? '';
    }

    public function getStreet(): string
    {
        return $this->arrData['strasse'] ?? '';
    }

    public function getPostal(): string
    {
        return $this->arrData['plz'] ?? '';
    }

    public function getCity(): string
    {
        return $this->arrData['ort'] ?? '';
    }

    public function getCountryCode(): string
    {
        return isset($this->arrData['land']) ? strtolower($this->arrData['land']) : '';
    }

    public function getDateOfBirth(): string
    {
        return $this->arrData['geburtsdatum'] ?? '';
    }

    public function getSacMemberId(): string
    {
        return preg_replace('/^0+/', '', $this->arrData['contact_number'] ?? '');
    }

    public function getEmail(): string
    {
        return $this->arrData['email'] ?? '';
    }

    public function getPhoneMobile(): string
    {
        return $this->arrData['telefonmobil'] ?? '';
    }

    public function getPhonePrivate(): string
    {
        return $this->arrData['telefonp'] ?? '';
    }

    public function getPhoneBusiness(): string
    {
        return $this->arrData['telefong'] ?? '';
    }

    public function getRolesAsString(): string
    {
        return $this->arrData['Roles'] ?? '';
    }

    public function getRolesAsArray(): array
    {
        return array_map(static fn ($item) => trim($item, '"'), explode(',', $this->arrData['Roles']));
    }

    public function getDummyResourceOwnerData(bool $isMember): array
    {
        if (true === $isMember) {
            return [
                'telefonmobil' => '079 999 99 99',
                'sub' => '0e592343a-2122-11e8-91a0-00505684a4ad',
                'telefong' => '041 984 13 50',
                'familienname' => 'Messner',
                'strasse' => 'Schloss Juval',
                'vorname' => 'Reinhold',
                'Roles' => 'NAV_BULLETIN,NAV_EINZEL_00999998,NAV_D,NAV_STAMMSEKTION_S00004250,NAV_EINZEL_S00004250,NAV_EINZEL_S00004251,NAV_S00004250,NAV_F1540,NAV_BULLETIN_S00004250,Internal/everyone,NAV_NAVISION,NAV_EINZEL,NAV_MITGLIED_S00004250,NAV_HERR,NAV_F1004V,NAV_F1004V_S00004250,NAV_BULLETIN_S00004250_PAPIER',
                'contact_number' => '999998',
                'ort' => 'Vinschgau IT',
                'geburtsdatum' => '25.05.1976',
                'anredecode' => 'HERR',
                'name' => 'Messner Reinhold',
                'land' => 'IT',
                'kanton' => 'ST',
                'korrespondenzsprache' => 'D',
                'telefonp' => '099 999 99 99',
                'email' => 'r.messner@matterhorn-kiosk.ch',
                'plz' => '6208',
            ];
        }

        // Non member
        return [
            'telefonmobil' => '079 999 99 99',
            'sub' => '0e59877743a-2122-11e8-91a0-00505684a4ad',
            'telefong' => '041 984 13 50',
            'familienname' => 'Rébuffat',
            'strasse' => 'Schloss Juval',
            'vorname' => 'Gaston',
            'Roles' => 'NAV_BULLETIN,NAV_EINZEL_00999999,NAV_D,NAV_STAMMSEKTION_S00009999,NAV_EINZEL_S00009999,NAV_EINZEL_S00009999,NAV_S00009999,NAV_F1540,NAV_BULLETIN_S00009999,Internal/everyone,NAV_NAVISION,NAV_EINZEL,NAV_MITGLIED_S00009999,NAV_HERR,NAV_F1004V,NAV_F1004V_S00009999,NAV_BULLETIN_S00009999_PAPIER',
            'contact_number' => '999999',
            'ort' => 'Chamonix FR',
            'geburtsdatum' => '25.05.1976',
            'anredecode' => 'HERR',
            'name' => 'Gaston Rébuffat',
            'land' => 'IT',
            'kanton' => 'ST',
            'korrespondenzsprache' => 'D',
            'telefonp' => '099 999 99 99',
            'email' => 'm.cupic@gmx.ch',
            'plz' => '6208',
        ];
    }
}
