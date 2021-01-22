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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\EventListener\Contao;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\User;

/**
 * Class PostLogoutListener.
 */
class PostLogoutListener
{
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * PostLogoutListener constructor.
     */
    public function __construct(ContaoFramework $framework)
    {
        $this->framework = $framework;
    }

    public function killSession(User $objUser): void
    {
    }
}
