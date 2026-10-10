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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Util;

/**
 * Replaces Codefog\HasteBundle\UrlParser::addQueryString(), so that the bundle
 * no longer depends on codefog/contao-haste.
 */
final class QueryStringUtil
{
    /**
     * Adds a query parameter to a URL. An existing parameter with the same name
     * is replaced, all other parameters and the fragment are left untouched.
     */
    public static function addParameter(string $url, string $name, string $value): string
    {
        $fragment = '';

        if (false !== ($pos = strpos($url, '#'))) {
            $fragment = substr($url, $pos);
            $url = substr($url, 0, $pos);
        }

        $pairs = [];

        if (false !== ($pos = strpos($url, '?'))) {
            $pairs = array_filter(
                explode('&', substr($url, $pos + 1)),
                static fn (string $pair): bool => '' !== $pair && rawurldecode(explode('=', $pair, 2)[0]) !== $name,
            );

            $url = substr($url, 0, $pos);
        }

        $pairs[] = rawurlencode($name).'='.rawurlencode($value);

        return $url.'?'.implode('&', $pairs).$fragment;
    }
}
