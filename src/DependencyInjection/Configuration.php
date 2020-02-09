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

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * @var string
     */
    private $projectDir;

    /**
     * @var string
     */
    private $defaultLocale;

    public function __construct(string $projectDir, string $defaultLocale)
    {
        $this->projectDir = $projectDir;
        $this->defaultLocale = $defaultLocale;
    }

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('markocupic');

        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->children()
                ->arrayNode('swiss_alpine_club_contao_login_client')
                ->addDefaultsIfNotSet()
                ->children()
                    ->arrayNode('session')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('attribute_bag_name')
                            ->cannotBeEmpty()
                            ->defaultValue('swiss_alpine_club_contao_login_client_attr')
                            ->end()

                        ->scalarNode('attribute_bag_key')
                            ->cannotBeEmpty()
                            ->defaultValue('_swiss_alpine_club_contao_login_client_attr')
                            ->end()

                        ->scalarNode('flash_bag_key')
                            ->cannotBeEmpty()
                            ->defaultValue('_swiss_alpine_club_contao_login_client_flash_bag')
                            ->end()
                    ->end()
                ->end()
            ->end()
        ;
        return $treeBuilder;
    }

}

