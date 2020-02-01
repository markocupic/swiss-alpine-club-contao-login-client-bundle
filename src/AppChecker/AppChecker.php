<?php

declare(strict_types=1);

/**
 * Swiss Alpine Club (SAC) Contao Login Client Bundle
 * Copyright (c) 2008-2020 Marko Cupic
 * @package swiss-alpine-club-contao-login-client-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/swiss-alpine-club-contao-login-client-bundle
 */

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\AppChecker;

use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Exception\AppCheckFailedException;

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
            if (empty(Config::get($config)))
            {
                throw new AppCheckFailedException('Parameter tl_settings.' . $config . ' not found. Please check the Contao settings');
            }
        }
    }
}
