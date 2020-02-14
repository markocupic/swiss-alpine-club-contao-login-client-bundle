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

use Contao\BackendTemplate;
use Contao\Config;
use Contao\System;
use Symfony\Component\HttpFoundation\Session\Session;

class ParseBackendTemplateListener
{
    /**
     * @var Session
     */
    private $session;

    /**
     * ParseBackendTemplateListener constructor.
     * @param Session $session
     */
    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    /**
     * Display option field in backend login
     *
     * @param $strContent
     * @param $strTemplate
     * @return mixed
     */
    public function addLoginButtonToTemplate($strContent, $strTemplate)
    {
        if ($strTemplate === 'be_login')
        {

            if(!Config::get('SAC_SSO_LOGIN_ENABLE_BACKEND_SSO'))
            {
                return $strContent;
            }

            $template = new BackendTemplate('mod_swiss_alpine_club_oidc_backend_login');

            $template->rt = '';
            if (preg_match('/name="REQUEST_TOKEN"\s+value=\"([^\']*?)\"/', $strContent, $matches))
            {
                $template->rt = $matches[1];
            }

            $template->targetPath = '';
            if (preg_match('/name="_target_path"\s+value=\"([^\']*?)\"/', $strContent, $matches))
            {
                $template->targetPath = $matches[1];
            }

            $template->failurePath = '';
            if (preg_match('/name="_failure_path"\s+value=\"([^\']*?)\"/', $strContent, $matches))
            {
                $template->failurePath = $matches[1];
            }

            $template->alwaysUseTargetPath = '';
            if (preg_match('/name="_always_use_target_path"\s+value=\"([^\']*?)\"/', $strContent, $matches))
            {
                $template->alwaysUseTargetPath = (string) $matches[1];
            }

            // Check for error messages
            $flashBagKey = System::getContainer()->getParameter('swiss_alpine_club_contao_login_client.session.flash_bag_key');
            $flashBag = $this->session->getFlashBag()->get($flashBagKey);
            if (count($flashBag) > 0)
            {
                $arrError = [];
                foreach ($flashBag[0] as $k => $v)
                {
                    $arrError[$k] = $v;
                }
                $template->error = $arrError;
            }

            $searchString = '</form>';
            $strContent = str_replace($searchString, $searchString . $template->parse(), $strContent);
        }

        return $strContent;
    }
}


