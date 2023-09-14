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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\User;

use Contao\BackendUser;
use Contao\CoreBundle\ContaoCoreBundle;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FrontendUser;
use Contao\MemberModel;
use Contao\StringUtil;
use Contao\System;
use Contao\UserModel;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\DataContainer\Util;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\ErrorMessage\ErrorMessage;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\ErrorMessage\ErrorMessageManager;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\OAuth\OAuthUser;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\OAuth\OAuthUserChecker;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

class ContaoUser
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Connection $connection,
        private readonly TranslatorInterface $translator,
        private readonly PasswordHasherFactoryInterface $hasherFactory,
        private readonly OAuthUserChecker $resourceOwnerChecker,
        private readonly ErrorMessageManager $errorMessageManager,
        private readonly OAuthUser $resourceOwner,
        private readonly Util $util,
        private readonly string $contaoScope,
    ) {
    }

    public function getResourceOwner(): OAuthUser
    {
        return $this->resourceOwner;
    }

    public function getContaoScope(): string
    {
        return $this->contaoScope;
    }

    public function getIdentifier(): string|null
    {
        $model = $this->getModel();

        return $model?->username;
    }

    /**
     * @throws \Exception
     */
    public function getModel(string $strTable = ''): MemberModel|UserModel|null
    {
        if ('' === $strTable) {
            if (ContaoCoreBundle::SCOPE_FRONTEND === $this->getContaoScope()) {
                $strTable = 'tl_member';
            } elseif (ContaoCoreBundle::SCOPE_BACKEND === $this->getContaoScope()) {
                $strTable = 'tl_user';
            }
        }

        if ('tl_member' === $strTable) {
            /** @var MemberModel $memberModelAdapter */
            $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);

            return $memberModelAdapter->findByUsername($this->resourceOwner->getSacMemberId());
        }

        if ('tl_user' === $strTable) {
            /** @var UserModel $userModelAdapter */
            $userModelAdapter = $this->framework->getAdapter(UserModel::class);

            return $userModelAdapter->findOneBySacMemberId($this->resourceOwner->getSacMemberId());
        }

        return null;
    }

    /**
     * @throws \Exception
     */
    public function createIfNotExists(): void
    {
        if (ContaoCoreBundle::SCOPE_FRONTEND === $this->getContaoScope()) {
            $this->createFrontendUserIfNotExists();
        }

        if (ContaoCoreBundle::SCOPE_BACKEND === $this->getContaoScope()) {
            throw new \Exception('Auto-Creating Backend User is not allowed.');
        }
    }

    /**
     * @throws \Exception
     */
    public function checkUserExists(): bool
    {
        if (empty($this->resourceOwner->getSacMemberId()) || !$this->userExists()) {
            if (ContaoCoreBundle::SCOPE_FRONTEND === $this->getContaoScope()) {
                $this->errorMessageManager->add2Flash(
                    new ErrorMessage(
                        ErrorMessage::LEVEL_WARNING,
                        $this->translator->trans('ERR.sacOidcLoginError_userDoesNotExist_matter', [$this->resourceOwner->getFirstName()], 'contao_default'),
                        $this->translator->trans('ERR.sacOidcLoginError_userDoesNotExist_howToFix', [], 'contao_default'),
                        $this->translator->trans('ERR.sacOidcLoginError_userDoesNotExist_explain', [], 'contao_default'),
                    )
                );
            } else {
                $this->errorMessageManager->add2Flash(
                    new ErrorMessage(
                        ErrorMessage::LEVEL_WARNING,
                        $this->translator->trans('ERR.sacOidcLoginError_backendUserNotFound_matter', [$this->resourceOwner->getFirstName()], 'contao_default'),
                    )
                );
            }

            return false;
        }

        return true;
    }

    /**
     * @throws \Exception
     */
    public function userExists(): bool
    {
        if (null !== $this->getModel()) {
            return true;
        }

        return false;
    }

    /**
     * @throws \Exception
     */
    public function checkIsAccountEnabled(): bool
    {
        if (($model = $this->getModel()) !== null) {
            if (ContaoCoreBundle::SCOPE_FRONTEND === $this->getContaoScope()) {
                $disabled = $model->disable || ('' !== $model->start && $model->start > time()) || ('' !== $model->stop && $model->stop <= time());

                if (!$disabled) {
                    return true;
                }
            }

            if (ContaoCoreBundle::SCOPE_BACKEND === $this->getContaoScope()) {
                $disabled = $model->disable || ('' !== $model->start && $model->start > time()) || ('' !== $model->stop && $model->stop <= time());

                if (!$disabled) {
                    return true;
                }
            }
        }

        $this->errorMessageManager->add2Flash(
            new ErrorMessage(
                ErrorMessage::LEVEL_WARNING,
                $this->translator->trans('ERR.sacOidcLoginError_accountDisabled_matter', [$this->resourceOwner->getFirstName()], 'contao_default'),
                '',
                $this->translator->trans('ERR.sacOidcLoginError_accountDisabled_explain', [], 'contao_default'),
            )
        );

        return false;
    }

    /**
     * @throws \Exception
     */
    public function updateFrontendUser(): void
    {
        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        $objMember = $this->getModel('tl_member');

        if (null !== $objMember) {
            // Correctly format the section ids (the key is important!): e.g. [0 => '4250', 2 => '4252'] -> user is member of two SAC Sektionen/Ortsgruppen
            $arrSectionIdsUserIsAllowed = array_map('strval', $this->resourceOwnerChecker->getAllowedSacSectionIds($this->resourceOwner, ContaoCoreBundle::SCOPE_FRONTEND));
            $arrSectionIdsAll = array_map('strval', array_keys($this->util->listSacSections()));
            $arrSectionIds = array_filter($arrSectionIdsAll, static fn ($v, $k) => \in_array($v, $arrSectionIdsUserIsAllowed, true), ARRAY_FILTER_USE_BOTH);

            // Update member details from JSON payload
            $set = [
                // Be sure to set the correct data type!
                // Otherwise, the record will be updated
                // due to wrong type cast only.
                'mobile' => $this->beautifyPhoneNumber($this->resourceOwner->getPhoneMobile()),
                'phone' => $this->beautifyPhoneNumber($this->resourceOwner->getPhonePrivate()),
                'uuid' => $this->resourceOwner->getId(),
                'lastname' => $this->resourceOwner->getLastName(),
                'firstname' => $this->resourceOwner->getFirstName(),
                'street' => $this->resourceOwner->getStreet(),
                'city' => $this->resourceOwner->getCity(),
                'postal' => $this->resourceOwner->getPostal(),
                'dateOfBirth' => false !== strtotime($this->resourceOwner->getDateOfBirth()) ? (string) strtotime($this->resourceOwner->getDateOfBirth()) : 0,
                'gender' => 'HERR' === $this->resourceOwner->getSalutation() ? 'male' : 'female',
                'email' => $this->resourceOwner->getEmail(),
                'sectionId' => serialize($arrSectionIds),
            ];

            // Member has to be member of a valid SAC section
            if ($systemAdapter->getContainer()->getParameter('sac_oauth2_client.oidc.allow_frontend_login_to_predefined_section_members_only')) {
                $set['isSacMember'] = !empty($this->resourceOwnerChecker->getAllowedSacSectionIds($this->resourceOwner, ContaoCoreBundle::SCOPE_FRONTEND)) ? '1' : '';
            } else {
                $set['isSacMember'] = $this->resourceOwnerChecker->isSacMember($this->resourceOwner) ? '1' : '';
            }

            // Add member groups
            $arrGroups = $stringUtilAdapter->deserialize($objMember->groups, true);
            $arrAutoGroups = $systemAdapter->getContainer()->getParameter('sac_oauth2_client.oidc.add_to_frontend_user_groups');

            if (!empty($arrAutoGroups) && \is_array($arrAutoGroups)) {
                foreach ($arrAutoGroups as $groupId) {
                    if (!\in_array($groupId, $arrGroups, false)) {
                        $arrGroups[] = $groupId;
                    }
                }

                $set['`groups`'] = serialize($arrGroups);
            }

            // Set random password
            if (empty($objMember->password)) {
                $encoder = $this->hasherFactory->getPasswordHasher(FrontendUser::class);
                $set['password'] = $encoder->hash(substr(md5((string) random_int(900009, 111111111111)), 0, 8), null);
            }

            if ($this->connection->update('tl_member', $set, ['id' => $objMember->id])) {
                $set = [
                    'tstamp' => time(),
                ];

                $this->connection->update('tl_member', $set, ['id' => $objMember->id]);

                $objMember->refresh();
            }
        }
    }

    /**
     * @throws \Exception
     */
    public function updateBackendUser(): void
    {
        $objUser = $this->getModel('tl_user');

        if (null !== $objUser) {
            // Correctly format the section ids (the key is important!): e.g. [0 => '4250', 2 => '4252'] -> user is member of two SAC Sektionen/Ortsgruppen
            $arrSectionIdsUserIsAllowed = array_map('strval', $this->resourceOwnerChecker->getAllowedSacSectionIds($this->resourceOwner, ContaoCoreBundle::SCOPE_BACKEND));
            $arrSectionIdsAll = array_map('strval', array_keys($this->util->listSacSections()));
            $arrSectionIds = array_filter($arrSectionIdsAll, static fn ($v, $k) => \in_array($v, $arrSectionIdsUserIsAllowed, true), ARRAY_FILTER_USE_BOTH);

            $set = [
                // Be sure to set the correct data type!
                // Otherwise, the record will be updated
                // due to wrong type cast only.
                'mobile' => $this->beautifyPhoneNumber($this->resourceOwner->getPhoneMobile()),
                'phone' => $this->beautifyPhoneNumber($this->resourceOwner->getPhonePrivate()),
                'uuid' => $this->resourceOwner->getId(),
                'lastname' => $this->resourceOwner->getLastName(),
                'firstname' => $this->resourceOwner->getFirstName(),
                'name' => $this->resourceOwner->getFullName(),
                'street' => $this->resourceOwner->getStreet(),
                'city' => $this->resourceOwner->getCity(),
                'postal' => $this->resourceOwner->getPostal(),
                'dateOfBirth' => false !== strtotime($this->resourceOwner->getDateOfBirth()) ? (string) strtotime($this->resourceOwner->getDateOfBirth()) : '0',
                'gender' => 'HERR' === $this->resourceOwner->getSalutation() ? 'male' : 'female',
                'email' => $this->resourceOwner->getEmail(),
                'sectionId' => serialize($arrSectionIds),
            ];

            // Set random password
            if (empty($objUser->password)) {
                $encoder = $this->hasherFactory->getPasswordHasher(BackendUser::class);
                $set['password'] = $encoder->hash(substr(md5((string) random_int(900009, 111111111111)), 0, 8), null);
            }

            if ($this->connection->update('tl_user', $set, ['id' => $objUser->id])) {
                $set = [
                    'tstamp' => time(),
                ];

                $this->connection->update('tl_user', $set, ['id' => $objUser->id]);

                $objUser->refresh();
            }
        }
    }

    public function isValidUsername(string $username): bool
    {
        $username = trim($username);

        // Check if username is valid
        // Security::MAX_USERNAME_LENGTH = 4096;
        if (\strlen($username) > Security::MAX_USERNAME_LENGTH) {
            return false;
        }

        return true;
    }

    /**
     * @throws \Exception
     */
    public function enableLogin(): void
    {
        if (($model = $this->getModel()) !== null) {
            $model->disable = '';
            $model->save();
            $model->refresh();
        }
    }

    /**
     * @throws \Exception
     */
    public function activateMemberAccount(): void
    {
        if (ContaoCoreBundle::SCOPE_FRONTEND !== $this->getContaoScope()) {
            return;
        }

        if (($model = $this->getModel()) !== null) {
            $model->login = '1';
            $model->save();
            $model->refresh();
        }
    }

    /**
     * @throws \Exception
     */
    public function unlock(): void
    {
        if (($model = $this->getModel()) !== null) {
            $model->locked = 0;
            $model->save();
            $model->refresh();
        }
    }

    /**
     * @throws \Exception
     */
    public function resetLoginAttempts(): void
    {
        if (($model = $this->getModel()) !== null) {
            $model->loginAttempts = 0;
            $model->save();
            $model->refresh();
        }
    }

    public static function beautifyPhoneNumber(string $strNumber = ''): string
    {
        if ('' !== $strNumber) {
            // Remove whitespaces
            $strNumber = preg_replace('/\s+/', '', $strNumber);
            // Remove country code
            $strNumber = str_replace('+41', '', $strNumber);
            $strNumber = str_replace('0041', '', $strNumber);

            // Add a leading zero, if there is no f.ex 41
            if (!str_starts_with($strNumber, '0') && 9 === \strlen($strNumber)) {
                $strNumber = '0'.$strNumber;
            }

            // Search for 0799871234 and replace it with 079 987 12 34
            $pattern = '/^([0]{1})([0-9]{2})([0-9]{3})([0-9]{2})([0-9]{2})$/';

            if (preg_match($pattern, $strNumber)) {
                $replace = '$1$2 $3 $4 $5';
                $strNumber = preg_replace($pattern, $replace, $strNumber);
            }
        }

        return $strNumber;
    }

    /**
     * @throws \Exception
     */
    private function createFrontendUserIfNotExists(): void
    {
        $sacMemberId = $this->resourceOwner->getSacMemberId();

        if (!$this->isValidUsername($sacMemberId)) {
            return;
        }

        if (null === $this->getModel('tl_member')) {
            $set = [
                'username' => $sacMemberId,
                'sacMemberId' => $sacMemberId,
                'uuid' => $this->resourceOwner->getId(),
                'dateAdded' => time(),
                'tstamp' => $sacMemberId,
            ];

            $this->connection->insert('tl_member', $set);

            $this->updateFrontendUser();
        }
    }
}
