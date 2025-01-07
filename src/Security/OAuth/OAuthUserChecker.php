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

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\System;
use Contao\Validator;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class OAuthUserChecker
{
    public function __construct(
        private ContaoFramework $framework,
        #[Autowire('%sac_oauth2_client.oidc.allowed_frontend_sac_section_ids%')]
        private array $allowedFrontendSacSectionIds,
        #[Autowire('%sac_oauth2_client.oidc.allowed_frontend_roles%')]
        private array $allowedFrontendRoles,
        #[Autowire('%sac_oauth2_client.oidc.allowed_backend_roles%')]
        private array $allowedBackendRoles,
        #[Autowire('%sac_oauth2_client.oidc.allowed_backend_sac_section_ids%')]
        private array $allowedBackendSacSectionIds,
    ) {
    }

    /**
     * Check if OAuth user has a valid uuid/sub.
     *
     * @param OAuthUser $oAuthUser
     */
    public function checkHasSacMemberId(ResourceOwnerInterface $oAuthUser): bool
    {
        /** @var System $systemAdapter */
        if (empty($oAuthUser->getId())) {
            return false;
        }

        return true;
    }

    /**
     * Check if OAuth user is a SAC member.
     *
     * @param OAuthUser $oAuthUser
     */
    public function checkIsSacMember(ResourceOwnerInterface $oAuthUser, string $contaoScope): bool
    {
        if (!$this->isSacMember($oAuthUser, $contaoScope)) {
            return false;
        }

        return true;
    }

    /**
     * Check for allowed SAC section membership.
     *
     * @param OAuthUser $oAuthUser
     */
    public function checkIsMemberOfAllowedSection(ResourceOwnerInterface $oAuthUser, string $contaoScope): bool
    {
        $arrMembership = $this->getAllowedSacSectionIds($oAuthUser, $contaoScope);

        if (\count($arrMembership) > 0) {
            return true;
        }

        return false;
    }

    /**
     * Check if OAuth user has a valid email address.
     *
     * @param OAuthUser $oAuthUser
     */
    public function checkHasValidEmailAddress(ResourceOwnerInterface $oAuthUser): bool
    {
        /** @var Validator $validatorAdapter */
        $validatorAdapter = $this->framework->getAdapter(Validator::class);

        if (empty($oAuthUser->getEmail()) || !$validatorAdapter->isEmail($oAuthUser->getEmail())) {
            return false;
        }

        return true;
    }

    /**
     * Return all allowed SAC section ids a OAuth user belongs to.
     *
     * @param OAuthUser $oAuthUser
     */
    public function getAllowedSacSectionIds(ResourceOwnerInterface $oAuthUser, string $contaoScope): array
    {
        $arrAllowedGroups = match ($contaoScope) {
            ContaoCoreBundle::SCOPE_FRONTEND => $this->allowedFrontendSacSectionIds,
            ContaoCoreBundle::SCOPE_BACKEND => $this->allowedBackendSacSectionIds,
            default => [],
        };

        $arrGroupMembership = $this->getSacSectionIds($oAuthUser, $contaoScope);

        return array_unique(array_intersect($arrAllowedGroups, $arrGroupMembership));
    }

    /**
     * Check if OAuth user is member of a SAC section.
     *
     * @param OAuthUser $oAuthUser
     */
    public function isSacMember(ResourceOwnerInterface $oAuthUser, string $contaoScope): bool
    {
        return !empty($oAuthUser->getSectionMembershipIDS($this->getAllowedRoles($contaoScope)));
    }

    /**
     * Return all SAC section ids a OAuth user belongs to.
     *
     * @param OAuthUser $oAuthUser
     */
    private function getSacSectionIds(ResourceOwnerInterface $oAuthUser, string $contaoScope): array
    {
        return $oAuthUser->getSectionMembershipIDS($this->getAllowedRoles($contaoScope));
    }

    private function getAllowedRoles(string $contaoScope): array
    {
        return match ($contaoScope) {
            ContaoCoreBundle::SCOPE_FRONTEND => $this->allowedFrontendRoles,
            ContaoCoreBundle::SCOPE_BACKEND => $this->allowedBackendRoles,
            default => throw new \Exception('Invalid contao scope detected. Scope must be either "frontend" or "backend".'),
        };
    }
}
