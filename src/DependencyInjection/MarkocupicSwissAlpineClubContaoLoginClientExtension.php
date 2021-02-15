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
    public function getAlias()
    {
        // Default root key would be markocupic_swiss_alpine_club_contao_login_client
        return Configuration::ROOT_KEY;
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();

        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../Resources/config')
        );

        $loader->load('listener.yml');
        $loader->load('services.yml');
        $loader->load('controller-contao-frontend-module.yml');

        $rootKey = $this->getAlias();

        // Oidc stuff
        $container->setParameter($rootKey.'.oidc.client_id', $config['oidc']['client_id']);
        $container->setParameter($rootKey.'.oidc.client_secret', $config['oidc']['client_secret']);
        $container->setParameter($rootKey.'.oidc.url_authorize', $config['oidc']['url_authorize']);
        $container->setParameter($rootKey.'.oidc.url_access_token', $config['oidc']['url_access_token']);
        $container->setParameter($rootKey.'.oidc.resource_owner_details', $config['oidc']['resource_owner_details']);
        $container->setParameter($rootKey.'.oidc.add_to_frontend_user_groups', $config['oidc']['add_to_frontend_user_groups']);
        $container->setParameter($rootKey.'.oidc.url_logout', $config['oidc']['url_logout']);
        $container->setParameter($rootKey.'.oidc.allow_frontend_login_to_defined_section_members_only', $config['oidc']['allow_frontend_login_to_defined_section_members_only']);
        $container->setParameter($rootKey.'.oidc.allow_backend_login_to_defined_section_members_only', $config['oidc']['allow_backend_login_to_defined_section_members_only']);
        $container->setParameter($rootKey.'.oidc.allowed_frontend_sac_section_ids', $config['oidc']['allowed_frontend_sac_section_ids']);
        $container->setParameter($rootKey.'.oidc.allowed_backend_sac_section_ids', $config['oidc']['allowed_backend_sac_section_ids']);
        $container->setParameter($rootKey.'.oidc.redirect_uri_frontend', $config['oidc']['redirect_uri_frontend']);
        $container->setParameter($rootKey.'.oidc.redirect_uri_backend', $config['oidc']['redirect_uri_backend']);
        $container->setParameter($rootKey.'.oidc.enable_backend_sso', $config['oidc']['enable_backend_sso']);
        $container->setParameter($rootKey.'.oidc.enable_csrf_token_check', $config['oidc']['enable_csrf_token_check']);
        // Session stuff
        $container->setParameter($rootKey.'.session.attribute_bag_key', $config['session']['attribute_bag_key']);
        $container->setParameter($rootKey.'.session.attribute_bag_name', $config['session']['attribute_bag_name']);
        $container->setParameter($rootKey.'.session.flash_bag_key', $config['session']['flash_bag_key']);
    }
}
