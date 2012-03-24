<?php
/**
 * Data class for Social Analytics stats
 *
 * PHP version 5
 *
 * @category Data
 * @package  StatusNet
 * @author   Stéphane Bérubé <chimo@chromic.org>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://github.com/chimo/social-analytics
 *
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009, StatusNet, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.     See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('STATUSNET')) {
    exit(1);
}

require_once INSTALLDIR . '/classes/Memcached_DataObject.php';

/**
 * Data class for Social Analytics stats
 *
 * We use the DB_DataObject framework for data classes in StatusNet. Each
 * table maps to a particular data class, making it easier to manipulate
 * data.
 *
 * Data classes should extend Memcached_DataObject, the (slightly misnamed)
 * extension of DB_DataObject that provides caching, internationalization,
 * and other bits of good functionality to StatusNet-specific data classes.
 *
 * @category Data
 * @package  StatusNet
 * @author   Stéphane Bérubé <chimo@chromic.org>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://github.com/chimo/SocialAnalytics
 *
 * @see      DB_DataObject
 */
class Social_analytics extends Memcached_DataObject
{
    public $__table = 'social_analytics'; // table name
    public $user_id;                      // int(4)  primary_key not_null
    public $last_updated;                 // datetime
    public $avg_posts_day;                // int(4)
    public $avg_follows_day;              // int(4)
    public $avg_followers_day;            // int(4)

    /**
     * Get an instance by key
     *
     * This is a utility method to get a single instance with a given key value.
     *
     * @param string $k Key to use to lookup (usually 'user_id' for this class)
     * @param mixed  $v Value to lookup
     *
     * @return Social_analytics object found, or null for no hits
     */
    function staticGet($k, $v=null)
    {
        return Memcached_DataObject::staticGet('Social_analytics', $k, $v);
    }

    /**
     * return key definitions for DB_DataObject
     *
     * DB_DataObject needs to know about keys that the table has, since it
     * won't appear in StatusNet's own keys list. In most cases, this will
     * simply reference your keyTypes() function.
     *
     * @return array list of key field names
     */
    function keys()
    {
        return array_keys($this->keyTypes());
    }

    /**
     * return key definitions for Memcached_DataObject
     *
     * Our caching system uses the same key definitions, but uses a different
     * method to get them. This key information is used to store and clear
     * cached data, so be sure to list any key that will be used for static
     * lookups.
     *
     * @return array associative array of key definitions, field name to type:
     *         'K' for primary key: for compound keys, add an entry for each component;
     *         'U' for unique keys: compound keys are not well supported here.
     */
    function keyTypes()
    {
        return array('user_id' => 'K');
    }

    /**
     * Magic formula for non-autoincrementing integer primary keys
     *
     * If a table has a single integer column as its primary key, DB_DataObject
     * assumes that the column is auto-incrementing and makes a sequence table
     * to do this incrementation. Since we don't need this for our class, we
     * overload this method and return the magic formula that DB_DataObject needs.
     *
     * @return array magic three-false array that stops auto-incrementing.
     */
    function sequenceKey()
    {
        return array(false, false, false);
    }


    /**
     * Get an instance by compound key
     *
     * This is a utility method to get a single instance with a given set of
     * key-value pairs. Usually used for the primary key for a compound key; thus
     * the name.
     *
     * @param array $kv array of key-value mappings
     *
     * @return Social_analytics object found, or null for no hits
     *
     */
    function pkeyGet($kv)
    {
        return Memcached_DataObject::pkeyGet('Social_analytics', $kv);
    }

    /**
     * Increment a user's greeting count and return instance
     *
     * This method handles the ins and outs of creating a new greeting_count for a
     * user or fetching the existing greeting count and incrementing its value.
     *
     * @param integer $user_id ID of the user to get a count for
     *
     * @return User_greeting_count instance for this user, with count already incremented.
     */
    static function dailyAvgs($user_id, $target_month=NULL)
    {
        $gc = new Social_analytics();

        $gc->user_id            = $user_id;
        $gc->last_updated       = date(DATE_ISO8601);
        $gc->avg_posts_day      = 0;
        $gc->avg_follows_day    = 0;
        $gc->avg_followers_day  = 0;

        if(!$target_month) {
            $target_month = new DateTime();
        }
        else {
            $target_month = new DateTime($target_month . '-01');
        }

        $gc->month = $target_month;

        $ttl_notices = 0;
        $gc->arr_notices = array();

        $notices = Memcached_DataObject::listGet('Notice', 'profile_id', array($user_id));
        foreach($notices[1] as $notice) {
            // Get date notice was created
            try {
                $date = new DateTime($notice->created);
            } catch(Exception $e) {
                // TODO: log/display error
                continue;
            }

            // Extract month
            if(($month = $date->format('Y-m')) === false) {
                // TODO: log/display error
                continue;
            }

            if($month == $target_month->format('Y-m')) {
                $gc->arr_notices[$date->format('Y-m-d')]++;
                $ttl_notices++;
            }
            else {
                continue;
            }
        }

        return $gc;
    }
}
