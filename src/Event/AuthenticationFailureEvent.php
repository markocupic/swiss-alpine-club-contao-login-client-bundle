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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Event;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

class AuthenticationFailureEvent extends Event
{
    public function __construct(
        private readonly Request $request,
        private readonly string $errLevel,
        private readonly string $exceptionClass,
        private readonly ResourceOwnerInterface|null $resourceOwner,
        private readonly array $args = [],
    ) {
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getErrorLevel(): string
    {
        return $this->errLevel;
    }

    public function getExceptionClass(): string
    {
        return $this->exceptionClass;
    }

    public function getResourceOwner(): ResourceOwnerInterface|null
    {
        return $this->resourceOwner;
    }

    public function getArgs(): array
    {
        return $this->args;
    }
}
