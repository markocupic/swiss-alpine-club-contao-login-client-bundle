<?php

declare(strict_types=1);

/**
 * Swiss Alpine Club (SAC) Contao Login Client Bundle
 * Copyright (c) 2008-2020 Marko Cupic
 * @package swiss-alpine-club-contao-login-client-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/swiss-alpine-club-contao-login-client-bundle
 */

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\EventListener\Contao;

use Contao\User;
use Contao\CoreBundle\Framework\ContaoFramework;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Class PostLogoutListener
 * @package Markocupic\SwissAlpineClubContaoLoginClientBundle\EventListener\Contao
 */
class PostLogoutListener
{
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * PostLogoutListener constructor.
     * @param ContaoFramework $framework
     */
    public function __construct(ContaoFramework $framework)
    {
        $this->framework = $framework;
    }

    /**
     * @param User $objUser
     */
    public function killSession(User $objUser)
    {
       //

    }
}
