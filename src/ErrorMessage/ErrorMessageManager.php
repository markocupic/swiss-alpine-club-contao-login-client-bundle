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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\ErrorMessage;

use Symfony\Component\HttpFoundation\RequestStack;

class ErrorMessageManager
{
    private string $flashBagKey;
    private RequestStack $requestStack;

    public function __construct(string $flashBagKey, RequestStack $requestStack)
    {
        $this->flashBagKey = $flashBagKey;
        $this->requestStack = $requestStack;
    }

    /**
     * Add an error message to the session flash bag.
     */
    public function add2Flash(ErrorMessage $objErrorMsg): void
    {
        $request = $this->requestStack->getCurrentRequest();
        $session = $request->getSession();

        $session->getFlashBag()->add($this->flashBagKey, $objErrorMsg->get());
    }
}
