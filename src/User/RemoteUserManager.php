<?php

/**
 * Swiss Alpine Club Login Client Bundle
 * OpenId Connect Login via https://sac-cas.ch for Contao Frontend and Backend
 *
 * @package Markocupic\SwissAlpineClubContaoLoginClientBundle
 * @author    Marko Cupic, Oberkirch
 * @license   MIT
 * @copyright 2020 Marko Cupic
 */

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\User;

use Markocupic\SwissAlpineClubContaoLoginClientBundle\Exception\UserNotYetCreatedException;
use Doctrine\DBAL\Connection;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Exception\InvalidUserDetailsException;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\ContaoBackendLogin;
use Symfony\Component\Routing\RouterInterface;

class RemoteUserManager
{
    /**
     * @var DatabaseConnection
     */
    protected $connection;

    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * @var ContaoBackendLogin
     */
    protected $contaoBackendLogin;

    /**
     * @var RemoteContaoOAuth2User
     */
    protected $remoteContaoOAuth2User;

    /**
     * RemoteUserManager constructor.
     * @param DatabaseConnection $connection
     * @param RouterInterface $router
     * @param ContaoBackendLogin $contaoBackendLogin
     * @param RemoteContaoOAuth2User $remoteContaoOAuth2User
     */
    public function __construct(Connection $connection, RouterInterface $router, ContaoBackendLogin $contaoBackendLogin, RemoteContaoOAuth2User $remoteContaoOAuth2User)
    {
        $this->connection = $connection;
        $this->router = $router;
        $this->contaoBackendLogin = $contaoBackendLogin;
        $this->remoteContaoOAuth2User = $remoteContaoOAuth2User;
    }

    /**
     * @param array $userData
     * @return RemoteContaoOAuth2User
     */
    public function create(array $userData): RemoteContaoOAuth2User
    {
        // Pass data to user object
        foreach ($userData as $field => $value)
        {
            $this->remoteContaoOAuth2User->set($field, $value);
        }

        return $this->remoteContaoOAuth2User;
    }

    /**
     * @param RemoteUserInterface $user
     * @throws InvalidUserDetailsException
     * @throws \Doctrine\DBAL\DBALException
     */
    public function createOrUpdate(RemoteUserInterface $user)
    {
        if (!$user->validate())
        {
            throw new InvalidUserDetailsException();
        }

        $userId = $this->connection->fetchColumn('SELECT id FROM tl_user WHERE username = ?', [$user->getUsername()]);

        if (!$userId)
        {
            // Create User
            $this->connection->insert('tl_user', $user->toArray());
            $userId = $this->connection->lastInsertId();
        }
        else
        {
            // Update User
            $this->connection->update('tl_user', $user->toArray(), ['id' => $userId]);
        }

        $user->setId($userId);
    }

    /**
     * @param RemoteUserInterface $user
     * @throws UserNotYetCreatedException
     */
    public function loginAs(RemoteUserInterface $user)
    {
        if (!$user->getId())
        {
            throw new UserNotYetCreatedException();
        }

        $this->contaoBackendLogin->login($user);
    }
}
