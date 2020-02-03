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
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Security\User\ContaoUserProvider;
use Contao\CoreBundle\Security\User\UserChecker;
use Contao\FrontendUser;
use Contao\MemberModel;
use Contao\StringUtil;
use Contao\UserModel;
use Contao\System;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Oauth\Oauth;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Security;

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
     * @var UserChecker
     */
    private $userChecker;

    /**
     * @var SessionInterface
     */
    private $session;

    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var LoggerInterface|null
     */
    private $logger;

    /**
     * User constructor.
     * @param ContaoFramework $framework
     * @param UserChecker $userChecker
     * @param SessionInterface $session
     * @param TokenStorageInterface $tokenStorage
     * @param EventDispatcherInterface $eventDispatcher
     * @param RequestStack $requestStack
     * @param null|LoggerInterface $logger
     */
    public function __construct(ContaoFramework $framework, UserChecker $userChecker, SessionInterface $session, TokenStorageInterface $tokenStorage, EventDispatcherInterface $eventDispatcher, RequestStack $requestStack, ?LoggerInterface $logger = null)
    {
        $this->framework = $framework;
        $this->userChecker = $userChecker;
        $this->session = $session;
        $this->tokenStorage = $tokenStorage;
        $this->eventDispatcher = $eventDispatcher;
        $this->requestStack = $requestStack;
        $this->logger = $logger;

        // Initialize Contao framework
        $this->framework->initialize();
    }

    /**
     * @param array $arrData
     * @param string $userClass
     */
    public function createIfNotExists(array $arrData, string $userClass): void
    {
        if ($userClass === FrontendUser::class)
        {
            $this->createFrontendUserIfNotExists($arrData);
        }

        if ($userClass === BackendUser::class)
        {
            $this->createBackendUserIfNotExists($arrData);
        }
    }

    /**
     * @param $arrData
     */
    private function createFrontendUserIfNotExists($arrData)
    {
        $username = preg_replace('/^0+/', '', $arrData['contact_number']);
        if (!$this->isValidUsername($username))
        {
            return;
        }

        $objUser = MemberModel::findByUsername($username);
        if ($objUser === null)
        {
            $objNew = new MemberModel();
            $objNew->username = $username;
            $objNew->sacMemberId = $username;
            $objNew->dateAdded = time();
            $objNew->tstamp = time();
            $objNew->save();
            $this->updateFrontendUser($arrData);
        }
    }

    /**
     * @param array $arrData
     * @param string $userClass
     */
    public function updateUser(array $arrData, string $userClass): void
    {
        if ($userClass === FrontendUser::class)
        {
            $this->updateFrontendUser($arrData);
        }

        if ($userClass === BackendUser::class)
        {
            $this->updateBackendUser($arrData);
        }
    }

    /**
     * @param array $arrData
     */
    public function updateFrontendUser(array $arrData)
    {
        $objUser = MemberModel::findByUsername($arrData['contact_number']);
        if ($objUser !== null)
        {
            $objUser->login = '1';
            $objUser->disable = '';
            $objUser->sacMemberId = $arrData['contact_number'];
            $objUser->mobile = $arrData['telefonmobil'];
            $objUser->phone = $arrData['telefonp'];
            $objUser->uuid = $arrData['sub'];
            $objUser->lastname = $arrData['familienname'];
            $objUser->firstname = $arrData['vorname'];
            $objUser->street = $arrData['strasse'];
            $objUser->city = $arrData['ort'];
            $objUser->postal = $arrData['plz'];
            $objUser->dateOfBirth = strtotime($arrData['geburtsdatum']) !== false ? strtotime($arrData['geburtsdatum']) : 0;
            $objUser->gender = $arrData['anredecode'] === 'HERR' ? 'male' : 'female';
            $objUser->country = strtolower($arrData['land']);
            $objUser->email = $arrData['email'];
            $objUser->sectionId = serialize(Oauth::getGroupMembership($arrData));
            // Member has to be member of a valid sac section
            $objUser->isSacMember = count(Oauth::getGroupMembership($arrData)) > 0 ? '1' : '';
            $objUser->tstamp = time();
            // Groups
            $arrGroups = StringUtil::deserialize($objUser->groups, true);
            $arrAutoGroups = StringUtil::deserialize(Config::get('SAC_SSO_LOGIN_ADD_TO_MEMBER_GROUPS'), true);
            $objUser->groups = serialize(array_merge($arrGroups, $arrAutoGroups));

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
     * @param string $username
     * @param string $userClass
     */
    public function activateLogin(string $username, string $userClass)
    {
        if ($userClass !== FrontendUser::class)
        {
            return;
        }
        // Retrieve user by its username
        $userProvider = new ContaoUserProvider($this->framework, $this->session, $userClass, $this->logger);

        $user = $userProvider->loadUserByUsername($username);
        if (!$user instanceof FrontendUser)
        {
            return;
        }

        if (null !== ($objMember = MemberModel::findByUsername($user->username)))
        {
            $objMember->login = '1';
            $objMember->save();
            $userProvider->refreshUser($user);
        }
    }

    public function unlock($username, $userClass)
    {
        // Retrieve user by its username
        $userProvider = new ContaoUserProvider($this->framework, $this->session, $userClass, $this->logger);

        $user = $userProvider->loadUserByUsername($username);
        if (!$user instanceof $userClass)
        {
            return;
        }

        if ($user instanceof FrontendUser)
        {
            if (null !== ($objMember = MemberModel::findByUsername($user->username)))
            {
                $objMember->locked = 0;
                $objMember->save();
                $userProvider->refreshUser($user);
            }
            return;
        }

        if ($user instanceof BackendUser)
        {
            if (null !== ($objUser = UserModel::findByUsername($user->username)))
            {
                $objUser->locked = 0;
                $objUser->save();
                $userProvider->refreshUser($user);
            }
            return;
        }
    }

    /**
     * @param $username
     * @param string $userClass
     * @return bool
     */
    public function userExists(string $username, string $userClass): bool
    {
        // Retrieve user by its username
        $userProvider = new ContaoUserProvider($this->framework, $this->session, $userClass, $this->logger);

        $user = $userProvider->loadUserByUsername($username);
        if ($user instanceof $userClass)
        {
            return true;
        }

        return false;
    }

    /**
     * @param bool $isMember
     * @return array
     */
    public function getMockUserData($isMember = true): array
    {
        if ($isMember === true)
        {
            return [
                'telefonmobil'         => '079 999 99 99',
                'sub'                  => '0e592343a-2122-11e8-91a0-00505684a4ad',
                'telefong'             => '041 984 13 50',
                'familienname'         => 'Messner',
                'strasse'              => 'Schloss Juval',
                'vorname'              => 'Reinhold',
                'Roles'                => 'NAV_BULLETIN,NAV_EINZEL_00999998,NAV_D,NAV_STAMMSEKTION_S00004250,NAV_EINZEL_S00004250,NAV_EINZEL_S00004251,NAV_S00004250,NAV_F1540,NAV_BULLETIN_S00004250,Internal/everyone,NAV_NAVISION,NAV_EINZEL,NAV_MITGLIED_S00004250,NAV_HERR,NAV_F1004V,NAV_F1004V_S00004250,NAV_BULLETIN_S00004250_PAPIER',
                'contact_number'       => '999998',
                'ort'                  => 'Vinschgau IT',
                'geburtsdatum'         => '25.05.1976',
                'anredecode'           => 'HERR',
                'name'                 => 'Messner Reinhold',
                'land'                 => 'IT',
                'kanton'               => 'ST',
                'korrespondenzsprache' => 'D',
                'telefonp'             => '099 999 99 99',
                'email'                => 'r.messner@matterhorn-kiosk.ch',
                'plz'                  => '6208',
            ];
        }

        // Non member
        return [
            'telefonmobil'         => '079 999 99 99',
            'sub'                  => '0e59877743a-2122-11e8-91a0-00505684a4ad',
            'telefong'             => '041 984 13 50',
            'familienname'         => 'Rébuffat',
            'strasse'              => 'Schloss Juval',
            'vorname'              => 'Gaston',
            'Roles'                => 'NAV_BULLETIN,NAV_EINZEL_00999999,NAV_D,NAV_STAMMSEKTION_S00009999,NAV_EINZEL_S00009999,NAV_EINZEL_S00009999,NAV_S00009999,NAV_F1540,NAV_BULLETIN_S00009999,Internal/everyone,NAV_NAVISION,NAV_EINZEL,NAV_MITGLIED_S00009999,NAV_HERR,NAV_F1004V,NAV_F1004V_S00009999,NAV_BULLETIN_S00009999_PAPIER',
            'contact_number'       => '999999',
            'ort'                  => 'Chamonix FR',
            'geburtsdatum'         => '25.05.1976',
            'anredecode'           => 'HERR',
            'name'                 => 'Gaston Rébuffat',
            'land'                 => 'IT',
            'kanton'               => 'ST',
            'korrespondenzsprache' => 'D',
            'telefonp'             => '099 999 99 99',
            'email'                => 'm.cupic@gmx.ch',
            'plz'                  => '6208',
        ];
    }
}
