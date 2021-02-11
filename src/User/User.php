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

use Contao\BackendUser;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FrontendUser;
use Contao\MemberModel;
use Contao\Model;
use Contao\ModuleModel;
use Contao\StringUtil;
use Contao\System;
use Contao\UserModel;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * Class User.
 */
class User
{
    /**
     * @var RemoteUser
     */
    public $remoteUser;

    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Session
     */
    private $session;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var string
     */
    private $contaoScope;

    /**
     * User constructor.
     */
    public function __construct(ContaoFramework $framework, Session $session, ?LoggerInterface $logger, TranslatorInterface $translator)
    {
        $this->framework = $framework;
        $this->session = $session;
        $this->logger = $logger;
        $this->translator = $translator;

        // Initialize Contao framework
        $this->framework->initialize();
    }

    /**
     * @throws \Exception
     */
    public function initialize(RemoteUser $remoteUser, string $scope): void
    {
        $this->remoteUser = &$remoteUser;
        $this->setContaoScope($scope);
    }

    /**
     * @throws \Exception
     */
    public function getContaoScope(): ?string
    {
        if (empty($this->contaoScope)) {
            throw new \Exception('No contao scope set.');
        }

        return $this->contaoScope;
    }

    /**
     * @throws \Exception
     */
    public function getModel(string $strTable = ''): ?Model
    {
        /** @var MemberModel $memberModelAdapter */
        $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);

        /** @var UserModel $userModelAdapter */
        $userModelAdapter = $this->framework->getAdapter(UserModel::class);


        if ('tl_member' === $strTable) {
            return $memberModelAdapter->findByUsername($this->remoteUser->get('contact_number'));
        }

        if ('tl_user' === $strTable) {
            return $userModelAdapter->findOneBySacMemberId($this->remoteUser->get('contact_number'));
        }

        if ('frontend' === $this->getContaoScope()) {
            return $memberModelAdapter->findByUsername($this->remoteUser->get('contact_number'));
        }

        if ('backend' === $this->getContaoScope()) {
            return $userModelAdapter->findOneBySacMemberId($this->remoteUser->get('contact_number'));
        }

        return null;
    }

    /**
     * @throws \Exception
     */
    public function createIfNotExists(): void
    {
        if ('frontend' === $this->getContaoScope()) {
            $this->createFrontendUserIfNotExists();
        }

        if ('backend' === $this->getContaoScope()) {
            throw new \Exception('Auto-Creating Backend User is not allowed.');
        }
    }

    /**
     * @throws \Exception
     */
    public function checkUserExists(): void
    {
        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        $arrData = $this->remoteUser->getData();

        if (!isset($arrData) || empty($arrData['contact_number']) || !$this->userExists()) {
            if ('frontend' === $this->getContaoScope()) {
                $arrError = [
                    'level' => 'warning',
                    'matter' => $this->translator->trans('ERR.sacOidcLoginError_userDoesNotExist_matter', [$arrData['vorname']], 'contao_default'),
                    'howToFix' => $this->translator->trans('ERR.sacOidcLoginError_userDoesNotExist_howToFix', [], 'contao_default'),
                    'explain' => $this->translator->trans('ERR.sacOidcLoginError_userDoesNotExist_explain', [], 'contao_default'),
                ];
            } else {
                $arrError = [
                    'level' => 'warning',
                    'matter' => $this->translator->trans('ERR.sacOidcLoginError_backendUserNotFound_matter', [$arrData['vorname']], 'contao_default'),
                    //'howToFix' => $this->translator->trans('ERR.sacOidcLoginError_backendUserNotFound_howToFix', [], 'contao_default'),
                    //'explain'  => $this->translator->trans('ERR.sacOidcLoginError_backendUserNotFound_explain', [], 'contao_default'),
                ];
            }

            $flashBagKey = $systemAdapter->getContainer()->getParameter('swiss_alpine_club_contao_login_client.session.flash_bag_key');
            $this->session->getFlashBag()->add($flashBagKey, $arrError);
            $bagName = $systemAdapter->getContainer()->getParameter('swiss_alpine_club_contao_login_client.session.attribute_bag_name');
            $controllerAdapter->redirect($this->session->getBag($bagName)->get('failurePath'));
        }
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
    public function checkIsLoginAllowed(): void
    {
        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        if (($model = $this->getModel()) !== null) {
            if ('frontend' === $this->getContaoScope()) {
                if ($model->login && !$model->disable && !$model->locked) {
                    return;
                }
            }

            if ('backend' === $this->getContaoScope()) {
                if (!$model->disable && !$model->locked) {
                    return;
                }
            }
        }

        $arrError = [
            'level' => 'warning',
            'matter' => $this->translator->trans('ERR.sacOidcLoginError_accountDisabled_matter', [$this->remoteUser->get('vorname')], 'contao_default'),
            //'howToFix' => $this->translator->trans('ERR.sacOidcLoginError_accountDisabled_howToFix', [], 'contao_default'),
            'explain' => $this->translator->trans('ERR.sacOidcLoginError_accountDisabled_explain', [], 'contao_default'),
        ];
        $flashBagKey = $systemAdapter->getContainer()->getParameter('swiss_alpine_club_contao_login_client.session.flash_bag_key');
        $this->session->getFlashBag()->add($flashBagKey, $arrError);
        $bagName = $systemAdapter->getContainer()->getParameter('swiss_alpine_club_contao_login_client.session.attribute_bag_name');
        $controllerAdapter->redirect($this->session->getBag($bagName)->get('failurePath'));
    }

    /**
     * @throws \Exception
     */
    public function updateFrontendUser(): void
    {
        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        /** @var Config $configAdapter */
        $configAdapter = $this->framework->getAdapter(Config::class);

        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);





        $arrData = $this->remoteUser->getData();

        $objMember = $this->getModel('tl_member');

        if (null !== $objMember) {
            $objMember->mobile = $this->beautifyPhoneNumber($arrData['telefonmobil']);
            $objMember->phone = $this->beautifyPhoneNumber($arrData['telefonp']);
            $objMember->uuid = $arrData['sub'];
            $objMember->lastname = $arrData['familienname'];
            $objMember->firstname = $arrData['vorname'];
            $objMember->street = $arrData['strasse'];
            $objMember->city = $arrData['ort'];
            $objMember->postal = $arrData['plz'];
            $objMember->dateOfBirth = false !== strtotime($arrData['geburtsdatum']) ? strtotime($arrData['geburtsdatum']) : 0;
            $objMember->gender = 'HERR' === $arrData['anredecode'] ? 'male' : 'female';
            $objMember->country = strtolower($arrData['land']);
            $objMember->email = $arrData['email'];
            $objMember->sectionId = serialize($this->remoteUser->getGroupMembership());
            // Member has to be member of a valid sac section
            $objMember->isSacMember = \count($this->remoteUser->getGroupMembership()) > 0 ? '1' : '';
            $objMember->tstamp = time();
            // Groups
            $arrGroups = $stringUtilAdapter->deserialize($objMember->groups, true);
            $arrAutoGroups = $stringUtilAdapter->deserialize($configAdapter->get('SAC_SSO_LOGIN_ADD_TO_MEMBER_GROUPS'), true);
            $objMember->groups = serialize(array_merge($arrGroups, $arrAutoGroups));

            // Set random password
            if (empty($objMember->password)) {
                $encoder = $systemAdapter->getContainer()->get('security.encoder_factory')->getEncoder(FrontendUser::class);
                $objMember->password = $encoder->encodePassword(substr(md5((string) random_int(900009, 111111111111)), 0, 8), null);
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
        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        $arrData = $this->remoteUser->getData();

        $objUser = $this->getModel('tl_user');

        if (null !== $objUser) {
            $objUser->mobile = $this->beautifyPhoneNumber($arrData['telefonmobil']);
            $objUser->phone = $this->beautifyPhoneNumber($arrData['telefonp']);
            $objUser->uuid = $arrData['sub'];
            $objUser->lastname = $arrData['familienname'];
            $objUser->firstname = $arrData['vorname'];
            $objUser->name = $arrData['vorname'].' '.$arrData['familienname'];
            $objUser->street = $arrData['strasse'];
            $objUser->city = $arrData['ort'];
            $objUser->postal = $arrData['plz'];
            $objUser->dateOfBirth = false !== strtotime($arrData['geburtsdatum']) ? strtotime($arrData['geburtsdatum']) : 0;
            $objUser->gender = 'HERR' === $arrData['anredecode'] ? 'male' : 'female';
            $objUser->country = strtolower($arrData['land']);
            $objUser->email = $arrData['email'];
            $objUser->sectionId = serialize($this->remoteUser->getGroupMembership());
            $objUser->tstamp = time();

            // Set random password
            if (empty($objUser->password)) {
                $encoder = $systemAdapter->getContainer()->get('security.encoder_factory')->getEncoder(BackendUser::class);
                $objUser->password = $encoder->encodePassword(substr(md5((string) random_int(900009, 111111111111)), 0, 8), null);
            }

            // Save
            $objUser->save();

            $objUser->refresh();
        }
    }

    /**
     * @throws \Exception
     */
    public function addFrontendGroups(ModuleModel $model): void
    {
        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        // Add groups
        $objUser = $this->getModel('tl_member');

        if (null !== $objUser) {
            $arrMemberGroups = $stringUtilAdapter->deserialize($objUser->groups, true);
            $arrGroupsToAdd = $stringUtilAdapter->deserialize($model->swiss_alpine_club_oidc_add_to_fe_groups, true);
            $arrGroups = array_merge($arrMemberGroups, $arrGroupsToAdd);
            $arrGroups = array_unique($arrGroups);
            $arrGroups = array_filter($arrGroups);
            $objUser->groups = serialize($arrGroups);
            $objUser->save();
        }
    }

    /**
     * @param $username
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
    public function activateLogin(): void
    {
        if ('frontend' !== $this->getContaoScope()) {
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

    /**
     * @param string $strNumber
     *
     * @return string|array<string>|null
     */
    public static function beautifyPhoneNumber($strNumber = '')
    {
        if ('' !== $strNumber) {
            // Remove whitespaces
            $strNumber = preg_replace('/\s+/', '', $strNumber);
            // Remove country code
            $strNumber = str_replace('+41', '', $strNumber);
            $strNumber = str_replace('0041', '', $strNumber);

            // Add a leading zero, if there is no f.ex 41
            if ('0' !== substr($strNumber, 0, 1) && 9 === \strlen($strNumber)) {
                $strNumber = '0'.$strNumber;
            }

            // Search for 0799871234 and replace it with 079 987 12 34
            $pattern = '/^([0]{1})([0-9]{2})([0-9]{3})([0-9]{2})([0-9]{2})$/';

            if (preg_match($pattern, $strNumber)) {
                $pattern = '/^([0]{1})([0-9]{2})([0-9]{3})([0-9]{2})([0-9]{2})$/';
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
        $arrScopes = ['frontend', 'backend'];

        if (!\in_array(strtolower($scope), $arrScopes, true)) {
            throw new \Exception('Parameter "$scope" should be either "frontend" or "backend".');
        }

        $this->contaoScope = strtolower($scope);
    }

    /**
     * @throws \Exception
     */
    private function createFrontendUserIfNotExists(): void
    {
        $arrData = $this->remoteUser->getData();
        $username = preg_replace('/^0+/', '', $arrData['contact_number']);
        $uuid = $arrData['sub'];

        if (!$this->isValidUsername($username)) {
            return;
        }

        if (null === $this->getModel('tl_member')) {
            $objNew = new MemberModel();
            $objNew->username = $username;
            $objNew->sacMemberId = $username;
            $objNew->uuid = $uuid;
            $objNew->dateAdded = time();
            $objNew->tstamp = time();
            $objNew->save();
            $this->updateFrontendUser();
        }
    }
}
