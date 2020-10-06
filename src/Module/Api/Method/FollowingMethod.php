<?php
/*
 * vim:set softtabstop=4 shiftwidth=4 expandtab:
 *
 *  LICENSE: GNU Affero General Public License, version 3 (AGPL-3.0-or-later)
 * Copyright 2001 - 2020 Ampache.org
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 */

declare(strict_types=0);

namespace Ampache\Module\Api\Method;

use Ampache\Config\AmpConfig;
use Ampache\Model\User;
use Ampache\Module\Api\Api;
use Ampache\Module\Api\Json_Data;
use Ampache\Module\Api\Xml_Data;
use Ampache\Module\System\Session;

final class FollowingMethod
{
    /**
     * following
     * MINIMUM_API_VERSION=380001
     * CHANGED_IN_API_VERSION=400004
     *
     * Get users followed by the user
     * Error when user not found or no followers
     *
     * @param array $input
     * username = (string) $username
     * @return boolean
     */
    public static function following($input)
    {
        if (!AmpConfig::get('sociable')) {
            Api::message('error', T_('Access Denied: social features are not enabled.'), '403', $input['api_format']);

            return false;
        }
        if (!Api::check_parameter($input, array('username'), 'following')) {
            return false;
        }
        $username = $input['username'];
        if (!empty($username)) {
            $user = User::get_from_username($username);
            if ($user !== null) {
                $users = $user->get_following();
                if (!count($users)) {
                    Api::message('error', 'User `' . $username . '` does not follow anyone.', '404', $input['api_format']);
                } else {
                    debug_event('api.class', 'User is following:  ' . print_r($users), 1);
                    ob_end_clean();
                    switch ($input['api_format']) {
                        case 'json':
                            echo JSON_Data::users($users);
                            break;
                        default:
                            echo XML_Data::users($users);
                    }
                }
            } else {
                debug_event('api.class', 'User `' . $username . '` cannot be found.', 1);
                Api::message('error', 'User `' . $username . '` cannot be found.', '404', $input['api_format']);
            }
        }
        Session::extend($input['auth']);

        return true;
    }
}
