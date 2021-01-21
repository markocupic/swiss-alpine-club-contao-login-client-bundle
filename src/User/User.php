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
 * Class User
 * @package Markocupic\SwissAlpineClubContaoLoginClientBundle\User
 */
class User
{

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
     * @var RemoteUser
     */
    public $remoteUser;

    /**
     * @var string
     */
    private $contaoScope;

    /**
     * User constructor.
     * @param ContaoFramework $framework
     * @param Session $session
     * @param null|LoggerInterface $logger
     * @param TranslatorInterface $translator
     */
    public function __construct(ContaoFramework $framework, Session $session, ?LoggerInterface $logger = null, TranslatorInterface $translator)
    {
        $this->framework = $framework;
        $this->session = $session;
        $this->logger = $logger;
        $this->translator = $translator;

        // Initialize Contao framework
        $this->framework->initialize();
    }

    /**
     * @param RemoteUser $remoteUser
     * @param string $scope
     * @throws \Exception
     */
    public function initialize(RemoteUser $remoteUser, string $scope)
    {
        $this->remoteUser = &$remoteUser;
        $this->setContaoScope($scope);
    }

    /**
     * @param string $scope
     * @throws \Exception
     */
    private function setContaoScope(string $scope)
    {
        $arrScopes = ['frontend', 'backend'];
        if (!in_array(strtolower($scope), $arrScopes))
        {
            throw new \Exception('Parameter "$scope" should be either "frontend" or "backend".');
        }

        $this->contaoScope = strtolower($scope);
    }

    /**
     * @return null|string
     * @throws \Exception
     */
    public function getContaoScope(): ?string
    {
        if (empty($this->contaoScope))
        {
            throw new \Exception('No contao scope set.');
        }
        return $this->contaoScope;
    }

    /**
     * @param string $strTable
     * @return Model|null
     * @throws \Exception
     */
    public function getModel(string $strTable = ''): ?Model
    {
        if ($strTable === 'tl_member')
        {
            return MemberModel::findByUsername($this->remoteUser->get('contact_number'));
        }
        elseif ($strTable === 'tl_user')
        {
            return UserModel::findOneBySacMemberId($this->remoteUser->get('contact_number'));
        }
        elseif ($this->getContaoScope() === 'frontend')
        {
            return MemberModel::findByUsername($this->remoteUser->get('contact_number'));
        }
        elseif ($this->getContaoScope() === 'backend')
        {
            return UserModel::findOneBySacMemberId($this->remoteUser->get('contact_number'));
        }
        else
        {
            return null;
        }
    }

    /**
     * @throws \Exception
     */
    public function createIfNotExists(): void
    {
        if ($this->getContaoScope() === 'frontend')
        {
            $this->createFrontendUserIfNotExists();
        }

        if ($this->getContaoScope() === 'backend')
        {
            throw new \Exception('Auto-Creating Backend User is not allowed.');
        }
    }

    /**
     * @throws \Exception
     */
    private function createFrontendUserIfNotExists()
    {
        $arrData = $this->remoteUser->getData();
        $username = preg_replace('/^0+/', '', $arrData['contact_number']);
        $uuid = $arrData['sub'];
        if (!$this->isValidUsername($username))
        {
            return;
        }

        if ($this->getModel('tl_member') === null)
        {
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

    /**
     * @throws \Exception
     */
    public function checkUserExists()
    {
        $arrData = $this->remoteUser->getData();
        if (!isset($arrData) || empty($arrData['contact_number']) || !$this->userExists())
        {
            if ($this->getContaoScope() === 'frontend')
            {
                $arrError = [
                    'level'    => 'warning',
                    'matter'   => $this->translator->trans('ERR.sacOidcLoginError_userDoesNotExist_matter', [$arrData['vorname']], 'contao_default'),
                    'howToFix' => $this->translator->trans('ERR.sacOidcLoginError_userDoesNotExist_howToFix', [], 'contao_default'),
                    'explain'  => $this->translator->trans('ERR.sacOidcLoginError_userDoesNotExist_explain', [], 'contao_default'),
                ];
            }
            else
            {
                $arrError = [
                    'level'  => 'warning',
                    'matter' => $this->translator->trans('ERR.sacOidcLoginError_backendUserNotFound_matter', [$arrData['vorname']], 'contao_default'),
                    //'howToFix' => $this->translator->trans('ERR.sacOidcLoginError_backendUserNotFound_howToFix', [], 'contao_default'),
                    //'explain'  => $this->translator->trans('ERR.sacOidcLoginError_backendUserNotFound_explain', [], 'contao_default'),
                ];
            }

            $flashBagKey = System::getContainer()->getParameter('swiss_alpine_club_contao_login_client.session.flash_bag_key');
            $this->session->getFlashBag()->add($flashBagKey, $arrError);
            $bagName = System::getContainer()->getParameter('swiss_alpine_club_contao_login_client.session.attribute_bag_name');
            Controller::redirect($this->session->getBag($bagName)->get('failurePath'));
        }
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function userExists(): bool
    {
        if (null !== $this->getModel())
        {
            return true;
        }

        return false;
    }

    /**
     * @throws \Exception
     */
    public function checkIsLoginAllowed()
    {
        if (($model = $this->getModel()) !== null)
        {
            if ($this->getContaoScope() === 'frontend')
            {
                if ($model->login && !$model->disable && $model->locked == 0)
                {
                    return;
                }
            }

            if ($this->getContaoScope() === 'backend')
            {
                if (!$model->disable && $model->locked == 0)
                {
                    return;
                }
            }
        }

        $arrError = [
            'level'   => 'warning',
            'matter'  => $this->translator->trans('ERR.sacOidcLoginError_accountDisabled_matter', [$this->remoteUser->get('vorname')], 'contao_default'),
            //'howToFix' => $this->translator->trans('ERR.sacOidcLoginError_accountDisabled_howToFix', [], 'contao_default'),
            'explain' => $this->translator->trans('ERR.sacOidcLoginError_accountDisabled_explain', [], 'contao_default'),
        ];
        $flashBagKey = System::getContainer()->getParameter('swiss_alpine_club_contao_login_client.session.flash_bag_key');
        $this->session->getFlashBag()->add($flashBagKey, $arrError);
        $bagName = System::getContainer()->getParameter('swiss_alpine_club_contao_login_client.session.attribute_bag_name');
        Controller::redirect($this->session->getBag($bagName)->get('failurePath'));
    }

    /**
     * @throws \Exception
     */
    public function updateFrontendUser()
    {
        $arrData = $this->remoteUser->getData();

        $objMember = $this->getModel('tl_member');
        if ($objMember !== null)
        {
            $objMember->mobile = $this->beautifyPhoneNumber($arrData['telefonmobil']);
            $objMember->phone = $this->beautifyPhoneNumber($arrData['telefonp']);
            $objMember->uuid = $arrData['sub'];
            $objMember->lastname = $arrData['familienname'];
            $objMember->firstname = $arrData['vorname'];
            $objMember->street = $arrData['strasse'];
            $objMember->city = $arrData['ort'];
            $objMember->postal = $arrData['plz'];
            $objMember->dateOfBirth = strtotime($arrData['geburtsdatum']) !== false ? strtotime($arrData['geburtsdatum']) : 0;
            $objMember->gender = $arrData['anredecode'] === 'HERR' ? 'male' : 'female';
            $objMember->country = strtolower($arrData['land']);
            $objMember->email = $arrData['email'];
            $objMember->sectionId = serialize($this->remoteUser->getGroupMembership());
            // Member has to be member of a valid sac section
            $objMember->isSacMember = count($this->remoteUser->getGroupMembership()) > 0 ? '1' : '';
            $objMember->tstamp = time();
            // Groups
            $arrGroups = StringUtil::deserialize($objMember->groups, true);
            $arrAutoGroups = StringUtil::deserialize(Config::get('SAC_SSO_LOGIN_ADD_TO_MEMBER_GROUPS'), true);
            $objMember->groups = serialize(array_merge($arrGroups, $arrAutoGroups));

            // Set random password
            if (empty($objMember->password))
            {
                $encoder = System::getContainer()->get('security.encoder_factory')->getEncoder(FrontendUser::class);
                $objMember->password = $encoder->encodePassword(substr(md5((string) rand(900009, 111111111111)), 0, 8), null);
            }

            // Save
            $objMember->save();

            $objMember->refresh();
        }
    }

    /**
     * @throws \Exception
     */
    public function updateBackendUser()
    {
        $arrData = $this->remoteUser->getData();

        $objUser = $this->getModel('tl_user');
        if ($objUser !== null)
        {
            $objUser->mobile = $this->beautifyPhoneNumber($arrData['telefonmobil']);
            $objUser->phone = $this->beautifyPhoneNumber($arrData['telefonp']);
            $objUser->uuid = $arrData['sub'];
            $objUser->lastname = $arrData['familienname'];
            $objUser->firstname = $arrData['vorname'];
            $objUser->name = $arrData['vorname'] . ' ' . $arrData['familienname'];
            $objUser->street = $arrData['strasse'];
            $objUser->city = $arrData['ort'];
            $objUser->postal = $arrData['plz'];
            $objUser->dateOfBirth = strtotime($arrData['geburtsdatum']) !== false ? strtotime($arrData['geburtsdatum']) : 0;
            $objUser->gender = $arrData['anredecode'] === 'HERR' ? 'male' : 'female';
            $objUser->country = strtolower($arrData['land']);
            $objUser->email = $arrData['email'];
            $objUser->sectionId = serialize($this->remoteUser->getGroupMembership());
            $objUser->tstamp = time();

            // Set random password
            if (empty($objUser->password))
            {
                $encoder = System::getContainer()->get('security.encoder_factory')->getEncoder(BackendUser::class);
                $objUser->password = $encoder->encodePassword(substr(md5((string) rand(900009, 111111111111)), 0, 8), null);
            }

            // Save
            $objUser->save();

            $objUser->refresh();
        }
    }

    /**
     * @param ModuleModel $model
     * @throws \Exception
     */
    public function addFrontendGroups(ModuleModel $model)
    {
        // Add groups
        $objUser = $this->getModel('tl_member');
        if ($objUser !== null)
        {
            $arrMemberGroups = StringUtil::deserialize($objUser->groups, true);
            $arrGroupsToAdd = StringUtil::deserialize($model->swiss_alpine_club_oidc_add_to_fe_groups, true);
            $arrGroups = array_merge($arrMemberGroups, $arrGroupsToAdd);
            $arrGroups = array_unique($arrGroups);
            $arrGroups = array_filter($arrGroups);
            $objUser->groups = serialize($arrGroups);
            $objUser->save();
        }
    }

    /**
     * @param $username
     * @return bool
     */
    public function isValidUsername($username): bool
    {
        if (!\is_string($username) && (!\is_object($username) || !method_exists($username, '__toString')))
        {
            return false;
        }

        $username = trim($username);

        // Check if username is valid
        // Security::MAX_USERNAME_LENGTH = 4096;
        if (\strlen($username) > Security::MAX_USERNAME_LENGTH)
        {
            return false;
        }
        return true;
    }

    /**
     * @throws \Exception
     */
    public function enableLogin()
    {
        if (($model = $this->getModel()) !== null)
        {
            $model->disable = '';
            $model->save();
            $model->refresh();
        }
    }

    /**
     * @throws \Exception
     */
    public function activateLogin()
    {
        if ($this->getContaoScope() !== 'frontend')
        {
            return;
        }

        if (($model = $this->getModel()) !== null)
        {
            $model->login = '1';
            $model->save();
            $model->refresh();
        }
    }

    /**
     * @throws \Exception
     */
    public function unlock()
    {
        if (($model = $this->getModel()) !== null)
        {
            $model->locked = 0;
            $model->loginAttempts = 0;
            $model->save();
            $model->refresh();
        }
    }

    /**
     * @throws \Exception
     */
    public function resetLoginAttempts()
    {
        if (($model = $this->getModel()) !== null)
        {
            $model->loginAttempts = 0;
            $model->save();
            $model->refresh();
        }
    }

    /**
     * @param string $strNumber
     * @return string
     */
    public static function beautifyPhoneNumber($strNumber = ''): string
    {
        if ($strNumber != '')
        {
            // Remove whitespaces
            $strNumber = preg_replace('/\s+/', '', $strNumber);
            // Remove country code
            $strNumber = str_replace('+41', '', $strNumber);
            $strNumber = str_replace('0041', '', $strNumber);

            // Add a leading zero, if there is no f.ex 41
            if (substr($strNumber, 0, 1) != '0' && strlen($strNumber) === 9)
            {
                $strNumber = '0' . $strNumber;
            }

            // Search for 0799871234 and replace it with 079 987 12 34
            $pattern = '/^([0]{1})([0-9]{2})([0-9]{3})([0-9]{2})([0-9]{2})$/';
            if (preg_match($pattern, $strNumber))
            {
                $pattern = '/^([0]{1})([0-9]{2})([0-9]{3})([0-9]{2})([0-9]{2})$/';
                $replace = '$1$2 $3 $4 $5';
                $strNumber = preg_replace($pattern, $replace, $strNumber);
            }
        }

        return $strNumber;
    }

}
