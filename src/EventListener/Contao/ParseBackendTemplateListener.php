<?php

declare(strict_types=1);

/*
 * This file is part of Swiss Alpine Club Contao Login Client Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/swiss-alpine-club-contao-login-client-bundle
 */

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\EventListener\Contao;

use Contao\BackendTemplate;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Contao\Environment;
use Contao\System;
use Symfony\Component\HttpFoundation\RequestStack;

#[AsHook('parseBackendTemplate')]
class ParseBackendTemplateListener
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ContaoFramework $framework,
    ) {
    }

    /**
     * Add SSO login button to the backend login form.
     *
     * @param $strContent
     * @param $strTemplate
     *
     * @return mixed
     */
    public function __invoke($strContent, $strTemplate): string
    {
        if ('be_login' === $strTemplate) {
            /** @var System $systemAdapter */
            $systemAdapter = $this->framework->getAdapter(System::class);

            /** @var Environment $environmentAdapter */
            $environmentAdapter = $this->framework->getAdapter(Environment::class);

            if (!$systemAdapter->getContainer()->getParameter('sac_oauth2_client.oidc.enable_backend_sso')) {
                return $strContent;
            }

            $template = new BackendTemplate('mod_swiss_alpine_club_oidc_backend_login');

            // Get request token (disabled by default)
            $template->rt = '';
            $template->enableCsrfTokenCheck = false;

            if ($systemAdapter->getContainer()->getParameter('sac_oauth2_client.oidc.enable_csrf_token_check')) {
                if (preg_match('/name="REQUEST_TOKEN"\s+value=\"([^\']*?)\"/', $strContent, $matches)) {
                    $template->rt = $matches[1];
                    $template->enableCsrfTokenCheck = true;
                }
            }

            $template->targetPath = '';

            if (preg_match('/name="_target_path"\s+value=\"([^\']*?)\"/', $strContent, $matches)) {
                $template->targetPath = $matches[1];
            }

            $failurePath = $environmentAdapter->get('url').'/contao';
            $template->failurePath = base64_encode($failurePath);

            $template->alwaysUseTargetPath = '';

            if (preg_match('/name="_always_use_target_path"\s+value=\"([^\']*?)\"/', $strContent, $matches)) {
                $template->alwaysUseTargetPath = (string) $matches[1];
            }

            // Check for error messages
            $flashBagKey = $systemAdapter->getContainer()->getParameter('sac_oauth2_client.session.flash_bag_key');
            $session = $this->requestStack->getCurrentRequest()->getSession();
            $flashBag = $session->getFlashBag()->get($flashBagKey);

            if (\count($flashBag) > 0) {
                $arrError = [];

                foreach ($flashBag[0] as $k => $v) {
                    $arrError[$k] = $v;
                }

                $template->error = $arrError;
            }

            $template->disableContaoLogin = $systemAdapter->getContainer()->getParameter('sac_oauth2_client.backend.disable_contao_login');

            $strAppendBefore = '<form';

            /** @var InsertTagParser $parser */
            $parser = $systemAdapter->getContainer()->get('contao.insert_tag.parser');

            // Parse SSO Login form
            $ssoLoginForm = $parser->replaceInline($template->parse());

            // Prepend sso login form to contao login form
            $strContent = str_replace($strAppendBefore, $ssoLoginForm.$strAppendBefore, $strContent);

            // Remove Contao login form
            $blnDisableContaoLogin = $systemAdapter->getContainer()->getParameter('sac_oauth2_client.backend.disable_contao_login');

            if (true === $blnDisableContaoLogin) {
                $strContent = preg_replace('/<form class="tl_login_form"[^>]*>(.*?)<\/form>/is', '', $strContent);
            }

            // Add hack: Test, if input field with id="username" exists.
            $strContent = str_replace("$('username').focus();", "if ($('username')){ \n\t\t$('username').focus();\n\t  }", $strContent);
        }

        return $strContent;
    }
}
