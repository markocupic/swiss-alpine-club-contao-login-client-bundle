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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Contao\ManagerPlugin\Config\ConfigPluginInterface;
use Contao\ManagerPlugin\Config\ContainerBuilder;
use Contao\ManagerPlugin\Config\ExtensionPluginInterface;
use Contao\ManagerPlugin\Routing\RoutingPluginInterface;
use Markocupic\SacEventToolBundle\MarkocupicSacEventToolBundle;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\MarkocupicSwissAlpineClubContaoLoginClientBundle;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Security\Authenticator\Authenticator;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class Plugin implements ConfigPluginInterface, BundlePluginInterface, RoutingPluginInterface, ExtensionPluginInterface
{
    /**
     * @internal
     */
    public function getBundles(ParserInterface $parser): array
    {
        return [
            BundleConfig::create(MarkocupicSwissAlpineClubContaoLoginClientBundle::class)
                ->setLoadAfter([ContaoCoreBundle::class])
                ->setLoadAfter([MarkocupicSacEventToolBundle::class]),
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader, array $managerConfig): void
    {
        $loader->load('@MarkocupicSwissAlpineClubContaoLoginClientBundle/config/config.yaml');
    }

    public function getRouteCollection(LoaderResolverInterface $resolver, KernelInterface $kernel)
    {
        return $resolver
            ->resolve(__DIR__.'/../../config/routes.yaml')
            ->load(__DIR__.'/../../config/routes.yaml')
        ;
    }

    public function getExtensionConfig($extensionName, array $extensionConfigs, ContainerBuilder $container): array
    {
        if ('security' !== $extensionName) {
            return $extensionConfigs;
        }

        foreach ($extensionConfigs as &$extensionConfig) {
            if (isset($extensionConfig['firewalls'], $extensionConfig['firewalls']['contao_frontend'])) {
                $extensionConfig['firewalls']['contao_frontend']['custom_authenticators'][] = Authenticator::class;
            }

            if (isset($extensionConfig['firewalls'], $extensionConfig['firewalls']['contao_backend'])) {
                $extensionConfig['firewalls']['contao_backend']['custom_authenticators'][] = Authenticator::class;
            }
        }

        $extensionConfigs[] = ['enable_authenticator_manager' => true];

        return $extensionConfigs;
    }
}
