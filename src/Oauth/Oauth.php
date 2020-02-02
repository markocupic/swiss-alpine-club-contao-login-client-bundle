<?php

declare(strict_types=1);

/**
 * Swiss Alpine Club (SAC) Contao Login Client Bundle
 * Copyright (c) 2008-2020 Marko Cupic
 * @package swiss-alpine-club-contao-login-client-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/swiss-alpine-club-contao-login-client-bundle
 */

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Oauth;

use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Security\User\UserChecker;
use Contao\System;
use Contao\Validator;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Exception\AppCheckFailedException;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Exception\InvalidRequestTokenException;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\User\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManager;

/**
 * Class Oauth
 * @package Markocupic\SwissAlpineClubContaoLoginClientBundle\Oauth
 */
class Oauth
{
    public const SESSION_KEY = 'swiss_alpine_club_contao_login_client_session';

    /** @var string provider key for contao frontend secured area */
    public const ERROR_SESSION_FLASHBAG_KEY = 'swiss_alpine_club_contao_login_client_err_session_flashbag';

    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var User
     */
    private $user;

    /**
     * @var UserChecker
     */
    private $userChecker;

    /**
     * @var SessionInterface
     */
    private $session;

    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var LoggerInterface|null
     */
    private $logger;

    /**
     * Oauth constructor.
     * @param ContaoFramework $framework
     * @param User $user
     * @param CsrfTokenManager $csrfTokenManager
     * @param UserChecker $userChecker
     * @param SessionInterface $session
     * @param TokenStorageInterface $tokenStorage
     * @param EventDispatcherInterface $eventDispatcher
     * @param RequestStack $requestStack
     * @param null|LoggerInterface $logger
     */
    public function __construct(ContaoFramework $framework, User $user, CsrfTokenManager $csrfTokenManager, UserChecker $userChecker, SessionInterface $session, TokenStorageInterface $tokenStorage, EventDispatcherInterface $eventDispatcher, RequestStack $requestStack, ?LoggerInterface $logger = null)
    {
        $this->framework = $framework;
        $this->user = $user;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->userChecker = $userChecker;
        $this->session = $session;
        $this->tokenStorage = $tokenStorage;
        $this->eventDispatcher = $eventDispatcher;
        $this->requestStack = $requestStack;
        $this->logger = $logger;

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

    /**
     * @return bool
     */
    public function hasFlashBagMessage(): bool
    {
        if ($this->session->isStarted())
        {
            if ($this->session->getFlashBag()->has(static::ERROR_SESSION_FLASHBAG_KEY))
            {
                return true;
            }
        }
        return false;
    }

    /**
     * @param null $index
     * @return array
     */
    public function getFlashBagMessage($index = null): array
    {
        if ($this->session->isStarted())
        {
            if ($this->hasFlashBagMessage())
            {
                $arrMessages = $this->session->getFlashBag()->get(static::ERROR_SESSION_FLASHBAG_KEY);
                if (null === $index)
                {
                    return $arrMessages;
                }

                if (isset($arrMessages[$index]))
                {
                    return $arrMessages[$index];
                }
            }
        }
        return [];
    }

    /**
     * @param $arrMsg
     */
    public function addFlashBagMessage(array $arrMsg): void
    {
        if ($this->session->isStarted())
        {
            $flashBag = $this->session->getFlashBag();
            $flashBag->add(static::ERROR_SESSION_FLASHBAG_KEY, $arrMsg);
        }
    }

    /**
     * @param array $arrData
     * @return bool
     */
    public function checkIsMemberInAllowedSection(array $arrData): bool
    {
        $arrMembership = $this->getGroupMembership($arrData);
        if (count($arrMembership) > 0)
        {
            return true;
        }

        $arrError = [
            'matter'   => 'Die Überprüfung der Daten vom Identity Provider hat fehlgeschlagen.',
            'howToFix' => sprintf('%s, Sie müssen Mitglied dieser SAC Sektion sein, um sich auf diesem Portal einloggen zu können.', $arrData['name']),
            'explain'  => 'Der geschütze Bereich ist nur Mitgliedern dieser SAC Sektion zugänglich.',
        ];
        $this->addFlashBagMessage($arrError);
        Controller::redirect($this->sessionGet('errorPath'));
    }

    /**
     * @todo Check for unique email address
     * @param array $arrData
     */
    public function checkHasValidEmail(array $arrData): void
    {
        if (empty($arrData['email']) || !Validator::isEmail($arrData['email']))
        {
            $arrError = [
                'matter'   => 'Die Überprüfung der Daten vom Identity Provider hat fehlgeschlagen.',
                'howToFix' => 'Sie haben noch keine/keine gültige E-Mail-Adresse hinterlegt. Bitte loggen Sie sich auf https:://www.sac-cas.ch auf Ihrem Account ein und hinterlegen Sie Ihre E-Mail-Adresse.',
                'explain'  => 'Einige Anwendungen (z.B. Event-Tool) auf diesem Portal setzen eine gültige E-Mail-Adresse voraus.',
            ];
            $this->addFlashBagMessage($arrError);
            Controller::redirect($this->sessionGet('errorPath'));
        }
    }

    /**
     * @param array $arrData
     */
    public function checkIsSacMember(array $arrData)
    {
        if (!isset($arrData) || empty($arrData['contact_number']) || empty($arrData['Roles']) || empty($arrData['contact_number']) || empty($arrData['sub']))
        {
            $arrError = [
                'matter'   => 'Die Überprüfung der Daten vom Identity Provider hat fehlgeschlagen.',
                'howToFix' => sprintf('%s, Sie müssen Mitglied des SAC sein, um sich auf diesem Portal einloggen zu können.', $arrData['name']),
                'explain'  => 'Der geschütze Bereich ist nur Mitgliedern des SAC (Schweizerischer Alpen Club) zugänglich.',
            ];
            $this->addFlashBagMessage($arrError);
            Controller::redirect($this->sessionGet('errorPath'));
        }
    }

    /**
     * @param array $arrData
     */
    public function checkHasValidUsername(array $arrData)
    {
        if (!isset($arrData) || empty($arrData['contact_number']) || !$this->user->isValidUsername($arrData['contact_number']))
        {
            $arrError = [
                'matter'   => sprintf('Die Überprüfung der Daten vom Identity Provider hat fehlgeschlagen. Der Benutzername "%s" ist ungültig.', $arrData['contact_number']),
                'howToFix' => 'Bitte überprüfen Sie die Schreibweise Ihrer Eingaben.',
                'explain'  => '',
            ];
            $this->addFlashBagMessage($arrError);
            Controller::redirect($this->sessionGet('errorPath'));
        }
    }

    /**
     * @param array $arrData
     * @param string $userClass
     */
    public function checkUserExists(array $arrData, string $userClass)
    {
        if (!isset($arrData) || empty($arrData['contact_number']) || !$this->user->userExists($arrData['contact_number'], $userClass))
        {
            $arrError = [
                'matter'   => sprintf('Die Überprüfung der Daten vom Identity Provider hat fehlgeschlagen. Der Benutzername "%s" wurde in der Datenbank nicht gefunden.', $arrData['contact_number']),
                'howToFix' => 'Falls Sie soeben eine Neumitgliedschaft beantragt haben, warten Sie bitten einen Tag und versuchen Sie sich danach noch einmal einzuloggen.',
                'explain'  => '',
            ];
            $this->addFlashBagMessage($arrError);
            Controller::redirect($this->sessionGet('errorPath'));
        }
    }

    /**
     * @param string $key
     * @param $value
     */
    public function sessionSet(string $key, $value): void
    {
        if (session_start())
        {
            if (!isset($_SESSION[static::SESSION_KEY]))
            {
                $_SESSION[static::SESSION_KEY] = [];
            }
            $_SESSION[static::SESSION_KEY][$key] = $value;
        }
    }

    /**
     * @param string $key
     */
    public function sessionRemove(string $key): void
    {
        if (session_start())
        {
            if (isset($_SESSION[static::SESSION_KEY][$key]))
            {
                unset($_SESSION[static::SESSION_KEY][$key]);
            }
        }
    }

    /**
     * @param string $key
     * @return null|mixed
     */
    public function sessionGet(string $key)
    {
        if (session_start())
        {
            if (isset($_SESSION[static::SESSION_KEY][$key]))
            {
                return $_SESSION[static::SESSION_KEY][$key];
            }
        }

        return null;
    }

    /**
     *
     */
    public function sessionDestroy()
    {
        if (session_start())
        {
            if (isset($_SESSION[static::SESSION_KEY]))
            {
                unset($_SESSION[static::SESSION_KEY]);
            }
        }
    }

    /**
     * @param array $arrData
     * @return array
     */
    public static function getGroupMembership(array $arrData): array
    {
        $arrMembership = [];
        $arrClubIds = explode(',', Config::get('SAC_EVT_SAC_SECTION_IDS'));
        if (isset($arrData['Roles']) && !empty($arrData['Roles']))
        {
            $arrRoles = explode(',', $arrData['Roles']);
            foreach ($arrRoles as $role)
            {
                //[Roles] => NAV_BULLETIN,NAV_EINZEL_00185155,NAV_D,NAV_STAMMSEKTION_S00004250,NAV_EINZEL_S00004250,NAV_S00004250,NAV_F1540,NAV_BULLETIN_S00004250,Internal/everyone,NAV_NAVISION,NAV_EINZEL,NAV_MITGLIED_S00004250,NAV_HERR,NAV_F1004V,NAV_F1004V_S00004250,NAV_BULLETIN_S00004250_PAPIER
                if (strpos($role, 'NAV_STAMMSEKTION_S') === 0)
                {
                    $strRole = str_replace('NAV_STAMMSEKTION_S', '', $role);
                    $strRole = preg_replace('/^0+/', '', $strRole);
                    if (!empty($strRole))
                    {
                        if (in_array($strRole, $arrClubIds))
                        {
                            $arrMembership[] = $strRole;
                        }
                    }
                }
            }
        }
        return $arrMembership;
    }

}
