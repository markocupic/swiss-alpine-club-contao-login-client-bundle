<?php

declare(strict_types=1);

/**
 * Swiss Alpine Club (SAC) Contao Login Client Bundle
 * Copyright (c) 2008-2020 Marko Cupic
 * @package swiss-alpine-club-contao-login-client-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/swiss-alpine-club-contao-login-client-bundle
 */

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\DependencyInjection;

use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Config\FileLocator;

class MarkocupicSwissAlpineClubContaoLoginClientExtension extends Extension
{



    /**
     * {@inheritdoc}
     */
    public function getConfiguration(array $config, ContainerBuilder $container): Configuration
    {
        return new Configuration(
            $container->getParameter('kernel.project_dir'),
            $container->getParameter('kernel.default_locale')
        );
    }

    public function load(array $configs, ContainerBuilder $container)
    {

        $configuration = new Configuration(
            $container->getParameter('kernel.project_dir'),
            $container->getParameter('kernel.default_locale')
        );

        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../Resources/config')
        );

        $loader->load('parameters.yml');
        $loader->load('listener.yml');
        $loader->load('services.yml');
        $loader->load('controller-contao-frontend-module.yml');

        $container->setParameter('markocupic.swiss_alpine_club_contao_login_client.session.attribute_bag_name', $config['swiss_alpine_club_contao_login_client']['session']['attribute_bag_name']);
        $container->setParameter('markocupic.swiss_alpine_club_contao_login_client.session.attribute_bag_key', $config['swiss_alpine_club_contao_login_client']['session']['attribute_bag_key']);
        $container->setParameter('markocupic.swiss_alpine_club_contao_login_client.session.flash_bag_key', $config['swiss_alpine_club_contao_login_client']['session']['flash_bag_key']);

    }
}
