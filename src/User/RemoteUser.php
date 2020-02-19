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
use Symfony\Component\Translation\TranslatorInterface;

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
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * RemoteUser constructor.
     * @param ContaoFramework $framework
     * @param User $user
     * @param Session $session
     * @param TranslatorInterface $translator
     */
    public function __construct(ContaoFramework $framework, User $user, Session $session, TranslatorInterface $translator)
    {
        $this->framework = $framework;
        $this->user = $user;
        $this->session = $session;
        $this->translator = $translator;

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
     * Check if remote user has a valid uuid/sub
     */
    public function checkHasUuid(): void
    {
        if (empty($this->get('sub')))
        {
            $arrError = [
                'level'    => 'warning',
                'matter'   => $this->translator->trans('ERR.sacOidcLoginError_invalidUuid_matter', [], 'contao_default'),
                'howToFix' => $this->translator->trans('ERR.sacOidcLoginError_invalidUuid_howToFix', [], 'contao_default'),
                //'explain' => $this->translator->trans('ERR.sacOidcLoginError_invalidUuid_explain', [], 'contao_default'),
            ];

            $flashBagKey = System::getContainer()->getParameter('swiss_alpine_club_contao_login_client.session.flash_bag_key');
            $this->session->getFlashBag()->add($flashBagKey, $arrError);
            $bagName = System::getContainer()->getParameter('swiss_alpine_club_contao_login_client.session.attribute_bag_name');
            Controller::redirect($this->session->getBag($bagName)->get('failurePath'));
        }
    }

    /**
     * Check if remote user is SAC member
     */
    public function checkIsSacMember(): void
    {
        if (empty($this->get('contact_number')) || empty($this->get('Roles')) || empty($this->get('sub')))
        {
            $arrError = [
                'level'    => 'warning',
                'matter'   => $this->translator->trans('ERR.sacOidcLoginError_userIsNotSacMember_matter', [$this->get('vorname')], 'contao_default'),
                'howToFix' => $this->translator->trans('ERR.sacOidcLoginError_userIsNotSacMember_howToFix', [], 'contao_default'),
                //'explain' => $this->translator->trans('ERR.sacOidcLoginError_userIsNotSacMember_explain', [], 'contao_default'),
            ];
            $flashBagKey = System::getContainer()->getParameter('swiss_alpine_club_contao_login_client.session.flash_bag_key');
            $this->session->getFlashBag()->add($flashBagKey, $arrError);
            $bagName = System::getContainer()->getParameter('swiss_alpine_club_contao_login_client.session.attribute_bag_name');
            Controller::redirect($this->session->getBag($bagName)->get('failurePath'));
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
            'level'    => 'warning',
            'matter'   => $this->translator->trans('ERR.sacOidcLoginError_userIsNotMemberOfAllowedSection_matter', [$this->get('vorname')], 'contao_default'),
            'howToFix' => $this->translator->trans('ERR.sacOidcLoginError_userIsNotMemberOfAllowedSection_howToFix', [], 'contao_default'),
            //'explain' => $this->translator->trans('ERR.sacOidcLoginError_userIsNotMemberOfAllowedSection_explain', [], 'contao_default'),
        ];
        $flashBagKey = System::getContainer()->getParameter('swiss_alpine_club_contao_login_client.session.flash_bag_key');
        $this->session->getFlashBag()->add($flashBagKey, $arrError);
        $bagName = System::getContainer()->getParameter('swiss_alpine_club_contao_login_client.session.attribute_bag_name');
        Controller::redirect($this->session->getBag($bagName)->get('failurePath'));
    }

    /**
     * Check for a valid email address
     */
    public function checkHasValidEmail(): void
    {
        if (empty($this->get('email')) || !Validator::isEmail($this->get('email')))
        {
            $arrError = [
                'level'    => 'warning',
                'matter'   => $this->translator->trans('ERR.sacOidcLoginError_invalidEmail_matter', [$this->get('vorname')], 'contao_default'),
                'howToFix' => $this->translator->trans('ERR.sacOidcLoginError_invalidEmail_howToFix', [], 'contao_default'),
                'explain'  => $this->translator->trans('ERR.sacOidcLoginError_invalidEmail_explain', [], 'contao_default'),
            ];
            $flashBagKey = System::getContainer()->getParameter('swiss_alpine_club_contao_login_client.session.flash_bag_key');
            $this->session->getFlashBag()->add($flashBagKey, $arrError);
            $bagName = System::getContainer()->getParameter('swiss_alpine_club_contao_login_client.session.attribute_bag_name');
            Controller::redirect($this->session->getBag($bagName)->get('failurePath'));
        }
    }

    /**
     * Return array with club ids
     * @return array
     */
    public function getGroupMembership(): array
    {
        $strRoles = $this->get('Roles');
        $arrMembership = [];
        $arrClubIds = explode(',', Config::get('SAC_EVT_SAC_SECTION_IDS'));
        if ($strRoles !== null && !empty($strRoles))
        {
            foreach ($arrClubIds as $clubId)
            {
                // Search for NAV_MITGLIED_S00004250 or NAV_MITGLIED_S00004251, etc.
                $pattern = '/NAV_MITGLIED_S([0])+' . $clubId . '/';
                if (preg_match($pattern, $strRoles))
                {
                    $arrMembership[] = $clubId;
                }
            }
        }
        return $arrMembership;
    }

    /**
     * @param bool $isMember
     * @return array
     */
    public function getMockUserData(bool $isMember = true): array
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
