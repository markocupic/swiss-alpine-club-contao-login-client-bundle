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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Security;

use Symfony\Component\HttpFoundation\Request;

/**
 * Validates user supplied redirect targets (_target_path, _failure_path,
 * post_logout_redirect_uri) in order to prevent open redirects.
 *
 * A redirect target is considered safe, if it is either a relative path beginning
 * with a single slash or an absolute http(s) url pointing to the host of the
 * current request.
 */
class RedirectPathValidator
{
    /**
     * Decode a base64 encoded redirect target and return it, if it is safe. Returns
     * null for missing, malformed or unsafe targets.
     */
    public function getSafePath(string|null $base64EncodedPath, Request $request): string|null
    {
        if (null === $base64EncodedPath || '' === $base64EncodedPath) {
            return null;
        }

        $path = base64_decode($base64EncodedPath, true);

        if (false === $path || '' === $path) {
            return null;
        }

        return $this->isSafePath($path, $request) ? $path : null;
    }

    public function isSafePath(string $path, Request $request): bool
    {
        // Leading/trailing whitespace is never part of a legitimate redirect target, but
        // browsers may strip it and thus change the meaning of the url.
        if ($path !== trim($path)) {
            return false;
        }

        // Reject control characters (response splitting).
        if (preg_match('/[\x00-\x1F\x7F]/', $path)) {
            return false;
        }

        // Reject scheme relative urls like "//example.com", "/\example.com" or
        // "\\example.com".
        if (preg_match('#^[/\\\\]{2,}#', $path)) {
            return false;
        }

        $parts = parse_url($path);

        if (false === $parts) {
            return false;
        }

        // Relative targets always point to the current host.
        if (!isset($parts['host'])) {
            return str_starts_with($path, '/');
        }

        if (isset($parts['scheme']) && !\in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }

        return strtolower($parts['host']) === strtolower($request->getHost());
    }
}
