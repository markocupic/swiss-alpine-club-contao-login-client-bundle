<?php

declare(strict_types=1);

/*
 * This file is part of Swiss Alpine Club Contao Login Client Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/swiss-alpine-club-contao-login-client-bundle
 */

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\ErrorMessage;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

readonly class ErrorMessageManager
{
    public function __construct(
        private RequestStack $requestStack,
        #[Autowire('%sac_oauth2_client.session.flash_bag_key%')]
        private string $sessionFlashBagKey,
    ) {
    }

    /**
     * Add an error message to the session flash bag.
     */
    public function add2Flash(ErrorMessage $objErrorMsg): void
    {
        $this->getFlashBag()->add($this->sessionFlashBagKey, $objErrorMsg->get());
    }

    /**
     * Clear flash messages.
     */
    public function clearFlash(): void
    {
        $this->getFlashBag()->set($this->sessionFlashBagKey, []);
    }

    private function getFlashBag(): FlashBagInterface
    {
        return $this->requestStack->getCurrentRequest()->getSession()->getFlashBag();
    }
}
