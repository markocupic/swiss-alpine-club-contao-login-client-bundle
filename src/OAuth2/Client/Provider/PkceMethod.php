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

/**
 * Proof Key for Code Exchange (RFC 7636).
 *
 * The case values match
 * League\OAuth2\Client\Provider\AbstractProvider::PKCE_METHOD_*. They
 * cannot reference those constants directly, because enum case values have
 * to be literals.
 */
enum PkceMethod: string
{
    /**
     * The code challenge is hashed with SHA-256 (recommended).
     */
    case S256 = 'S256';

    /**
     * The code challenge is sent in plain text. Only use this, if the identity
     * provider does not support S256.
     */
    case Plain = 'plain';

    /**
     * PKCE is disabled.
     */
    case None = 'none';

    /**
     * Returns the value expected by league/oauth2-client. Null disables PKCE.
     */
    public function toProviderOption(): string|null
    {
        return match ($this) {
            self::None => null,
            default => $this->value,
        };
    }

    public function isEnabled(): bool
    {
        return self::None !== $this;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
