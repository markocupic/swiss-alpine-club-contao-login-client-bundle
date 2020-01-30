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

namespace Markocupic\SwissAlpineClubContaoLoginClientBundle\Model;

use Contao\System;
use Contao\Model;

/**
 * Class OidcServerModel
 * @package Markocupic\SwissAlpineClubContaoLoginClientBundle\Model
 *
 * @method static findById(int $serverId)
 * @property int $id
 * @property string $secret
 * @property string url_authorize
 * @property string url_access_token
 * @property string url_resource_owner_details
 * @property string login_scope
 *
 */
class OidcServerModel extends Model
{
	protected static $strTable = 'tl_oidc_server';

    /**
     * @return mixed
     */
	public function getRedirectUrl()
    {
        return System::getContainer()->get('router')
                ->generate('superlogin_auth_redirect', ['serverId' => $this->id]);
    }
}
