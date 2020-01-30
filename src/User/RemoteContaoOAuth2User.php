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

/**
 * Class RemoteContaoOAuth2User
 * @package Markocupic\SwissAlpineClubContaoLoginClientBundle\User
 */
class RemoteContaoOAuth2User implements RemoteUserInterface
{
    /**
     * @var array $userData
     */
    protected $userData;

    /**
     * @var int $id
     */
    protected $id;

    /**
     * RemoteContaoOAuth2User constructor.
     */
    public function __construct()
    {
        $this->setDefaultUserData();
    }

    /**
     * @param $key
     * @param $value
     */
    public function set($key, $value)
    {
        if ($field = $this->getFieldMap($key))
        {
            $this->userData[$field] = $value;
        }
    }

    /**
     * @param $key
     * @return mixed|null
     */
    public function get($key)
    {
        return $this->has($key) ? $this->userData[$key] : null;
    }

    /**
     * @param $key
     * @return bool
     */
    public function has($key)
    {
        return isset($this->userData[$key]);
    }

    /**
     * @param $field
     * @return mixed|null
     */
    protected function getFieldMap($field)
    {
        $mapping = [
            'fullname' => 'name',
            'username' => 'username',
            'email'    => 'email',
            'language' => 'language',
        ];

        if (isset($mapping[$field]))
        {
            return $mapping[$field];
        }

        return null;
    }

    /**
     *
     */
    public function setDefaultUserData()
    {
        /**
         *
         */
        $this->userData = [
            'admin'    => true,
            'language' => 'de', // Todo
        ];
    }

    /**
     * @return bool
     */
    public function validate()
    {
        $requiredFields = ['username', 'name', 'email'];

        foreach ($requiredFields as $field)
        {
            if (!$this->has($field))
            {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->userData;
    }

    /**
     * @return mixed|null
     */
    public function getUsername()
    {
        return $this->get('username');
    }

    /**
     * @param $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }
}
