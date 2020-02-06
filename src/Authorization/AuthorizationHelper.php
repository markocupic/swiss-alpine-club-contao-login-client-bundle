<?php

declare(strict_types=1);

/**
 * Swiss Alpine Club (SAC) Contao Login Client Bundle
 * Copyright (c) 2008-2020 Marko Cupic
 * @package swiss-alpine-club-contao-login-client-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/swiss-alpine-club-contao-login-client-bundle
 */

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Authorization;

use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\System;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Exception\AppCheckFailedException;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Exception\InvalidRequestTokenException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManager;

/**
 * Class AuthorizationHelper
 * @package Markocupic\SwissAlpineClubContaoLoginClientBundle\Authorization
 */
class AuthorizationHelper
{

    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var CsrfTokenManager
     */
    private $csrfTokenManager;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * AuthorizationHelper constructor.
     * @param ContaoFramework $framework
     * @param CsrfTokenManager $csrfTokenManager
     * @param RequestStack $requestStack
     */
    public function __construct(ContaoFramework $framework, CsrfTokenManager $csrfTokenManager, RequestStack $requestStack)
    {
        $this->framework = $framework;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->requestStack = $requestStack;

        $this->framework->initialize();
    }

    /**
     * @return array
     */
    public function getProviderData(): array
    {
        return [
            // The client ID assigned to you by the provider
            'clientId'                => Config::get('SAC_SSO_LOGIN_CLIENT_ID'),
            // The client password assigned to you by the provider
            'clientSecret'            => Config::get('SAC_SSO_LOGIN_CLIENT_SECRET'),
            // Absolute Callbackurl to your system(must be registered by service provider.)
            'redirectUri'             => Config::get('SAC_SSO_LOGIN_REDIRECT_URI'),
            'urlAuthorize'            => Config::get('SAC_SSO_LOGIN_URL_AUTHORIZE'),
            'urlAccessToken'          => Config::get('SAC_SSO_LOGIN_URL_ACCESS_TOKEN'),
            'urlResourceOwnerDetails' => Config::get('SAC_SSO_LOGIN_URL_RESOURCE_OWNER_DETAILS'),
            'response_type'           => 'code',
            'scopes'                  => ['openid'],
        ];
    }

    /**
     * @throws AppCheckFailedException
     * @throws InvalidRequestTokenException
     */
    public function checkQueryParams()
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request->query->has('moduleId'))
        {
            // Module id not found in the query string
            throw new AppCheckFailedException('Login Error: URI parameter "moduleId" not found.');
        }

        if (!$request->query->has('targetPath'))
        {
            // Target path not found in the query string
            throw new AppCheckFailedException('Login Error: URI parameter "targetPath" not found.');
        }

        if (!$request->query->has('errorPath'))
        {
            // Target path not found in the query string
            throw new AppCheckFailedException('Login Error: URI parameter "errorPath" not found.');
        }

        $tokenName = System::getContainer()->getParameter('contao.csrf_token_name');
        if (!$request->query->has('rt') || !$this->csrfTokenManager->isTokenValid(new CsrfToken($tokenName, $request->query->get('rt'))))
        {
            throw new InvalidRequestTokenException('Invalid CSRF token. Please reload the page and try again.');
        }
    }

}
