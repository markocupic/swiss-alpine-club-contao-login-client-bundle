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

use Contao\BackendUser;
use Contao\CoreBundle\ContaoCoreBundle;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FrontendUser;
use Contao\MemberModel;
use Contao\Model;
use Contao\StringUtil;
use Contao\System;
use Contao\UserModel;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\ErrorMessage\ErrorMessage;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\ErrorMessage\ErrorMessageManager;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Provider\SwissAlpineClubResourceOwner;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Validator\LoginValidator;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

class ContaoUser
{
    private ContaoFramework $framework;
    private TranslatorInterface $translator;
    private PasswordHasherFactoryInterface $hasherFactory;
    private LoginValidator $loginValidator;
    private ErrorMessageManager $errorMessageManager;
    private string $contaoScope = '';
    private ?SwissAlpineClubResourceOwner $resourceOwner = null;

    public function __construct(ContaoFramework $framework, TranslatorInterface $translator, PasswordHasherFactoryInterface $hasherFactory, LoginValidator $loginValidator, ErrorMessageManager $errorMessageManager)
    {
        $this->framework = $framework;
        $this->translator = $translator;
        $this->hasherFactory = $hasherFactory;
        $this->loginValidator = $loginValidator;
        $this->errorMessageManager = $errorMessageManager;
    }

    /**
     * This is the first method that has to be called
     *
     * @throws \Exception
     */
    public function createFromResourceOwner(SwissAlpineClubResourceOwner $resourceOwner, string $scope): void
    {
        $this->resourceOwner = $resourceOwner;
        $this->setContaoScope($scope);
    }

    public function getResourceOwner(): ?SwissAlpineClubResourceOwner
    {
        return $this->resourceOwner;
    }

    /**
     * @throws \Exception
     */
    public function getContaoScope(): ?string
    {
        if (empty($this->contaoScope)) {
            throw new \Exception('No Contao scope has been set.');
        }

        return $this->contaoScope;
    }

    /**
     * @throws \Exception
     */
    public function getModel(string $strTable = ''): ?Model
    {

        if ('tl_member' === $strTable || ContaoCoreBundle::SCOPE_FRONTEND === $this->getContaoScope()) {
            /** @var MemberModel $memberModelAdapter */
            $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);

            return $memberModelAdapter->findByUsername($this->resourceOwner->getSacMemberId());
        }

        if ('tl_user' === $strTable || ContaoCoreBundle::SCOPE_BACKEND === $this->getContaoScope()) {
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

            // Update member details from JSON payload
            $objMember->mobile = $this->beautifyPhoneNumber($this->resourceOwner->getPhoneMobile());
            $objMember->phone = $this->beautifyPhoneNumber($this->resourceOwner->getPhonePrivate());
            $objMember->uuid = $this->resourceOwner->getId();
            $objMember->lastname = $this->resourceOwner->getLastName();
            $objMember->firstname = $this->resourceOwner->getFirstName();
            $objMember->street = $this->resourceOwner->getStreet();
            $objMember->city = $this->resourceOwner->getCity();
            $objMember->postal = $this->resourceOwner->getPostal();
            $objMember->dateOfBirth = false !== strtotime($this->resourceOwner->getDateOfBirth()) ? strtotime($this->resourceOwner->getDateOfBirth()) : 0;
            $objMember->gender = 'HERR' === $this->resourceOwner->getSalutation() ? 'male' : 'female';
            $objMember->country = strtolower($this->resourceOwner->getCountryCode());
            $objMember->email = $this->resourceOwner->getEmail();

            // Update SAC section membership from JSON payload
            $objMember->sectionId = serialize($this->loginValidator->getAllowedSacSectionIds($this->resourceOwner));

            // Member has to be member of a valid SAC section
            if ($systemAdapter->getContainer()->getParameter('sac_oauth2_client.oidc.allow_frontend_login_to_predefined_section_members_only')) {
                $objMember->isSacMember = !empty($this->loginValidator->getAllowedSacSectionIds($this->resourceOwner)) ? '1' : '';
            } else {
                $objMember->isSacMember = $this->loginValidator->isSacMember($this->resourceOwner) ? '1' : '';
            }

            $objMember->tstamp = time();

            // Add member groups
            $arrGroups = $stringUtilAdapter->deserialize($objMember->groups, true);
            $arrAutoGroups = $systemAdapter->getContainer()->getParameter('sac_oauth2_client.oidc.add_to_frontend_user_groups');

            if (!empty($arrAutoGroups) && \is_array($arrAutoGroups)) {

                foreach ($arrAutoGroups as $groupId) {
                    if (!in_array($groupId, $arrGroups, false)) {
                        $arrGroups[] = $groupId;
                    }
                }

                $objMember->groups = serialize($arrGroups);

            }

            // Set random password
            if (empty($objMember->password)) {
                $encoder = $this->hasherFactory->getPasswordHasher(FrontendUser::class);
                $objMember->password = $encoder->hash(substr(md5((string)random_int(900009, 111111111111)), 0, 8), null);
            }

            // Save
            $objMember->save();

            $objMember->refresh();
        }
    }

    /**
     * @throws \Exception
     */
    public function updateBackendUser(): void
    {

        $objUser = $this->getModel('tl_user');

        if (null !== $objUser) {
            $objUser->mobile = $this->beautifyPhoneNumber($this->resourceOwner->getPhoneMobile());
            $objUser->phone = $this->beautifyPhoneNumber($this->resourceOwner->getPhonePrivate());
            $objUser->uuid = $this->resourceOwner->getId();
            $objUser->lastname = $this->resourceOwner->getLastName();
            $objUser->firstname = $this->resourceOwner->getFirstName();
            $objUser->name = $this->resourceOwner->getFullName();
            $objUser->street = $this->resourceOwner->getStreet();
            $objUser->city = $this->resourceOwner->getCity();
            $objUser->postal = $this->resourceOwner->getPostal();
            $objUser->dateOfBirth = false !== strtotime($this->resourceOwner->getDateOfBirth()) ? strtotime($this->resourceOwner->getDateOfBirth()) : 0;
            $objUser->gender = 'HERR' === $this->resourceOwner->getSalutation() ? 'male' : 'female';
            $objUser->country = strtolower($this->resourceOwner->getCountryCode());
            $objUser->email = $this->resourceOwner->getEmail();
            $objUser->sectionId = serialize($this->loginValidator->getAllowedSacSectionIds($this->resourceOwner));
            $objUser->tstamp = time();

            // Set random password
            if (empty($objUser->password)) {
                $passwordHasher = $this->hasherFactory->getPasswordHasher(BackendUser::class);
                $objUser->password = $passwordHasher->hash(substr(md5((string)random_int(900009, 111111111111)), 0, 8), null);
            }

            // Save
            $objUser->save();

            $objUser->refresh();
        }
    }

    /**
     * @param $username
     * @return bool
     */
    public function isValidUsername($username): bool
    {
        if (!\is_string($username) && (!\is_object($username) || !method_exists($username, '__toString'))) {
            return false;
        }

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
    private function setContaoScope(string $scope): void
    {
        $arrAllowedScopes = [
            ContaoCoreBundle::SCOPE_FRONTEND,
            ContaoCoreBundle::SCOPE_BACKEND,
        ];

        if (!\in_array(strtolower($scope), $arrAllowedScopes, true)) {
            throw new \Exception('Parameter "$scope" should be either "frontend" or "backend".');
        }

        $this->contaoScope = strtolower($scope);
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
            $objNew = new MemberModel();
            $objNew->username = $sacMemberId;
            $objNew->sacMemberId = $sacMemberId;
            $objNew->uuid = $this->resourceOwner->getId();
            $objNew->dateAdded = time();
            $objNew->tstamp = time();
            $objNew->save();
            $this->updateFrontendUser();
        }
    }
}
