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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle;

use Markocupic\SwissAlpineClubContaoLoginClientBundle\DependencyInjection\Compiler\AddSessionBagsPass;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\DependencyInjection\MarkocupicSwissAlpineClubContaoLoginClientExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class MarkocupicSwissAlpineClubContaoLoginClientBundle extends Bundle
{
    public function getContainerExtension(): MarkocupicSwissAlpineClubContaoLoginClientExtension
    {
        // Set alias sac_oauth2_client
        return new MarkocupicSwissAlpineClubContaoLoginClientExtension();
    }

    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new AddSessionBagsPass());
    }
}
