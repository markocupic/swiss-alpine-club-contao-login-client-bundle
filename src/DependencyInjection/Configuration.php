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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public const ROOT_KEY = 'sac_oauth2_client';

    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder(self::ROOT_KEY);

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('oidc')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('debug_mode')
                            ->defaultFalse()
                            ->info('If set to true, the details about the resource owner will be logged (contao log).')
                        ->end()
                        ->scalarNode('client_id')
                            ->cannotBeEmpty()
                            ->defaultValue('***')
                        ->end()
                        ->scalarNode('client_secret')
                            ->cannotBeEmpty()
                            ->defaultValue('***')
                        ->end()
                        ->scalarNode('auth_provider_endpoint_authorize')
                            ->cannotBeEmpty()
                            ->defaultValue('https://ids01.sac-cas.ch:443/oauth2/authorize')
                        ->end()
                        ->scalarNode('auth_provider_endpoint_token')
                            ->cannotBeEmpty()
                            ->defaultValue('https://ids01.sac-cas.ch:443/oauth2/token')
                        ->end()
                        ->scalarNode('auth_provider_endpoint_userinfo')
                            ->cannotBeEmpty()
                            ->defaultValue('https://ids01.sac-cas.ch:443/oauth2/userinfo')
                        ->end()
                        ->scalarNode('client_auth_endpoint_frontend_route')
                            ->cannotBeEmpty()
                            ->defaultValue('swiss_alpine_club_sso_login_frontend')
                        ->end()
                        ->scalarNode('client_auth_endpoint_backend_route')
                            ->cannotBeEmpty()
                            ->defaultValue('swiss_alpine_club_sso_login_backend')
                        ->end()
                        ->scalarNode('auth_provider_endpoint_logout')
                            ->cannotBeEmpty()
                            ->defaultValue('https://ids01.sac-cas.ch/oidc/logout')
                        ->end()
                        ->booleanNode('auto_create_frontend_user')
                            ->defaultFalse()
                        ->end()
                        ->booleanNode('allow_frontend_login_to_sac_members_only')
                            ->defaultTrue()
                        ->end()
                        ->booleanNode('allow_frontend_login_to_predefined_section_members_only')
                            ->defaultTrue()
                        ->end()
                        ->booleanNode('allow_frontend_login_if_contao_account_is_disabled')
                            ->defaultFalse()
                        ->end()
                        ->arrayNode('allowed_frontend_sac_section_ids')
                            ->scalarPrototype()->end()
                            ->info('Array of allowed SAC section ids. eg. [4250,4251,4252,4253,4254]')
                        ->end()
                        ->arrayNode('add_to_frontend_user_groups')
                            ->scalarPrototype()->end()
                            ->info('Add one or more contao frontend user group ids where user will be assigned, if he logs in. eg [9,10]')
                        ->end()
                        ->booleanNode('auto_create_backend_user')
                            ->defaultFalse()
                        ->end()
                        ->booleanNode('allow_backend_login_to_sac_members_only')
                            ->defaultTrue()
                        ->end()
                        ->booleanNode('allow_backend_login_to_predefined_section_members_only')
                            ->defaultTrue()
                        ->end()
                        ->booleanNode('allow_backend_login_if_contao_account_is_disabled')
                            ->defaultFalse()
                        ->end()
                        ->arrayNode('allowed_backend_sac_section_ids')
                            ->scalarPrototype()->end()
                            ->info('Array of allowed SAC section ids. eg. [4250,4251,4252,4253,4254]')
                        ->end()
                        ->booleanNode('enable_backend_sso')
                            ->defaultTrue()
                        ->end()
                        ->booleanNode('enable_csrf_token_check')
                            ->defaultFalse()
                        ->end()
                    ->end()
                ->end() // end oidc
                ->arrayNode('session')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('flash_bag_key')
                            ->cannotBeEmpty()
                            ->defaultValue('_sac_oauth2_client_flash_bag')
                        ->end()
                    ->end()
                ->end() // session
                ->arrayNode('backend')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('disable_contao_login')
                            ->defaultFalse()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
