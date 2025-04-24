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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Cron;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;

#[AsCronJob('daily')]
readonly class RemoveExpiredLoginSessionRecordsCron
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function __invoke(): void
    {
        $this->connection->executeStatement(
            'DELETE FROM tl_sac_login_session WHERE expires < ?',
            [time()],
            [Types::INTEGER],
        );
    }
}
