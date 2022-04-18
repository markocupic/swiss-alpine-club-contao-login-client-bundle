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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\User;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\System;
use Contao\Validator;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Controller\Authentication\AuthenticationController;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\ErrorMessage\ErrorMessage;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\ErrorMessage\ErrorMessageManager;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class RemoteUser.
 */
class RemoteUser
{
    /**
     * Navision section id regex.
     */
    public const NAV_SECTION_ID_REGEX = '/NAV_MITGLIED_S(\d+)/';

    private ContaoFramework $framework;
    private TranslatorInterface $translator;
    private ErrorMessageManager $errorMessageManager;
    private array $data = [];
    private string $contaoScope = '';

    /**
     * RemoteUser constructor.
     */
    public function __construct(ContaoFramework $framework, TranslatorInterface $translator, ErrorMessageManager $errorMessageManager)
    {
        $this->framework = $framework;
        $this->translator = $translator;
        $this->errorMessageManager = $errorMessageManager;
    }

    /**
     * Service method call.
     */
    public function initializeFramework(): void
    {
        // Initialize Contao framework
        $this->framework->initialize();
    }

    /**
     * @throws \Exception
     */
    public function create(array $arrData, string $contaoScope): void
    {
        $this->setContaoScope($contaoScope);

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
    public function checkHasUuid(): bool
    {
        /** @var System $systemAdapter */
        if (empty($this->get('sub'))) {
            $this->errorMessageManager->add2Flash(
                new ErrorMessage(
                    ErrorMessage::LEVEL_WARNING,
                    $this->translator->trans('ERR.sacOidcLoginError_invalidUuid_matter', [], 'contao_default'),
                    $this->translator->trans('ERR.sacOidcLoginError_invalidUuid_howToFix', [], 'contao_default'),
                )
            );

            return false;
        }

        return true;
    }

    /**
     * Check if remote user is SAC member.
     */
    public function checkIsSacMember(): bool
    {
        if (!$this->isSacMember()) {
            $this->errorMessageManager->add2Flash(
                new ErrorMessage(
                    ErrorMessage::LEVEL_WARNING,
                    $this->translator->trans('ERR.sacOidcLoginError_userIsNotSacMember_matter', [$this->get('vorname')], 'contao_default'),
                    $this->translator->trans('ERR.sacOidcLoginError_userIsNotSacMember_howToFix', [], 'contao_default'),
                )
            );

            return false;
        }

        return true;
    }

    /**
     * Check for allowed section membership.
     */
    public function checkIsMemberInAllowedSection(): bool
    {
        $arrMembership = $this->getAllowedSacSectionIds();

        if (\count($arrMembership) > 0) {
            return true;
        }

        $this->errorMessageManager->add2Flash(
            new ErrorMessage(
                ErrorMessage::LEVEL_WARNING,
                $this->translator->trans('ERR.sacOidcLoginError_userIsNotMemberOfAllowedSection_matter', [$this->get('vorname')], 'contao_default'),
                $this->translator->trans('ERR.sacOidcLoginError_userIsNotMemberOfAllowedSection_howToFix', [], 'contao_default'),
            )
        );

        return false;
    }

    /**
     * Check for a valid email address.
     */
    public function checkHasValidEmail(): bool
    {
        /** @var Validator $validatorAdapter */
        $validatorAdapter = $this->framework->getAdapter(Validator::class);

        if (empty($this->get('email')) || !$validatorAdapter->isEmail($this->get('email'))) {
            $this->errorMessageManager->add2Flash(
                new ErrorMessage(
                    ErrorMessage::LEVEL_WARNING,
                    $this->translator->trans('ERR.sacOidcLoginError_invalidEmail_matter', [$this->get('vorname')], 'contao_default'),
                    $this->translator->trans('ERR.sacOidcLoginError_invalidEmail_howToFix', [], 'contao_default'),
                    $this->translator->trans('ERR.sacOidcLoginError_invalidEmail_explain', [], 'contao_default'),
                )
            );

            return false;
        }

        return true;
    }

    /**
     * Return all allowed sac sections ids a remote user belongs to.
     */
    public function getAllowedSacSectionIds(): array
    {
        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        if (AuthenticationController::CONTAO_SCOPE_FRONTEND === $this->contaoScope) {
            $arrAllowedGroups = $systemAdapter
                ->getContainer()
                ->getParameter('sac_oauth2_client.oidc.allowed_frontend_sac_section_ids')
            ;
        } else {
            $arrAllowedGroups = $systemAdapter
                ->getContainer()
                ->getParameter('sac_oauth2_client.oidc.allowed_backend_sac_section_ids')
            ;
        }

        $arrGroupMembership = $this->getSacSectionIds();

        return array_unique(array_intersect($arrAllowedGroups, $arrGroupMembership));
    }

    /**
     * Check if remote user is member of an sac section.
     */
    public function isSacMember(): bool
    {
        $strRoles = $this->get('Roles');

        // Search for NAV_MITGLIED_S00004250 or NAV_MITGLIED_S00004251, etc.
        $pattern = static::NAV_SECTION_ID_REGEX;

        return preg_match($pattern, $strRoles) && !empty($this->get('sub')) && !empty($this->get('contact_number'));
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

    /**
     * Return all sac sections ids a remote user belongs to.
     */
    private function getSacSectionIds(): array
    {
        $strRoles = (string) $this->get('Roles');

        if (empty($strRoles)) {
            return [];
        }

        // Search for NAV_MITGLIED_S00004250 or NAV_MITGLIED_S00004251, etc.
        $pattern = static::NAV_SECTION_ID_REGEX;

        return preg_match_all($pattern, $strRoles, $matches) ? array_unique(array_map('intval', $matches[1])) : [];
    }

    /**
     * @throws \Exception
     */
    private function setContaoScope(string $contaoScope): void
    {
        if (AuthenticationController::CONTAO_SCOPE_FRONTEND !== $contaoScope && AuthenticationController::CONTAO_SCOPE_BACKEND !== $contaoScope) {
            throw new \Exception('Scope should be either "backend" or "frontend".');
        }
        $this->contaoScope = $contaoScope;
    }
}
