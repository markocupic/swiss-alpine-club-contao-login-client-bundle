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

use Composer\Autoload\ClassLoader;

$loader = null;

foreach ([
    // The bundle is the root package, e.g. when the CI job checks it out.
    __DIR__.'/../vendor/autoload.php',
    // The bundle is installed in the vendor directory of a Contao project.
    __DIR__.'/../../../autoload.php',
] as $autoloader) {
    if (is_file($autoloader)) {
        $loader = require $autoloader;
        break;
    }
}

if (!$loader instanceof ClassLoader) {
    throw new RuntimeException('Could not find the composer autoloader. Run "composer install" first.');
}

// Composer only applies the autoload-dev section of the ROOT package. If the bundle
// is installed as a dependency, its test namespace is unknown to the project's
// autoloader, and helpers such as the fixtures would not be found.
$loader->addPsr4('Markocupic\\SwissAlpineClubContaoLoginClientBundle\\Tests\\', __DIR__);
