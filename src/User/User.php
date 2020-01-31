<?php

declare(strict_types=1);

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\User;

use Contao\BackendUser;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\CoreBundle\Security\User\ContaoUserProvider;
use Contao\CoreBundle\Security\User\UserChecker;
use Contao\FrontendUser;
use Contao\Config;
use Contao\MemberModel;
use Contao\UserModel;
use Psr\Log\LogLevel;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

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

        $this->framework->initialize();
    }

    /**
     * @param array $arrData
     * @param array $arrClubIds
     * @return bool
     */
    public function isClubMember(array $arrData, array $arrClubIds): bool
    {
        $arrMembership = [];
        if (isset($arrData['Roles']) && !empty($arrData['Roles']))
        {
            $arrRoles = explode(',', $arrData['Roles']);
            foreach ($arrRoles as $role)
            {
                //[Roles] => NAV_BULLETIN,NAV_EINZEL_00185155,NAV_D,NAV_STAMMSEKTION_S00004250,NAV_EINZEL_S00004250,NAV_S00004250,NAV_F1540,NAV_BULLETIN_S00004250,Internal/everyone,NAV_NAVISION,NAV_EINZEL,NAV_MITGLIED_S00004250,NAV_HERR,NAV_F1004V,NAV_F1004V_S00004250,NAV_BULLETIN_S00004250_PAPIER
                if (strpos($role, 'NAV_EINZEL_S') === 0)
                {
                    $strRole = str_replace('NAV_EINZEL_S', '', $role);
                    $strRole = preg_replace('/^0+/', '', $strRole);
                    if (!empty($strRole))
                    {
                        if (in_array($strRole, $arrClubIds))
                        {
                            return true;
                        }
                    }
                }
            }
        }
        return false;
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
     * @param $username
     * @param string $userClass
     * @return bool
     */
    public function userExists(string $username, string $userClass): bool
    {
        // Retrieve user by its username
        $userProvider = new ContaoUserProvider($this->framework, $this->session, $userClass, $this->logger);

        $user = $userProvider->loadUserByUsername($username);
        if (!$user instanceof $userClass)
        {
            return false;
        }

        if ($user instanceof FrontendUser)
        {
            if (null !== ($objMember = MemberModel::findByUsername($user->username)))
            {
                $objMember->login = '1';
                $objMember->locked = 0;
                $objMember->save();
                $user = $userProvider->refreshUser($user);
                return true;
            }
        }

        if ($user instanceof BackendUser)
        {
            if (null !== ($objUser = UserModel::findByUsername($user->username)))
            {
                $objUser->locked = 0;
                $objUser->save();
                $user = $userProvider->refreshUser($user);
                return true;
            }
        }

        return false;
    }
}
