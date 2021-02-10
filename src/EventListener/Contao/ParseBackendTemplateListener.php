<?php

declare(strict_types=1);

/*
 * This file is part of Swiss Alpine Club Contao Login Client Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/swiss-alpine-club-contao-login-client-bundle
 */

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\EventListener\Contao;

use Contao\BackendTemplate;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\System;
use Symfony\Component\HttpFoundation\Session\Session;

class ParseBackendTemplateListener
{
    /**
     * @var Session
     */
    private $session;

    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * ParseBackendTemplateListener constructor.
     */
    public function __construct(Session $session, ContaoFramework $framework)
    {
        $this->session = $session;
        $this->framework = $framework;
    }

    /**
     * Display option field in backend login.
     *
     * @param $strContent
     * @param $strTemplate
     *
     * @return mixed
     */
    public function addLoginButtonToTemplate($strContent, $strTemplate)
    {
        if ('be_login' === $strTemplate) {
            if (!Config::get('SAC_SSO_LOGIN_ENABLE_BACKEND_SSO')) {
                return $strContent;
            }

            $template = new BackendTemplate('mod_swiss_alpine_club_oidc_backend_login');

            // Get request token (disabled by default)
            $template->rt = '';
            $template->doCsrfTokenCheck = false;
            $systemAdapter = $this->framework->getAdapter(System::class);
            if($systemAdapter->getContainer->getParameter('swiss_alpine_club_contao_login_client.csrf_token_check') === 'true')
            {
                if (preg_match('/name="REQUEST_TOKEN"\s+value=\"([^\']*?)\"/', $strContent, $matches)) {
                    $template->rt = $matches[1];
                    $template->doCsrfTokenCheck = true;
                }
            }

            $template->targetPath = '';

            if (preg_match('/name="_target_path"\s+value=\"([^\']*?)\"/', $strContent, $matches)) {
                $template->targetPath = $matches[1];
            }

            $template->failurePath = '';

            if (preg_match('/name="_failure_path"\s+value=\"([^\']*?)\"/', $strContent, $matches)) {
                $template->failurePath = $matches[1];
            }

            $template->alwaysUseTargetPath = '';

            if (preg_match('/name="_always_use_target_path"\s+value=\"([^\']*?)\"/', $strContent, $matches)) {
                $template->alwaysUseTargetPath = (string) $matches[1];
            }

            // Check for error messages
            $flashBagKey = System::getContainer()->getParameter('swiss_alpine_club_contao_login_client.session.flash_bag_key');
            $flashBag = $this->session->getFlashBag()->get($flashBagKey);

            if (\count($flashBag) > 0) {
                $arrError = [];

                foreach ($flashBag[0] as $k => $v) {
                    $arrError[$k] = $v;
                }
                $template->error = $arrError;
            }

            $searchString = '</form>';
            $strContent = str_replace($searchString, $searchString.Controller::replaceInsertTags($template->parse()), $strContent);
        }

        return $strContent;
    }
}
