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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\ErrorMessage;

final class ErrorMessage
{
    public const LEVEL_WARNING = 'warning';
    public const LEVEL_ERROR = 'error';

    private string $level;
    private string $matter;
    private string $howToFix;
    private string $explain;

    public function __construct(string $level, string $matter, string $howToFix = '', string $explain = '')
    {
        if (self::LEVEL_ERROR !== $level && self::LEVEL_WARNING !== $level) {
            throw new \InvalidArgumentException(sprintf('First parameter must be either %s or %s, %s given.', self::LEVEL_WARNING, self::LEVEL_ERROR, $level));
        }

        $this->level = $level;
        $this->matter = $matter;
        $this->howToFix = $howToFix;
        $this->explain = $explain;
    }

    public function get(): array
    {
        return [
            'level' => $this->level,
            'matter' => $this->matter,
            'howToFix' => $this->howToFix,
            'explain' => $this->explain,
        ];
    }
}
