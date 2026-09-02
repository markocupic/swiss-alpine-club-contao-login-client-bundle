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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Session;

/**
 * Keys under which the OpenID Connect id_token is kept until the user logs out.
 *
 * The token is stored in the default attribute bag on purpose: the bundle's own
 * session bags are cleared on a successful login, but the token is needed later
 * on, as the id_token_hint of the single logout request.
 */
final class IdTokenSessionKeys
{
    public const string BACKEND = 'sac_oauth2_client_id_token_backend';

    public const string FRONTEND = 'sac_oauth2_client_id_token_frontend';

    public static function forScope(bool $isBackend): string
    {
        return $isBackend ? self::BACKEND : self::FRONTEND;
    }
}
