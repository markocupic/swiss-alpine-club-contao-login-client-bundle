<?php

declare(strict_types=1);

/*
 * This file is part of Swiss Alpine Club Contao Login Client Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/swiss-alpine-club-contao-login-client-bundle
 */

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\EventListener\Contao;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Controller\StartController;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

#[AsHook('parseBackendTemplate', priority: 100)]
readonly class ParseBackendTemplateListener
{
    public function __construct(
        private Environment $twig,
        private InsertTagParser $insertTagParser,
        private RequestStack $requestStack,
        private RouterInterface $router,
        private TokenStorageInterface $tokenStorage,
        private UriSigner $uriSigner,
        #[Autowire('%sac_oauth2_client.oidc.enable_backend_sso%')]
        private bool $enableBackendSso,
        #[Autowire('%sac_oauth2_client.session.flash_bag_key%')]
        private string $sessionFlashBagKey,
        #[Autowire('%sac_oauth2_client.backend.disable_contao_login%')]
        private bool $disableContaoLogin,
    ) {
    }

    /**
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function __invoke(string $strContent, string $templateName): string
    {
        // Contao renders the second factor challenge with "be_login_two_factor", which
        // starts with "be_login" as well. That page must be left alone: its form is the
        // code input, not the password form, so removing it would lock out everyone who
        // has two factor authentication enabled. And offering the SSO button again to
        // someone who is halfway through a login would only restart the flow.
        //
        // Unlike in the DisableContaoBackendLoginListener, the token storage is filled
        // at this point, so the state can be read straight from the token.
        if ($this->tokenStorage->getToken() instanceof TwoFactorTokenInterface) {
            return $strContent;
        }

        if (str_starts_with($templateName, 'be_login')) {
            if (!$this->enableBackendSso) {
                return $strContent;
            }

            $template = [];

            $action = $this->router->generate(StartController::LOGIN_ROUTE_BACKEND, [], UrlGeneratorInterface::ABSOLUTE_URL);
            $template['action'] = $this->uriSigner->sign($action);
            $template['target_path'] = $this->getTargetPath($strContent);
            $template['failure_path'] = $this->getFailurePath();
            $template['always_use_target_path'] = $this->getAlwaysUseTargetPath($strContent);
            $template['error'] = $this->getErrorMessage();
            $template['disable_contao_login'] = $this->disableContaoLogin;

            // Render the oauth button container template
            $strSacLoginForm = $this->twig->render(
                '@MarkocupicSwissAlpineClubContaoLoginClient/backend/swiss_alpine_club_oidc_backend_login.html.twig',
                $template,
            );

            // Replace insert tags
            $strSacLoginForm = $this->insertTagParser->replaceInline($strSacLoginForm);

            // Prepend SAC SSO login form
            $strContent = str_replace('<form', $strSacLoginForm.'<form', $strContent);

            // Remove Contao login form
            if ($this->disableContaoLogin) {
                $strContent = preg_replace('/<form class="tl_login_form"[^>]*>(.*?)<\/form>/is', '', $strContent);
            }

            // Add hack: Test, if input field with id="username" exists.
            $strContent = str_replace("$('username').focus();", "if ($('username')){ \n\t\t$('username').focus();\n\t  }", $strContent);
        }

        return $strContent;
    }

    private function getTargetPath(string $strContent): string
    {
        $targetPath = '';

        if (preg_match('/name="_target_path"\s+value=\"([^\']*?)\"/', $strContent, $matches)) {
            $targetPath = $matches[1];
        }

        return $targetPath;
    }

    private function getFailurePath(): string
    {
        return base64_encode($this->router->generate('contao_backend', [], UrlGeneratorInterface::ABSOLUTE_URL));
    }

    private function getAlwaysUseTargetPath(string $strContent): string
    {
        $targetPath = '';

        if (preg_match('/name="_always_use_target_path"\s+value=\"([^\']*?)\"/', $strContent, $matches)) {
            $targetPath = $matches[1];
        }

        return $targetPath;
    }

    /**
     * Retrieve first error message.
     */
    private function getErrorMessage(): array|null
    {
        $session = $this->requestStack->getCurrentRequest()?->getSession();

        if (!$session instanceof FlashBagAwareSessionInterface) {
            return null;
        }

        $flashBag = $session->getFlashBag()->get($this->sessionFlashBagKey);

        if (!empty($flashBag)) {
            $arrError = [];

            foreach ($flashBag[0] as $k => $v) {
                $arrError[$k] = $v;
            }

            return $arrError;
        }

        return null;
    }
}
