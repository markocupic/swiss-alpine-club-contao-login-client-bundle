<?php

declare(strict_types=1);

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\AppChecker;

use Contao\BackendUser;
use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\CoreBundle\Security\User\ContaoUserProvider;
use Contao\CoreBundle\Security\User\UserChecker;
use Contao\FrontendUser;
use Contao\MemberModel;
use Contao\UserModel;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Exception\AppCheckFailedException;
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
 * Class AppChecker
 * @package Markocupic\SwissAlpineClubContaoLoginClientBundle\AppChecker
 */
class AppChecker
{


    /**
     * @var ContaoFramework
     */
    private $framework;



    /**
     * AppChecker constructor.
     * @param ContaoFramework $framework
     */
    public function __construct(ContaoFramework $framework)
    {
        $this->framework = $framework;
        $this->framework->initialize();
    }

    /**
     * @throws AppCheckFailedException
     */
    public function checkConfiguration()
    {
        $arrConfigs = [
            // Club ids
            'SAC_EVT_SAC_SECTION_IDS',
            //OIDC Stuff
            'SAC_SSO_LOGIN_CLIENT_ID',
            'SAC_SSO_LOGIN_CLIENT_SECRET',
            'SAC_SSO_LOGIN_REDIRECT_URI',
            'SAC_SSO_LOGIN_URL_AUTHORIZE',
            'SAC_SSO_LOGIN_URL_ACCESS_TOKEN',
            'SAC_SSO_LOGIN_URL_RESOURCE_OWNER_DETAILS',
        ];

        foreach ($arrConfigs as $config)
        {
            if(empty(Config::get($config)))
            {
                throw new AppCheckFailedException('Parameter tl_settings.' . $config . ' not found. Please check the Contao settings');
            }
        }

    }
}
