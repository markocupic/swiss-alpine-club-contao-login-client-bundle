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

use Contao\DataContainer;

// Fields
$GLOBALS['TL_DCA']['tl_user']['fields']['loginAttempts']['sorting'] = true;
$GLOBALS['TL_DCA']['tl_user']['fields']['loginAttempts']['flag'] = DataContainer::SORT_DESC;
