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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Validator;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\System;
use Contao\Validator;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\ErrorMessage\ErrorMessage;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\ErrorMessage\ErrorMessageManager;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Provider\SwissAlpineClubResourceOwner;
use Symfony\Contracts\Translation\TranslatorInterface;

class LoginValidator
{
    /**
     * NAVISION section id regex.
     */
    public const NAV_SECTION_ID_REGEX = '/NAV_MITGLIED_S(\d+)/';

    private string $contaoScope = 'frontend';

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly TranslatorInterface $translator,
        private readonly ErrorMessageManager $errorMessageManager,
    ) {
    }

    /**
     * Check if resource owner has a valid uuid/sub.
     */
    public function checkHasUuid(SwissAlpineClubResourceOwner $resourceOwner): bool
    {
        /** @var System $systemAdapter */
        if (empty($resourceOwner->getId())) {
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
     * Check if resource owner is a SAC member.
     */
    public function checkIsSacMember(SwissAlpineClubResourceOwner $resourceOwner): bool
    {
        if (!$this->isSacMember($resourceOwner)) {
            $this->errorMessageManager->add2Flash(
                new ErrorMessage(
                    ErrorMessage::LEVEL_WARNING,
                    $this->translator->trans('ERR.sacOidcLoginError_userIsNotSacMember_matter', [$resourceOwner->getFirstName()], 'contao_default'),
                    $this->translator->trans('ERR.sacOidcLoginError_userIsNotSacMember_howToFix', [], 'contao_default'),
                )
            );

            return false;
        }

        return true;
    }

    /**
     * Check for allowed SAC section membership.
     */
    public function checkIsMemberOfAllowedSection(SwissAlpineClubResourceOwner $resourceOwner): bool
    {
        $arrMembership = $this->getAllowedSacSectionIds($resourceOwner);

        if (\count($arrMembership) > 0) {
            return true;
        }

        $this->errorMessageManager->add2Flash(
            new ErrorMessage(
                ErrorMessage::LEVEL_WARNING,
                $this->translator->trans('ERR.sacOidcLoginError_userIsNotMemberOfAllowedSection_matter', [$resourceOwner->getFirstName()], 'contao_default'),
                $this->translator->trans('ERR.sacOidcLoginError_userIsNotMemberOfAllowedSection_howToFix', [], 'contao_default'),
            )
        );

        return false;
    }

    /**
     * Check if resource owner has a valid email address.
     */
    public function checkHasValidEmailAddress(SwissAlpineClubResourceOwner $resourceOwner): bool
    {
        /** @var Validator $validatorAdapter */
        $validatorAdapter = $this->framework->getAdapter(Validator::class);

        if (empty($resourceOwner->getEmail()) || !$validatorAdapter->isEmail($resourceOwner->getEmail())) {
            $this->errorMessageManager->add2Flash(
                new ErrorMessage(
                    ErrorMessage::LEVEL_WARNING,
                    $this->translator->trans('ERR.sacOidcLoginError_invalidEmail_matter', [$resourceOwner->getFirstName()], 'contao_default'),
                    $this->translator->trans('ERR.sacOidcLoginError_invalidEmail_howToFix', [], 'contao_default'),
                    $this->translator->trans('ERR.sacOidcLoginError_invalidEmail_explain', [], 'contao_default'),
                )
            );

            return false;
        }

        return true;
    }

    /**
     * Return all allowed SAC section ids a resource owner belongs to.
     */
    public function getAllowedSacSectionIds(SwissAlpineClubResourceOwner $resourceOwner): array
    {
        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        if (ContaoCoreBundle::SCOPE_FRONTEND === $this->contaoScope) {
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

        $arrGroupMembership = $this->getSacSectionIds($resourceOwner);

        return array_unique(array_intersect($arrAllowedGroups, $arrGroupMembership));
    }

    /**
     * Check if user is member of a SAC section.
     */
    public function isSacMember(SwissAlpineClubResourceOwner $resourceOwner): bool
    {
        $strRoles = $resourceOwner->getRolesAsString();

        // Search for NAV_MITGLIED_S00004250 or NAV_MITGLIED_S00004251, etc.
        $pattern = static::NAV_SECTION_ID_REGEX;

        return preg_match($pattern, $strRoles) && !empty($resourceOwner->getId()) && !empty($resourceOwner->getSacMemberId());
    }

    /**
     * @throws \Exception
     */
    public function setContaoScope(string $contaoScope): void
    {
        if (ContaoCoreBundle::SCOPE_FRONTEND !== $contaoScope && ContaoCoreBundle::SCOPE_BACKEND !== $contaoScope) {
            throw new \Exception('Scope should be either "backend" or "frontend".');
        }
        $this->contaoScope = $contaoScope;
    }

    /**
     * Return all SAC section ids a resource owner belongs to.
     */
    private function getSacSectionIds(SwissAlpineClubResourceOwner $resourceOwner): array
    {
        $strRoles = $resourceOwner->getRolesAsString();

        if (empty($strRoles)) {
            return [];
        }

        // Search for NAV_MITGLIED_S00004250 or NAV_MITGLIED_S00004251, etc.
        $pattern = static::NAV_SECTION_ID_REGEX;

        return preg_match_all($pattern, $strRoles, $matches) ? array_unique(array_map('intval', $matches[1])) : [];
    }
}
