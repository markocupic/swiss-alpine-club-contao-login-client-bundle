<?php

declare(strict_types=1);

/*
 * This file is part of Swiss Alpine Club Contao Login Client Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/swiss-alpine-club-contao-login-client-bundle
 */

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\OAuth;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\System;
use Contao\Validator;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\ErrorMessage\ErrorMessage;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\ErrorMessage\ErrorMessageManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;

class OAuthUserChecker
{
    /**
     * NAVISION section id regex.
     */
    public const NAV_SECTION_ID_REGEX = '/NAV_MITGLIED_S(\d+)/';

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly ErrorMessageManager $errorMessageManager,
        private readonly TranslatorInterface $translator,
        #[Autowire('%sac_oauth2_client.oidc.allowed_frontend_sac_section_ids%')]
        private readonly array $allowedFrontendSacSectionIds,
        #[Autowire('%sac_oauth2_client.oidc.allowed_backend_sac_section_ids%')]
        private readonly array $allowedBackendSacSectionIds,
    ) {
    }

    /**
     * Check if OAuth user has a valid uuid/sub.
     */
    public function checkHasUuid(ResourceOwnerInterface $oAuthUser): bool
    {
        /** @var System $systemAdapter */
        if (empty($oAuthUser->getId())) {
            $this->errorMessageManager->add2Flash(
                new ErrorMessage(
                    ErrorMessage::LEVEL_WARNING,
                    $this->translator->trans('ERR.sacOidcLoginError_resourceOwnerHasInvalidUuid_matter', [], 'contao_default'),
                    $this->translator->trans('ERR.sacOidcLoginError_resourceOwnerHasInvalidUuid_howToFix', [], 'contao_default'),
                )
            );

            return false;
        }

        return true;
    }

    /**
     * Check if OAuth user is a SAC member.
     */
    public function checkIsSacMember(ResourceOwnerInterface $oAuthUser): bool
    {
        if (!$this->isSacMember($oAuthUser)) {
            $this->errorMessageManager->add2Flash(
                new ErrorMessage(
                    ErrorMessage::LEVEL_WARNING,
                    $this->translator->trans('ERR.sacOidcLoginError_missingSacMembership_matter', [$oAuthUser->getFirstName()], 'contao_default'),
                    $this->translator->trans('ERR.sacOidcLoginError_missingSacMembership_howToFix', [], 'contao_default'),
                )
            );

            return false;
        }

        return true;
    }

    /**
     * Check for allowed SAC section membership.
     */
    public function checkIsMemberOfAllowedSection(ResourceOwnerInterface $oAuthUser, string $contaoScope): bool
    {
        $arrMembership = $this->getAllowedSacSectionIds($oAuthUser, $contaoScope);

        if (\count($arrMembership) > 0) {
            return true;
        }

        $this->errorMessageManager->add2Flash(
            new ErrorMessage(
                ErrorMessage::LEVEL_WARNING,
                $this->translator->trans('ERR.sacOidcLoginError_notMemberOfAllowedSection_matter', [$oAuthUser->getFirstName()], 'contao_default'),
                $this->translator->trans('ERR.sacOidcLoginError_notMemberOfAllowedSection_howToFix', [], 'contao_default'),
            )
        );

        return false;
    }

    /**
     * Check if OAuth user has a valid email address.
     */
    public function checkHasValidEmailAddress(ResourceOwnerInterface $oAuthUser): bool
    {
        /** @var Validator $validatorAdapter */
        $validatorAdapter = $this->framework->getAdapter(Validator::class);

        if (empty($oAuthUser->getEmail()) || !$validatorAdapter->isEmail($oAuthUser->getEmail())) {
            $this->errorMessageManager->add2Flash(
                new ErrorMessage(
                    ErrorMessage::LEVEL_WARNING,
                    $this->translator->trans('ERR.sacOidcLoginError_resourceOwnerHasInvalidEmail_matter', [$oAuthUser->getFirstName()], 'contao_default'),
                    $this->translator->trans('ERR.sacOidcLoginError_resourceOwnerHasInvalidEmail_howToFix', [], 'contao_default'),
                    $this->translator->trans('ERR.sacOidcLoginError_resourceOwnerHasInvalidEmail_explain', [], 'contao_default'),
                )
            );

            return false;
        }

        return true;
    }

    /**
     * Return all allowed SAC section ids a OAuth user belongs to.
     */
    public function getAllowedSacSectionIds(ResourceOwnerInterface $oAuthUser, string $contaoScope): array
    {
        $arrAllowedGroups = match ($contaoScope) {
            ContaoCoreBundle::SCOPE_FRONTEND => $this->allowedFrontendSacSectionIds,
            default => $this->allowedBackendSacSectionIds,
        };

        $arrGroupMembership = $this->getSacSectionIds($oAuthUser);

        return array_unique(array_intersect($arrAllowedGroups, $arrGroupMembership));
    }

    /**
     * Check if OAuth user is member of a SAC section.
     */
    public function isSacMember(ResourceOwnerInterface $oAuthUser): bool
    {
        $strRoles = $oAuthUser->getRolesAsString();

        // Search for NAV_MITGLIED_S00004250 or NAV_MITGLIED_S00004251, etc.
        $pattern = static::NAV_SECTION_ID_REGEX;

        return preg_match($pattern, $strRoles) && !empty($oAuthUser->getId()) && !empty($oAuthUser->getSacMemberId());
    }

    /**
     * Return all SAC section ids a OAuth user belongs to.
     */
    private function getSacSectionIds(ResourceOwnerInterface $oAuthUser): array
    {
        $strRoles = $oAuthUser->getRolesAsString();

        if (empty($strRoles)) {
            return [];
        }

        // Search for NAV_MITGLIED_S00004250 or NAV_MITGLIED_S00004251, etc.
        $pattern = static::NAV_SECTION_ID_REGEX;

        return preg_match_all($pattern, $strRoles, $matches) ? array_unique(array_map('intval', $matches[1])) : [];
    }
}
