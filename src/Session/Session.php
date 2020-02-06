<?php

declare(strict_types=1);

/**
 * Swiss Alpine Club (SAC) Contao Login Client Bundle
 * Copyright (c) 2008-2020 Marko Cupic
 * @package swiss-alpine-club-contao-login-client-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/swiss-alpine-club-contao-login-client-bundle
 */

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Session;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Class Session
 * @package Markocupic\SwissAlpineClubContaoLoginClientBundle\Session
 */
class Session
{
    public const SESSION_KEY = '_swiss_alpine_club_contao_login_client_session';

    public const ERROR_SESSION_FLASHBAG_KEY = '_swiss_alpine_club_contao_login_client_err_session_flashbag';

    /**
     * @var SessionInterface
     */
    private $session;

    /**
     * Session constructor.
     * @param SessionInterface $session
     */
    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
    }

    /**
     * @return bool
     */
    public function hasFlashBagMessage(): bool
    {
        if ($this->session->isStarted())
        {
            if ($this->session->getFlashBag()->has(static::ERROR_SESSION_FLASHBAG_KEY))
            {
                return true;
            }
        }
        return false;
    }

    /**
     * @param null $index
     * @return array
     */
    public function getFlashBagMessage($index = null): array
    {
        if ($this->session->isStarted())
        {
            if ($this->hasFlashBagMessage())
            {
                $arrMessages = $this->session->getFlashBag()->get(static::ERROR_SESSION_FLASHBAG_KEY);
                if (null === $index)
                {
                    return $arrMessages;
                }

                if (isset($arrMessages[$index]))
                {
                    return $arrMessages[$index];
                }
            }
        }
        return [];
    }

    /**
     * @param $arrMsg
     */
    public function addFlashBagMessage(array $arrMsg): void
    {
        if ($this->session->isStarted())
        {
            $flashBag = $this->session->getFlashBag();
            $flashBag->add(static::ERROR_SESSION_FLASHBAG_KEY, $arrMsg);
        }
    }

    /**
     * @param string $key
     * @return bool
     */
    public function sessionHas(string $key): bool
    {
        if (session_start())
        {
            if (isset($_SESSION[static::SESSION_KEY][$key]))
            {
                return true;
            }
        }
        return false;
    }

    /**
     * @param string $key
     * @return null|mixed
     */
    public function sessionGet(string $key)
    {
        if (session_start())
        {
            if (isset($_SESSION[static::SESSION_KEY][$key]))
            {
                return $_SESSION[static::SESSION_KEY][$key];
            }
        }

        return null;
    }

    /**
     * @param string $key
     * @param $value
     */
    public function sessionSet(string $key, $value): void
    {
        if (session_start())
        {
            if (!isset($_SESSION[static::SESSION_KEY]))
            {
                $_SESSION[static::SESSION_KEY] = [];
            }
            $_SESSION[static::SESSION_KEY][$key] = $value;
        }
    }

    /**
     * @param string $key
     */
    public function sessionRemove(string $key): void
    {
        if (session_start())
        {
            if (isset($_SESSION[static::SESSION_KEY][$key]))
            {
                unset($_SESSION[static::SESSION_KEY][$key]);
            }
        }
    }

    /**
     *
     */
    public function sessionDestroy()
    {
        if (session_start())
        {
            if (isset($_SESSION[static::SESSION_KEY]))
            {
                unset($_SESSION[static::SESSION_KEY]);
            }
        }
    }

}
