<?php

declare(strict_types=1);

/*
 * This file is part of Swiss Alpine Club Contao Login Client Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/swiss-alpine-club-contao-login-client-bundle
 */

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\User;

use Contao\CoreBundle\Framework\ContaoFramework;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\ErrorMessage\ErrorMessageManager;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Provider\SwissAlpineClubResourceOwner;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Validator\LoginValidator;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ContaoUserFactory
{
    private ContaoFramework $framework;
    private TranslatorInterface $translator;
    private PasswordHasherFactoryInterface $hasherFactory;
    private LoginValidator $loginValidator;
    private ErrorMessageManager $errorMessageManager;

    public function __construct(ContaoFramework $framework, TranslatorInterface $translator, PasswordHasherFactoryInterface $hasherFactory, LoginValidator $loginValidator, ErrorMessageManager $errorMessageManager)
    {
        $this->framework = $framework;
        $this->translator = $translator;
        $this->hasherFactory = $hasherFactory;
        $this->loginValidator = $loginValidator;
        $this->errorMessageManager = $errorMessageManager;
    }

    public function createContaoUser(SwissAlpineClubResourceOwner $resourceOwner, string $contaoScope): ContaoUser
    {
        $contaoUser = new ContaoUser($this->framework, $this->translator, $this->hasherFactory, $this->loginValidator, $this->errorMessageManager);

        $contaoUser->createFromResourceOwner($resourceOwner, $contaoScope);

        return $contaoUser;
    }
}
