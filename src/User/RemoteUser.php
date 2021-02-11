<?php

declare(strict_types=1);

/*
 * This file is part of Swiss Alpine Club Contao Login Client Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
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
 * Class RemoteUser.
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
     * remote user data.
     */
    private $data = [];

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * RemoteUser constructor.
     */
    public function __construct(ContaoFramework $framework, User $user, Session $session, TranslatorInterface $translator)
    {
        $this->framework = $framework;
        $this->user = $user;
        $this->session = $session;
        $this->translator = $translator;

        $this->framework->initialize();
    }

    public function create(array $arrData): void
    {
        foreach ($arrData as $k => $v) {
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
     *
     * @return mixed|null
     */
    public function get($key)
    {
        $arrData = $this->getData();

        if (isset($arrData[$key])) {
            return $arrData[$key];
        }

        return null;
    }

    /**
     * Check if remote user has a valid uuid/sub.
     */
    public function checkHasUuid(): void
    {
        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        if (empty($this->get('sub'))) {
            $arrError = [
                'level' => 'warning',
                'matter' => $this->translator->trans('ERR.sacOidcLoginError_invalidUuid_matter', [], 'contao_default'),
                'howToFix' => $this->translator->trans('ERR.sacOidcLoginError_invalidUuid_howToFix', [], 'contao_default'),
                //'explain' => $this->translator->trans('ERR.sacOidcLoginError_invalidUuid_explain', [], 'contao_default'),
            ];

            $flashBagKey = $systemAdapter->getContainer()->getParameter('markocupic.swiss_alpine_club_contao_login_client_bundle.session.flash_bag_key');
            $this->session->getFlashBag()->add($flashBagKey, $arrError);
            $bagName = $systemAdapter->getContainer()->getParameter('markocupic.swiss_alpine_club_contao_login_client_bundle.session.attribute_bag_name');
            $controllerAdapter->redirect($this->session->getBag($bagName)->get('failurePath'));
        }
    }

    /**
     * Check if remote user is SAC member.
     */
    public function checkIsSacMember(): void
    {
        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        if (empty($this->get('contact_number')) || empty($this->get('Roles')) || empty($this->get('sub'))) {
            $arrError = [
                'level' => 'warning',
                'matter' => $this->translator->trans('ERR.sacOidcLoginError_userIsNotSacMember_matter', [$this->get('vorname')], 'contao_default'),
                'howToFix' => $this->translator->trans('ERR.sacOidcLoginError_userIsNotSacMember_howToFix', [], 'contao_default'),
                //'explain' => $this->translator->trans('ERR.sacOidcLoginError_userIsNotSacMember_explain', [], 'contao_default'),
            ];
            $flashBagKey = $systemAdapter->getContainer()->getParameter('markocupic.swiss_alpine_club_contao_login_client_bundle.session.flash_bag_key');
            $this->session->getFlashBag()->add($flashBagKey, $arrError);
            $bagName = $systemAdapter->getContainer()->getParameter('markocupic.swiss_alpine_club_contao_login_client_bundle.session.attribute_bag_name');
            $controllerAdapter->redirect($this->session->getBag($bagName)->get('failurePath'));
        }
    }

    /**
     * Check for allowed section membership.
     *
     * @return bool
     */
    public function checkIsMemberInAllowedSection(): void
    {
        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        $arrMembership = $this->getGroupMembership();

        if (\count($arrMembership) > 0) {
            return;
        }

        $arrError = [
            'level' => 'warning',
            'matter' => $this->translator->trans('ERR.sacOidcLoginError_userIsNotMemberOfAllowedSection_matter', [$this->get('vorname')], 'contao_default'),
            'howToFix' => $this->translator->trans('ERR.sacOidcLoginError_userIsNotMemberOfAllowedSection_howToFix', [], 'contao_default'),
            //'explain' => $this->translator->trans('ERR.sacOidcLoginError_userIsNotMemberOfAllowedSection_explain', [], 'contao_default'),
        ];
        $flashBagKey = $systemAdapter->getContainer()->getParameter('markocupic.swiss_alpine_club_contao_login_client_bundle.session.flash_bag_key');
        $this->session->getFlashBag()->add($flashBagKey, $arrError);
        $bagName = $systemAdapter->getContainer()->getParameter('markocupic.swiss_alpine_club_contao_login_client_bundle.session.attribute_bag_name');
        $controllerAdapter->redirect($this->session->getBag($bagName)->get('failurePath'));
    }

    /**
     * Check for a valid email address.
     */
    public function checkHasValidEmail(): void
    {
        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        /** @var Validator $validatorAdapter */
        $validatorAdapter = $this->framework->getAdapter(Validator::class);

        if (empty($this->get('email')) || !$validatorAdapter->isEmail($this->get('email'))) {
            $arrError = [
                'level' => 'warning',
                'matter' => $this->translator->trans('ERR.sacOidcLoginError_invalidEmail_matter', [$this->get('vorname')], 'contao_default'),
                'howToFix' => $this->translator->trans('ERR.sacOidcLoginError_invalidEmail_howToFix', [], 'contao_default'),
                'explain' => $this->translator->trans('ERR.sacOidcLoginError_invalidEmail_explain', [], 'contao_default'),
            ];
            $flashBagKey = $systemAdapter->getContainer()->getParameter('markocupic.swiss_alpine_club_contao_login_client_bundle.session.flash_bag_key');
            $this->session->getFlashBag()->add($flashBagKey, $arrError);
            $bagName = $systemAdapter->getContainer()->getParameter('markocupic.swiss_alpine_club_contao_login_client_bundle.session.attribute_bag_name');
            $controllerAdapter->redirect($this->session->getBag($bagName)->get('failurePath'));
        }
    }

    /**
     * Return array with club ids.
     */
    public function getGroupMembership(): array
    {
        $configAdapter = $this->framework->getAdapter(Config::class);

        $strRoles = $this->get('Roles');
        $arrMembership = [];
        $arrClubIds = explode(',', $configAdapter->get('SAC_EVT_SAC_SECTION_IDS'));

        if (null !== $strRoles && !empty($strRoles)) {
            foreach ($arrClubIds as $clubId) {
                // Search for NAV_MITGLIED_S00004250 or NAV_MITGLIED_S00004251, etc.
                $pattern = '/NAV_MITGLIED_S([0])+'.$clubId.'/';

                if (preg_match($pattern, $strRoles)) {
                    $arrMembership[] = $clubId;
                }
            }
        }

        return $arrMembership;
    }

    public function getMockUserData(bool $isMember = true): array
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
