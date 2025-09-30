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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\User;

use Contao\BackendUser;
use Contao\CoreBundle\ContaoCoreBundle;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FrontendUser;
use Contao\MemberModel;
use Contao\StringUtil;
use Contao\UserModel;
use Doctrine\DBAL\Connection;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Markocupic\SacEventToolBundle\DataContainer\Util;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\OAuth\OAuthUser;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\OAuth\OAuthUserChecker;
use Random\Randomizer;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

readonly class ContaoUser
{
    public function __construct(
        private ContaoFramework $framework,
        private Connection $connection,
        private PasswordHasherFactoryInterface $hasherFactory,
        private OAuthUserChecker $oauthUserChecker,
        private ResourceOwnerInterface $resourceOwner,
        private Util $util,
        private string $contaoScope,
        private bool $allowFrontendLoginToPredefinedSectionMembersOnly,
        private array $addToFrontendUserGroups,
    ) {
    }

    /**
     * @return OAuthUser
     */
    public function getResourceOwner(): ResourceOwnerInterface
    {
        return $this->resourceOwner;
    }

    public function getContaoScope(): string
    {
        return $this->contaoScope;
    }

    /**
     * @throws \Exception
     */
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

            return $memberModelAdapter->findOneByUsername($this->getResourceOwner()->getSacMemberId());
        }

        if ('tl_user' === $strTable) {
            /** @var UserModel $userModelAdapter */
            $userModelAdapter = $this->framework->getAdapter(UserModel::class);

            return $userModelAdapter->findOneBySacMemberId($this->getResourceOwner()->getSacMemberId());
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
    public function checkFrontendUserExists(): bool
    {
        if (empty($this->getResourceOwner()->getSacMemberId()) || !$this->userExists()) {
            return false;
        }

        return true;
    }

    /**
     * @throws \Exception
     */
    public function checkBackendUserExists(): bool
    {
        if (empty($this->getResourceOwner()->getSacMemberId()) || !$this->userExists()) {
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
    public function checkFrontendLoginIsEnabled(): bool
    {
        if (ContaoCoreBundle::SCOPE_FRONTEND !== $this->getContaoScope()) {
            throw new \RuntimeException(\sprintf('Scope must be frontend, "%s" given.', $this->getContaoScope()));
        }

        $model = $this->getModel();

        if (null === $model) {
            throw new \RuntimeException('Contao Frontend User model not found.');
        }

        if (!$model->login) {
            return false;
        }

        return true;
    }

    /**
     * @throws \Exception
     */
    public function checkAccountIsNotDisabled(): bool
    {
        $model = $this->getModel();

        if (null === $model) {
            throw new \RuntimeException('Contao User model not found.');
        }

        $disabled = $model->disable || ('' !== $model->start && $model->start > time()) || ('' !== $model->stop && $model->stop <= time());

        if ($disabled) {
            return false;
        }

        return true;
    }

    /**
     * @throws \Exception
     */
    public function updateFrontendUser(): void
    {
        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        $objMember = $this->getModel('tl_member');

        if (null === $objMember) {
            return;
        }

        // Correctly format the section ids (the key is important!): e.g. [0 => '4250', 2
        // => '4252'] -> user is member of two SAC Sektionen/Ortsgruppen
        $arrSectionIdsUserIsAllowed = array_map('strval', $this->oauthUserChecker->getAllowedSacSectionIds($this->getResourceOwner(), ContaoCoreBundle::SCOPE_FRONTEND));
        $arrSectionIdsAll = array_map('strval', array_keys($this->util->listSacSections()));
        $arrSectionIds = array_filter($arrSectionIdsAll, static fn ($v, $k) => \in_array($v, $arrSectionIdsUserIsAllowed, true), ARRAY_FILTER_USE_BOTH);

        // Update member details from JSON payload
        $set = [
            // Be sure to set the correct data type! Otherwise, the record will be
            // updated due to wrong type cast only. mobile' =>
            // $this->beautifyPhoneNumber($this->getResourceOwner()->getPhone()),
            // phone' =>
            // $this->beautifyPhoneNumber($this->getResourceOwner()->getPhone()), uuid'
            // => $this->getResourceOwner()->getId(),
            'lastname' => $this->getResourceOwner()->getLastName(),
            'firstname' => $this->getResourceOwner()->getFirstName(),
            'street' => $this->getResourceOwner()->getStreet(),
            'city' => $this->getResourceOwner()->getCity(),
            'postal' => $this->getResourceOwner()->getPostal(),
            'dateOfBirth' => false !== strtotime($this->getResourceOwner()->getDateOfBirth()) ? (string) strtotime($this->getResourceOwner()->getDateOfBirth()) : 0,
            'gender' => $this->getResourceOwner()->getGender(),
            'email' => $this->getResourceOwner()->getEmail(),
            'sectionId' => serialize($arrSectionIds),
        ];

        // Member has to be member of a valid SAC section
        if ($this->allowFrontendLoginToPredefinedSectionMembersOnly) {
            $set['isSacMember'] = !empty($this->oauthUserChecker->getAllowedSacSectionIds($this->getResourceOwner(), ContaoCoreBundle::SCOPE_FRONTEND)) ? 1 : 0;
        } else {
            $set['isSacMember'] = $this->oauthUserChecker->isSacMember($this->getResourceOwner(), $this->contaoScope) ? 1 : 0;
        }

        // Add member groups
        $arrGroups = $stringUtilAdapter->deserialize($objMember->groups, true);
        $arrAutoGroups = $this->addToFrontendUserGroups;

        if (!empty($arrAutoGroups) && \is_array($arrAutoGroups)) {
            foreach ($arrAutoGroups as $groupId) {
                if (!\in_array($groupId, $arrGroups, false)) {
                    $arrGroups[] = $groupId;
                }
            }

            $set[$this->connection->quoteIdentifier('groups')] = serialize($arrGroups);
        }

        // Set random password
        if (empty($objMember->password)) {
            $encoder = $this->hasherFactory->getPasswordHasher(FrontendUser::class);
            $set['password'] = $encoder->hash($this->generateRandomPassword());
        }

        if ($this->connection->update('tl_member', $set, ['id' => $objMember->id])) {
            $set = [
                'tstamp' => time(),
            ];

            $this->connection->update('tl_member', $set, ['id' => $objMember->id]);

            $objMember->refresh();
        }
    }

    /**
     * @throws \Exception
     */
    public function updateBackendUser(): void
    {
        $objUser = $this->getModel('tl_user');

        if (null === $objUser) {
            return;
        }

        // Correctly format the section ids (the key is important!): e.g. [0 => '4250', 2
        // => '4252'] -> user is member of two SAC Sektionen/Ortsgruppen
        $arrSectionIdsUserIsAllowed = array_map('strval', $this->oauthUserChecker->getAllowedSacSectionIds($this->getResourceOwner(), ContaoCoreBundle::SCOPE_BACKEND));
        $arrSectionIdsAll = array_map('strval', array_keys($this->util->listSacSections()));
        $arrSectionIds = array_filter($arrSectionIdsAll, static fn ($v, $k) => \in_array($v, $arrSectionIdsUserIsAllowed, true), ARRAY_FILTER_USE_BOTH);

        $set = [
            // Be sure to set the correct data type! Otherwise, the record will be updated
            // due to wrong type cast only. mobile' =>
            // $this->beautifyPhoneNumber($this->getResourceOwner()->getPhoneMobile()),
            // phone' =>
            // $this->beautifyPhoneNumber($this->getResourceOwner()->getPhonePrivate()),
            // uuid' => $this->getResourceOwner()->getId(),
            'lastname' => $this->getResourceOwner()->getLastName(),
            'firstname' => $this->getResourceOwner()->getFirstName(),
            'name' => $this->getResourceOwner()->getFullName(),
            'street' => $this->getResourceOwner()->getStreet(),
            'city' => $this->getResourceOwner()->getCity(),
            'postal' => $this->getResourceOwner()->getPostal(),
            'dateOfBirth' => false !== strtotime($this->getResourceOwner()->getDateOfBirth()) ? (string) strtotime($this->getResourceOwner()->getDateOfBirth()) : '0',
            'gender' => $this->getResourceOwner()->getGender(),
            'email' => $this->getResourceOwner()->getEmail(),
            'sectionId' => serialize($arrSectionIds),
        ];

        // Set random password
        if (empty($objUser->password)) {
            $encoder = $this->hasherFactory->getPasswordHasher(BackendUser::class);
            $set['password'] = $encoder->hash($this->generateRandomPassword());
        }

        if ($this->connection->update('tl_user', $set, ['id' => $objUser->id])) {
            $set = [
                'tstamp' => time(),
            ];

            $this->connection->update('tl_user', $set, ['id' => $objUser->id]);

            $objUser->refresh();
        }
    }

    public function isValidUsername(string $username): bool
    {
        $username = trim($username);

        // Check if username is valid Security::MAX_USERNAME_LENGTH = 4096;
        if (\strlen($username) > UserBadge::MAX_USERNAME_LENGTH) {
            return false;
        }

        return true;
    }

    /**
     * @throws \Exception
     */
    public function activateMemberAccount(): void
    {
        if (ContaoCoreBundle::SCOPE_FRONTEND !== $this->getContaoScope()) {
            throw new \RuntimeException(\sprintf('Scope must be frontend, "%s" given.', $this->getContaoScope()));
        }

        if (($model = $this->getModel()) !== null) {
            $model->disable = false;
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
            $strNumber = str_replace(['+41', '0041'], ['', ''], $strNumber);

            // Add a leading zero, if there is no f.ex 41
            if (!str_starts_with($strNumber, '0') && 9 === \strlen($strNumber)) {
                $strNumber = '0'.$strNumber;
            }

            // Search for 0799871234 and replace it with 079 987 12 34
            $pattern = '/^(0)([0-9]{2})([0-9]{3})([0-9]{2})([0-9]{2})$/';

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
        $sacMemberId = $this->getResourceOwner()->getSacMemberId();

        if (!$this->isValidUsername($sacMemberId)) {
            throw new \RuntimeException(\sprintf('Could not create a new Contao Frontend User due to an invalid username "%s".', $sacMemberId));
        }

        if (null === $this->getModel('tl_member')) {
            $set = [
                'username' => $sacMemberId,
                'sacMemberId' => $sacMemberId,
                // 'uuid' => $this->getResourceOwner()->getId(),
                'dateAdded' => time(),
                'tstamp' => time(),
                'login' => true,
            ];

            $this->connection->insert('tl_member', $set);

            $this->updateFrontendUser();
        }
    }

    private function generateRandomPassword(): string
    {
        $randomInt = (new Randomizer())->getInt(1111111111111, 9999999999999);

        return substr(md5((string) $randomInt), 0, 12);
    }
}
