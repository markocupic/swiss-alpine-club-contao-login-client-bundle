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
use Contao\Validator;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Authorization\AuthorizationHelper;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Session\Session;

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
     * remote user data
     */
    private $data = [];

    /**
     * RemoteUser constructor.
     * @param ContaoFramework $framework
     * @param AuthorizationHelper $authorizationHelper
     * @param User $user
     */
    public function __construct(ContaoFramework $framework, AuthorizationHelper $authorizationHelper, User $user, Session $session)
    {
        $this->framework = $framework;
        $this->authorizationHelper = $authorizationHelper;
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
                $this->session->addFlashBagMessage($arrError);
                Controller::redirect($this->session->sessionGet('errorPath'));
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
        $this->session->addFlashBagMessage($arrError);
        Controller::redirect($this->session->sessionGet('errorPath'));
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
            $this->session->addFlashBagMessage($arrError);
            Controller::redirect($this->session->sessionGet('errorPath'));
        }
    }

    /**
     * Validate email
     * @todo Check for unique email address
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
            $this->session->addFlashBagMessage($arrError);
            Controller::redirect($this->session->sessionGet('errorPath'));
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

}
