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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\OAuth2\Client\Provider;

use League\OAuth2\Client\Provider\AbstractProvider;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

readonly class ProviderFactory
{
    public function __construct(
        #[Autowire('@markocupic.sac_oauth2_client.oauth2.client.provider.provider_configuration')]
        private ProviderConfiguration $providerConfiguration,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function createProvider(): AbstractProvider
    {
        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);

        return new Hitobito($resolver->resolve($this->providerConfiguration->all()), [], $this->eventDispatcher);
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired([
            'clientId',
            'clientSecret',
            'urlAuthorize',
            'urlAccessToken',
            'urlResourceOwnerDetails',
            'redirectUri',
            'scopes',
        ]);

        $resolver->setAllowedTypes('scopes', OAuthScope::class.'[]');

        $resolver->setDefined('pkceMethod');
        $resolver->setAllowedTypes('pkceMethod', PkceMethod::class);

        $urlKeys = ['urlAuthorize', 'urlAccessToken', 'urlResourceOwnerDetails', 'redirectUri'];

        foreach ($urlKeys as $key) {
            $resolver->setAllowedValues(
                $key,
                static function (string $value): bool {
                    if (!str_starts_with($value, 'https://')) {
                        return false;
                    }

                    return true;
                },
            );
        }
    }
}
