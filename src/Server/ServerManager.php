<?php

/**
 * Swiss Alpine Club Login Client Bundle
 * OpenId Connect Login via https://sac-cas.ch for Contao Frontend and Backend
 *
 * @package Markocupic\SwissAlpineClubContaoLoginClientBundle
 * @author    Marko Cupic, Oberkirch
 * @license   MIT
 * @copyright 2020 Marko Cupic
 */

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Server;

use Markocupic\SwissAlpineClubContaoLoginClientBundle\Model\OidcServerModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Doctrine\DBAL\Driver\Connection;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use League\OAuth2\Client\Provider\GenericProvider;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class ServerManager
 * @package Markocupic\SwissAlpineClubContaoLoginClientBundle\Server
 */
class ServerManager
{
    /**
     * @var Connection $connection
     */
    protected $connection;

    /**
     * @var RouterInterface $router
     */
    protected $router;

    /**
     * @var ContaoFramework $framework
     */
    protected $framework;

    /**
     * ServerManager constructor.
     * @param Connection $connection
     * @param RouterInterface $router
     * @param ContaoFramework $framework
     */
    public function __construct(Connection $connection, RouterInterface $router, ContaoFramework $framework)
    {
        $this->connection = $connection;
        $this->router = $router;
        $this->framework = $framework;
    }

    /**
     * Find Login-Server
     * @param int $serverId
     * @return mixed
     */
    public function find(int $serverId)
    {
        if (!$this->framework->isInitialized())
        {
            $this->framework->initialize();
        }

        return OidcServerModel::findById($serverId);
    }

    /**
     * Generate return url
     * @param int $id
     * @return string
     */
    public function generateReturnUrl(int $id)
    {
        return $this->router->generate(
            'sac_oidc_auth',
            ['serverId' => $id],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }

    /**
     * Create OAuth2 Provider
     * @param OidcServerModel $server
     * @return GenericProvider
     */
    public function createOAuth2Provider(OidcServerModel $server): GenericProvider
    {
        return new GenericProvider([
            'clientId'                => $server->public_id,
            'clientSecret'            => $server->secret,
            'redirectUri'             => $this->generateReturnUrl($server->id),
            'urlAuthorize'            => $server->url_authorize,
            'urlAccessToken'          => $server->url_access_token,
            'urlResourceOwnerDetails' => $server->url_resource_owner_details,
        ]);
    }
}
