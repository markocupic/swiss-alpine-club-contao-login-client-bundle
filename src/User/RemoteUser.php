<?php

declare(strict_types=1);

/**
 * Swiss Alpine Club (SAC) Contao Login Client Bundle
 * Copyright (c) 2008-2020 Marko Cupic
 * @package swiss-alpine-club-contao-login-client-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/swiss-alpine-club-contao-login-client-bundle
 */

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\User;

use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\System;
use Contao\Validator;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Class RemoteUser
 * @package Markocupic\SwissAlpineClubContaoLoginClientBundle\User
 */
class RemoteUser
{

    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var User
     */
    private $user;

    /**
     * @var Session
     */
    private $session;

    /**
     * remote user data
     */
    private $data = [];

    /**
     * RemoteUser constructor.
     * @param ContaoFramework $framework
     * @param User $user
     * @param Session $session
     */
    public function __construct(ContaoFramework $framework, User $user, Session $session)
    {
        $this->framework = $framework;
        $this->user = $user;
        $this->session = $session;

        $this->framework->initialize();
    }

    /**
     * @param array $arrData
     */
    public function create(array $arrData)
    {
        foreach ($arrData as $k => $v)
        {
            $this->data[$k] = $v;
        }
    }

    /**
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param $key
     * @return mixed|null
     */
    public function get($key)
    {
        $arrData = $this->getData();
        if (isset($arrData[$key]))
        {
            return $arrData[$key];
        }
        return null;
    }

    /**
     * Check if remote user is SAC member
     */
    public function checkIsSacMember(): void
    {
        if (empty($this->get('contact_number')) || empty($this->get('Roles')) || empty($this->get('contact_number')) || empty($this->get('sub')))
        {
            if (empty($this->get('contact_number')) || empty($this->get('Roles')) || empty($this->get('contact_number')) || empty($this->get('sub')))
            {
                $arrError = [
                    'matter'   => sprintf('Hallo %s<br>Schön bist du hier. Leider hat die Überprüfung deiner vom Identity Provider an uns übermittelten Daten fehlgeschlagen.', $this->get('vorname')),
                    'howToFix' => 'Du musst Mitglied dieser Sektion sein, um dich auf diesem Portal einloggen zu können. Wenn du eine Mitgliedschaft beantragen möchtest, darfst du dich sehr gerne bei userer Geschäftsstelle melden.',
                    //'explain'  => 'Der geschütze Bereich ist nur Mitgliedern des SAC (Schweizerischer Alpen Club) zugänglich.',
                ];
                $flashBagKey = System::getContainer()->getParameter('swiss_alpine_club_contao_login_client.session.flash_bag_key');
                $this->session->getFlashBag()->add($flashBagKey, $arrError);
                $bagName = System::getContainer()->getParameter('swiss_alpine_club_contao_login_client.session.attribute_bag_name');
                Controller::redirect($this->session->getBag($bagName)->get('errorPath'));
            }
        }
    }

    /**
     * Check for allowed section membership
     * @return bool
     */
    public function checkIsMemberInAllowedSection(): void
    {
        $arrMembership = $this->getGroupMembership();
        if (count($arrMembership) > 0)
        {
            return;
        }

        $arrError = [
            'matter'   => sprintf('Hallo %s<br>Schön bist du hier. Leider hat die Überprüfung deiner vom Identity Provider an uns übermittelten Daten fehlgeschlagen.', $this->get('vorname')),
            'howToFix' => sprintf('Du musst Mitglied unserer SAC Sektion sein, um dich auf diesem Portal einloggen zu können. Wenn du eine Zusatzmitgliedschaft beantragen möchtest, dann darfst du dich sehr gerne bei unserer Geschäftsstelle melden.', $this->get('name')),
            //'explain'  => 'Der geschütze Bereich ist nur Mitgliedern dieser SAC Sektion zugänglich.',
        ];
        $flashBagKey = System::getContainer()->getParameter('swiss_alpine_club_contao_login_client.session.flash_bag_key');
        $this->session->getFlashBag()->add($flashBagKey, $arrError);
        $bagName = System::getContainer()->getParameter('swiss_alpine_club_contao_login_client.session.attribute_bag_name');
        Controller::redirect($this->session->getBag($bagName)->get('errorPath'));
    }

    /**
     * Validate username
     */
    public function checkHasValidUsername(): void
    {
        if (empty($this->get('contact_number') || !$this->user->isValidUsername($this->get('contact_number'))))
        {
            $arrError = [
                'matter'   => 'Schön bist du hier. Leider hat die Überprüfung deiner vom Identity Provider an uns übermittelten Daten fehlgeschlagen.',
                'howToFix' => 'Bitte überprüfe die Schreibweise deiner Eingaben.',
                'explain'  => '',
            ];
            $flashBagKey = System::getContainer()->getParameter('swiss_alpine_club_contao_login_client.session.flash_bag_key');
            $this->session->getFlashBag()->add($flashBagKey, $arrError);
            $bagName = System::getContainer()->getParameter('swiss_alpine_club_contao_login_client.session.attribute_bag_name');
            Controller::redirect($this->session->getBag($bagName)->get('errorPath'));
        }
    }

    /**
     * Check for a valid email address
     */
    public function checkHasValidEmail(): void
    {
        if (empty($this->get('email')) || !Validator::isEmail($this->get('email')))
        {
            $arrError = [
                'matter'   => sprintf('Hallo %s<br>Schön bist du hier. Leider hat die Überprüfung deiner vom Identity Provider an uns übermittelten Daten fehlgeschlagen.', $this->get('vorname')),
                'howToFix' => 'Du hast noch keine gültige E-Mail-Adresse hinterlegt. Bitte logge dich auf https:://www.sac-cas.ch mit deinem Account ein und hinterlege deine E-Mail-Adresse.',
                'explain'  => 'Einige Anwendungen (z.B. Event-Tool) auf diesem Portal setzen eine gültige E-Mail-Adresse voraus.',
            ];
            $flashBagKey = System::getContainer()->getParameter('swiss_alpine_club_contao_login_client.session.flash_bag_key');
            $this->session->getFlashBag()->add($flashBagKey, $arrError);
            $bagName = System::getContainer()->getParameter('swiss_alpine_club_contao_login_client.session.attribute_bag_name');
            Controller::redirect($this->session->getBag($bagName)->get('errorPath'));
        }
    }

    /**
     * @return array
     */
    public function getGroupMembership(): array
    {
        $strRoles = $this->get('Roles');
        $arrMembership = [];
        $arrClubIds = explode(',', Config::get('SAC_EVT_SAC_SECTION_IDS'));
        if ($strRoles !== null && !empty($strRoles))
        {
            foreach ($arrClubIds as $arrClubId)
            {
                // Search for NAV_MITGLIED_S00004250 or NAV_MITGLIED_S00004251, etc.
                $pattern = '/NAV_MITGLIED_S([0])+' . $arrClubId . '/';
                if (preg_match($pattern, $strRoles))
                {
                    $arrMembership[] = $arrClubId;
                }
            }
        }
        return $arrMembership;
    }

    /**
     * @param bool $isMember
     * @return array
     */
    public function getMockUserData($isMember = true): array
    {
        if ($isMember === true)
        {
            return [
                'telefonmobil'         => '079 999 99 99',
                'sub'                  => '0e592343a-2122-11e8-91a0-00505684a4ad',
                'telefong'             => '041 984 13 50',
                'familienname'         => 'Messner',
                'strasse'              => 'Schloss Juval',
                'vorname'              => 'Reinhold',
                'Roles'                => 'NAV_BULLETIN,NAV_EINZEL_00999998,NAV_D,NAV_STAMMSEKTION_S00004250,NAV_EINZEL_S00004250,NAV_EINZEL_S00004251,NAV_S00004250,NAV_F1540,NAV_BULLETIN_S00004250,Internal/everyone,NAV_NAVISION,NAV_EINZEL,NAV_MITGLIED_S00004250,NAV_HERR,NAV_F1004V,NAV_F1004V_S00004250,NAV_BULLETIN_S00004250_PAPIER',
                'contact_number'       => '999998',
                'ort'                  => 'Vinschgau IT',
                'geburtsdatum'         => '25.05.1976',
                'anredecode'           => 'HERR',
                'name'                 => 'Messner Reinhold',
                'land'                 => 'IT',
                'kanton'               => 'ST',
                'korrespondenzsprache' => 'D',
                'telefonp'             => '099 999 99 99',
                'email'                => 'r.messner@matterhorn-kiosk.ch',
                'plz'                  => '6208',
            ];
        }

        // Non member
        return [
            'telefonmobil'         => '079 999 99 99',
            'sub'                  => '0e59877743a-2122-11e8-91a0-00505684a4ad',
            'telefong'             => '041 984 13 50',
            'familienname'         => 'Rébuffat',
            'strasse'              => 'Schloss Juval',
            'vorname'              => 'Gaston',
            'Roles'                => 'NAV_BULLETIN,NAV_EINZEL_00999999,NAV_D,NAV_STAMMSEKTION_S00009999,NAV_EINZEL_S00009999,NAV_EINZEL_S00009999,NAV_S00009999,NAV_F1540,NAV_BULLETIN_S00009999,Internal/everyone,NAV_NAVISION,NAV_EINZEL,NAV_MITGLIED_S00009999,NAV_HERR,NAV_F1004V,NAV_F1004V_S00009999,NAV_BULLETIN_S00009999_PAPIER',
            'contact_number'       => '999999',
            'ort'                  => 'Chamonix FR',
            'geburtsdatum'         => '25.05.1976',
            'anredecode'           => 'HERR',
            'name'                 => 'Gaston Rébuffat',
            'land'                 => 'IT',
            'kanton'               => 'ST',
            'korrespondenzsprache' => 'D',
            'telefonp'             => '099 999 99 99',
            'email'                => 'm.cupic@gmx.ch',
            'plz'                  => '6208',
        ];
    }

}
