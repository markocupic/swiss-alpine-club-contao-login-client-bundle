<?php

declare(strict_types=1);

/*
 * This file is part of Swiss Alpine Club Contao Login Client Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/swiss-alpine-club-contao-login-client-bundle
 */

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\EventListener\Contao;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Controller\SacLoginStartController;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

#[AsHook('parseBackendTemplate')]
readonly class ParseBackendTemplateListener
{
    public function __construct(
        private Container $container,
        private Environment $twig,
        private RouterInterface $router,
        private UriSigner $uriSigner,
    ) {
    }

    /**
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function __invoke(string $strContent, string $strTemplate): string
    {
        if (str_starts_with($strTemplate, 'be_login')) {
            if (!$this->container->getParameter('sac_oauth2_client.oidc.enable_backend_sso')) {
                return $strContent;
            }

            $template = [];

            $action = $this->router->generate(SacLoginStartController::LOGIN_ROUTE_BACKEND, [], UrlGeneratorInterface::ABSOLUTE_URL);
            $template['action'] = $this->uriSigner->sign($action);
            $template['target_path'] = $this->getTargetPath($strContent);
            $template['failure_path'] = $this->getFailurePath();
            $template['always_use_target_path'] = $this->getAlwaysUseTargetPath($strContent);
            $template['error'] = $this->getErrorMessage();
            $template['disable_contao_login'] = $this->container->getParameter('sac_oauth2_client.backend.disable_contao_login');

            // Render the oauth button container template
            $strSacLoginForm = $this->twig->render(
                '@MarkocupicSwissAlpineClubContaoLoginClient/backend/swiss_alpine_club_oidc_backend_login.html.twig',
                $template,
            );

            // Replace insert tags
            $strSacLoginForm = $this->container->get('contao.insert_tag.parser')->replaceInline($strSacLoginForm);

            // Prepend SAC SSO login form
            $strContent = str_replace('<form', $strSacLoginForm.'<form', $strContent);

            // Remove Contao login form
            if ($this->container->getParameter('sac_oauth2_client.backend.disable_contao_login')) {
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
        /** @var RouterInterface $router */
        $router = $this->container->get('router');

        return base64_encode($router->generate('contao_backend', [], UrlGeneratorInterface::ABSOLUTE_URL));
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
        $flashBag = $this->container->get('request_stack')
            ->getCurrentRequest()
            ->getSession()
            ->getFlashBag()
            ->get($this->container->getParameter('sac_oauth2_client.session.flash_bag_key'))
        ;

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
