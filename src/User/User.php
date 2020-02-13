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
use Contao\CoreBundle\Security\User\ContaoUserProvider;
use Contao\FrontendUser;
use Contao\MemberModel;
use Contao\StringUtil;
use Contao\System;
use Contao\UserModel;
use Symfony\Component\HttpFoundation\Session\Session;
use Psr\Log\LoggerInterface;
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
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Session
     */
    private $session;

    /**
     * User constructor.
     * @param ContaoFramework $framework
     * @param Session $session
     * @param null|LoggerInterface $logger
     */
    public function __construct(ContaoFramework $framework, Session $session, ?LoggerInterface $logger = null)
    {
        $this->framework = $framework;
        $this->session = $session;
        $this->logger = $logger;

        // Initialize Contao framework
        $this->framework->initialize();
    }

    /**
     * @param RemoteUser $remoteUser
     * @param string $userClass
     */
    public function createIfNotExists(RemoteUser $remoteUser, string $userClass): void
    {
        if ($userClass === FrontendUser::class)
        {
            $this->createFrontendUserIfNotExists($remoteUser);
        }

        if ($userClass === BackendUser::class)
        {
            // Do not create backend user automatically
            //$this->createBackendUserIfNotExists($remoteUser);
        }
    }

    /**
     * @param RemoteUser $remoteUser
     * @param string $userClass
     */
    public function checkUserIsDisabled(RemoteUser $remoteUser, string $userClass): void
    {
        $arrData = $remoteUser->getData();

        $model = $userClass === BackendUser::class ? UserModel::findByUsername($remoteUser->get('contao_username')) : MemberModel::findByUsername($remoteUser->get('contao_username'));

        if (null !== ($model))
        {
            if ($model->disable)
            {
                $arrError = [
                    'matter'  => sprintf('Hallo %s<br>Schön bist du hier. Leider hat die Überprüfung deiner vom Identity Provider an uns übermittelten Daten fehlgeschlagen.', $arrData['vorname']),
                    //'howToFix' => 'Falls du soeben/erst kürzlich eine Neumitgliedschaft beantragt hast, dann warte bitten einen Tag und versuche dich danach noch einmal hier einzuloggen.',
                    'explain' => 'Dein Konto wurde leider deaktiviert und kann im Moment nicht verwendet werden.',
                ];
                $flashBagKey = System::getContainer()->getParameter('swiss_alpine_club_contao_login_client.session.flash_bag_key');
                $this->session->getFlashBag()->add($flashBagKey, $arrError);
                $bagName = System::getContainer()->getParameter('swiss_alpine_club_contao_login_client.session.attribute_bag_name');
                Controller::redirect($this->session->getBag($bagName)->get('errorPath'));
            }
        }
    }

    /**
     * @param RemoteUser $remoteUser
     */
    private function createFrontendUserIfNotExists(RemoteUser $remoteUser)
    {
        $arrData = $remoteUser->getData();
        $username = preg_replace('/^0+/', '', $arrData['contact_number']);
        $uuid = $arrData['sub'];
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
            $objNew->uuid = $uuid;
            $objNew->dateAdded = time();
            $objNew->tstamp = time();
            $objNew->save();
            $this->updateFrontendUser($remoteUser);
        }
    }

    /**
     * @param RemoteUser $remoteUser
     * @param string $userClass
     */
    public function updateUser(RemoteUser $remoteUser, string $userClass): void
    {
        if ($userClass === FrontendUser::class)
        {
            $this->updateFrontendUser($remoteUser);
        }

        if ($userClass === BackendUser::class)
        {
            $this->updateBackendUser($remoteUser);
        }
    }

    /**
     * @param RemoteUser $remoteUser
     * @param bool $sync
     */
    public function updateFrontendUser(RemoteUser $remoteUser, bool $sync = false)
    {
        $arrData = $remoteUser->getData();
        $objUser = MemberModel::findByUsername($arrData['contact_number']);
        if ($objUser !== null)
        {
            $objUser->login = '1';
            $objUser->disable = '';
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
            $objUser->sectionId = serialize($remoteUser->getGroupMembership());
            // Member has to be member of a valid sac section
            $objUser->isSacMember = count($remoteUser->getGroupMembership()) > 0 ? '1' : '';
            $objUser->tstamp = time();
            // Groups
            $arrGroups = StringUtil::deserialize($objUser->groups, true);
            $arrAutoGroups = StringUtil::deserialize(Config::get('SAC_SSO_LOGIN_ADD_TO_MEMBER_GROUPS'), true);
            $objUser->groups = serialize(array_merge($arrGroups, $arrAutoGroups));

            // Set random password
            if (empty($objUser->password))
            {
                $encoder = System::getContainer()->get('security.encoder_factory')->getEncoder(FrontendUser::class);
                $objUser->password = $encoder->encodePassword(substr(md5((string) rand(900009, 111111111111)), 0, 8), null);
            }

            // Save
            $objUser->save();

            $objUser->refresh();

            // Update Backend User (sync)
            if (!$sync)
            {
                $this->updateBackendUser($remoteUser, true);
            }
        }
    }

    /**
     * @param RemoteUser $remoteUser
     * @param bool $sync
     */
    public function updateBackendUser(RemoteUser $remoteUser, bool $sync = false)
    {
        $arrData = $remoteUser->getData();
        $objUser = UserModel::findOneBySacMemberId($arrData['contact_number']);
        if ($objUser !== null)
        {
            $objUser->disable = '';
            $objUser->mobile = $arrData['telefonmobil'];
            $objUser->phone = $arrData['telefonp'];
            $objUser->uuid = $arrData['sub'];
            $objUser->lastname = $arrData['familienname'];
            $objUser->firstname = $arrData['vorname'];
            $objUser->name = $arrData['vorname'];
            $objUser->street = $arrData['strasse'];
            $objUser->city = $arrData['ort'];
            $objUser->postal = $arrData['plz'];
            $objUser->dateOfBirth = strtotime($arrData['geburtsdatum']) !== false ? strtotime($arrData['geburtsdatum']) : 0;
            $objUser->gender = $arrData['anredecode'] === 'HERR' ? 'male' : 'female';
            $objUser->country = strtolower($arrData['land']);
            $objUser->email = $arrData['email'];
            $objUser->sectionId = serialize($remoteUser->getGroupMembership());
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

            // Update Frontend User
            if (!$sync)
            {
                $this->updateFrontendUser($remoteUser, true);
            }
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
     * @param RemoteUser $remoteUser
     * @param string $userClass
     */
    public function checkUserExists(RemoteUser $remoteUser, string $userClass)
    {
        $arrData = $remoteUser->getData();
        if (!isset($arrData) || empty($arrData['contact_number']) || !$this->userExists($remoteUser, $userClass))
        {
            $arrError = [
                'matter'   => sprintf('Hallo %s<br>Schön bist du hier. Leider hat die Überprüfung deiner vom Identity Provider an uns übermittelten Daten fehlgeschlagen.', $arrData['vorname']),
                'howToFix' => 'Falls du soeben/erst kürzlich eine Neumitgliedschaft beantragt hast, dann warte bitten einen Tag und versuche dich danach noch einmal hier einzuloggen.',
                'explain'  => 'Leider dauert es mindestens einen Tag bis uns von der Zentralstelle deine Mitgliedschaft bestätigt wird.',
            ];
            $flashBagKey = System::getContainer()->getParameter('swiss_alpine_club_contao_login_client.session.flash_bag_key');
            $this->session->getFlashBag()->add($flashBagKey, $arrError);
            $bagName = System::getContainer()->getParameter('swiss_alpine_club_contao_login_client.session.attribute_bag_name');
            Controller::redirect($this->session->getBag($bagName)->get('errorPath'));
        }
    }

    /**
     * @param RemoteUser $remoteUser
     * @param string $userClass
     */
    public function activateLogin(RemoteUser $remoteUser, string $userClass)
    {
        $username = $remoteUser->get('contact_number');
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

    /**
     * @param RemoteUser $remoteUser
     * @param $userClass
     */
    public function unlock(RemoteUser $remoteUser, $userClass)
    {
        $username = $remoteUser->get('contact_number');

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
     * @param RemoteUser $remoteUser
     * @param string $userClass
     * @return bool
     */
    public function userExists(RemoteUser $remoteUser, string $userClass): bool
    {
        $username = $remoteUser->get('contact_number');
        // Get username from sac member id
        if ($userClass === BackendUser::class)
        {
            if (null !== ($objUser = UserModel::findBySacMemberId($username)))
            {
                $username = $objUser->username;
                $remoteUser->username = $username;
            }
        }

        // Retrieve user by its username
        $userProvider = new ContaoUserProvider($this->framework, $this->session, $userClass, $this->logger);

        $user = $userProvider->loadUserByUsername($username);
        if ($user instanceof $userClass)
        {
            return true;
        }

        return false;
    }

}
