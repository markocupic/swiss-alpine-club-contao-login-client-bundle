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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Tests\Fixtures;

/**
 * Sample resource owner claims as they are returned by the Hitobito userinfo
 * endpoint. Used to build an OAuthUser without talking to the identity provider.
 */
final class ResourceOwnerFixtures
{
    /**
     * @return array<string, mixed>
     */
    public static function claims(bool $isMember): array
    {
        if (true === $isMember) {
            return [
                'sub' => '123456',
                'first_name' => 'Reinhold',
                'last_name' => 'Messner',
                'nickname' => '',
                'company_name' => '',
                'company' => '',
                'email' => 'r.messner@matterhorn-kiosk.ch',
                'address_care_of' => '',
                'street' => 'Schloss Juwal',
                'housenumber' => '2',
                'postbox' => '12345',
                'zip_code' => '8888',
                'town' => 'Vinschgau IT',
                'country' => 'IT',
                'gender' => 'm', // can be w (female), m (male) or empty string (divers)
                'language' => 'de',
                'address' => 'Schloss Juwal 2',
                'roles' => [
                    [
                        'group_id' => '1417',
                        'group_name' => 'Mitglieder',
                        'role' => 'Group::SektionsMitglieder::Mitglied',
                        'role_class' => 'Group::SektionsMitglieder::Mitglied',
                        'role_name' => 'Mitglied (Stammsektion)',
                        'permissions' => [],
                        'layer_group_id' => '1415', // Sektions ID
                        'layer_group_name' => 'SAC Pilatus',
                    ],
                    [
                        'group_id' => '1427',
                        'group_name' => 'Mitglieder',
                        'role' => 'Group::SektionsMitglieder::MitgliedZusatzsektion',
                        'role_class' => 'Group::SektionsMitglieder::MitgliedZusatzsektion',
                        'role_name' => 'Mitglied (Zusatzsektion)',
                        'permissions' => [],
                        'layer_group_id' => '1425', // Sektions ID
                        'layer_group_name' => 'SAC Pilatus Napf',
                    ],
                    [
                        'group_id' => '1571',
                        'group_name' => 'Mitglieder',
                        'role' => 'Group::SektionsMitglieder::MitgliedZusatzsektion',
                        'role_class' => 'Group::SektionsMitglieder::MitgliedZusatzsektion',
                        'role_name' => 'Mitglied (Zusatzsektion)',
                        'permissions' => [],
                        'layer_group_id' => '1569', // Sektions ID
                        'layer_group_name' => 'SAC Uto',
                    ],
                ],
                'picture_url' => 'https://sac-cas.puzzle.ch/packs/media/images/profile-c150952c7e2ec2cf298980d55b2bcde3.svg',
                'membership_verify_url' => 'https://sac-cas.puzzle.ch/verify_membership/ddERxr7Jky5Ck8ZpjFZRTGg2',
                'phone' => '077 777 77 77', // Phone number must be tagged as Haupt-Telefon in https://sac-cas.puzzle.ch/
                'membership_years' => '3.0',
                'user_groups' => [
                    'SAC_member',
                    'SAC_member_additional',
                    'Group::SektionsMitglieder::Mitglied#1417',
                    'Group::SektionsMitglieder::MitgliedZusatzsektion#1427',
                    'Group::SektionsMitglieder::MitgliedZusatzsektion#1571',
                ],
            ];
        }

        // Non member
        return [
            'sub' => '987654',
            'first_name' => 'Gaston',
            'last_name' => 'Rébuffat',
            'nickname' => '',
            'company_name' => '',
            'company' => '',
            'email' => 'g.rebuffat@chamonix-montblanc.fr',
            'address_care_of' => '',
            'street' => 'Rue de chamois',
            'housenumber' => '2',
            'postbox' => '12345',
            'zip_code' => '74056',
            'town' => 'Chamonix FR',
            'country' => 'FR',
            'gender' => 'm', // can be w (female), m (male) or empty string (divers)
            'language' => 'fr',
            'address' => 'Rue de chamois 2',
            'roles' => [
                [
                    'group_id' => '9999',
                    'group_name' => 'Mitglieder',
                    'role' => 'Group::SektionsMitglieder::Mitglied',
                    'role_class' => 'Group::SektionsMitglieder::Mitglied',
                    'role_name' => 'Mitglied (Stammsektion)',
                    'permissions' => [],
                    'layer_group_id' => '8999', // Sektions ID
                    'layer_group_name' => 'SAC Sektion Fake 1',
                ],
                [
                    'group_id' => '9998',
                    'group_name' => 'Mitglieder',
                    'role' => 'Group::SektionsMitglieder::MitgliedZusatzsektion',
                    'role_class' => 'Group::SektionsMitglieder::MitgliedZusatzsektion',
                    'role_name' => 'Mitglied (Zusatzsektion)',
                    'permissions' => [],
                    'layer_group_id' => '8998', // Sektions ID
                    'layer_group_name' => 'SAC Sektion Fake 2',
                ],
                [
                    'group_id' => '9997',
                    'group_name' => 'Mitglieder',
                    'role' => 'Group::SektionsMitglieder::MitgliedZusatzsektion',
                    'role_class' => 'Group::SektionsMitglieder::MitgliedZusatzsektion',
                    'role_name' => 'Mitglied (Zusatzsektion)',
                    'permissions' => [],
                    'layer_group_id' => '8997', // Sektions ID
                    'layer_group_name' => 'SAC Sektion Fake 3',
                ],
            ],
            'picture_url' => 'https://sac-cas.puzzle.ch/packs/media/images/profile-c150952c7e2ec2cf298980d55b2bcde3.svg',
            'membership_verify_url' => 'https://sac-cas.puzzle.ch/verify_membership/ddERxr7Jky5Ck8ZpjFZRTGg2',
            'phone' => '079 999 99 99', // Phone number must be tagged as Haupt-Telefon in https://sac-cas.puzzle.ch/
            'membership_years' => '3.0',
            'user_groups' => [
                'SAC_member',
                'SAC_member_additional',
                'Group::SektionsMitglieder::Mitglied#1417',
                'Group::SektionsMitglieder::MitgliedZusatzsektion#1427',
                'Group::SektionsMitglieder::MitgliedZusatzsektion#1571',
            ],
        ];
    }
}
