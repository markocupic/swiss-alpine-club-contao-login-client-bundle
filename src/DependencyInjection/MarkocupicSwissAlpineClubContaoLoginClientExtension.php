<?php

declare(strict_types=1);

/*
 * This file is part of Swiss Alpine Club Contao Login Client Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/swiss-alpine-club-contao-login-client-bundle
 */

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Class MarkocupicSwissAlpineClubContaoLoginClientExtension.
 */
class MarkocupicSwissAlpineClubContaoLoginClientExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function getAlias(): string
    {
        // Default root key would be markocupic_swiss_alpine_club_contao_login_client
        return Configuration::ROOT_KEY;
    }

    /**
     * @throws \Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();

        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../Resources/config')
        );

        $loader->load('listener.yml');
        $loader->load('subscriber.yml');
        $loader->load('services.yml');
        $loader->load('controller-contao-frontend-module.yml');

        $rootKey = $this->getAlias();

        $container->setParameter($rootKey.'.oidc.debug_mode', $config['oidc']['debug_mode']);
        $container->setParameter($rootKey.'.oidc.client_id', $config['oidc']['client_id']);
        $container->setParameter($rootKey.'.oidc.client_secret', $config['oidc']['client_secret']);
        $container->setParameter($rootKey.'.oidc.auth_provider_endpoint_authorize', $config['oidc']['auth_provider_endpoint_authorize']);
        $container->setParameter($rootKey.'.oidc.auth_provider_endpoint_token', $config['oidc']['auth_provider_endpoint_token']);
        $container->setParameter($rootKey.'.oidc.auth_provider_endpoint_userinfo', $config['oidc']['auth_provider_endpoint_userinfo']);
        $container->setParameter($rootKey.'.oidc.add_to_frontend_user_groups', $config['oidc']['add_to_frontend_user_groups']);
        $container->setParameter($rootKey.'.oidc.auth_provider_endpoint_logout', $config['oidc']['auth_provider_endpoint_logout']);
        $container->setParameter($rootKey.'.oidc.autocreate_frontend_user', $config['oidc']['autocreate_frontend_user']);
        $container->setParameter($rootKey.'.oidc.allow_frontend_login_to_sac_members_only', $config['oidc']['allow_frontend_login_to_sac_members_only']);
        $container->setParameter($rootKey.'.oidc.allow_frontend_login_to_predefined_section_members_only', $config['oidc']['allow_frontend_login_to_predefined_section_members_only']);
        $container->setParameter($rootKey.'.oidc.allow_frontend_login_if_contao_account_is_disabled', $config['oidc']['allow_frontend_login_if_contao_account_is_disabled']);
        $container->setParameter($rootKey.'.oidc.autocreate_backend_user', $config['oidc']['autocreate_backend_user']);
        $container->setParameter($rootKey.'.oidc.allow_backend_login_to_sac_members_only', $config['oidc']['allow_backend_login_to_sac_members_only']);
        $container->setParameter($rootKey.'.oidc.allow_backend_login_to_predefined_section_members_only', $config['oidc']['allow_backend_login_to_predefined_section_members_only']);
        $container->setParameter($rootKey.'.oidc.allow_backend_login_if_contao_account_is_disabled', $config['oidc']['allow_backend_login_if_contao_account_is_disabled']);
        $container->setParameter($rootKey.'.oidc.allowed_frontend_sac_section_ids', $config['oidc']['allowed_frontend_sac_section_ids']);
        $container->setParameter($rootKey.'.oidc.allowed_backend_sac_section_ids', $config['oidc']['allowed_backend_sac_section_ids']);
        $container->setParameter($rootKey.'.oidc.client_auth_endpoint_frontend', $config['oidc']['client_auth_endpoint_frontend']);
        $container->setParameter($rootKey.'.oidc.client_auth_endpoint_backend', $config['oidc']['client_auth_endpoint_backend']);
        $container->setParameter($rootKey.'.oidc.enable_backend_sso', $config['oidc']['enable_backend_sso']);
        $container->setParameter($rootKey.'.oidc.enable_csrf_token_check', $config['oidc']['enable_csrf_token_check']);
        // Session stuff
        $container->setParameter($rootKey.'.session.attribute_bag_key', $config['session']['attribute_bag_key']);
        $container->setParameter($rootKey.'.session.attribute_bag_name', $config['session']['attribute_bag_name']);
        $container->setParameter($rootKey.'.session.flash_bag_key', $config['session']['flash_bag_key']);
        // Backend settings
        $container->setParameter($rootKey.'.backend.hide_contao_login', $config['backend']['hide_contao_login']);
    }
}
