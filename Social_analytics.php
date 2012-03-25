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
        $gc->user_id = $user_id;

        if(!$target_month) {
            $target_month = new DateTime('first day of this month');
        }
        else {
            $target_month = new DateTime($target_month . '-01');
        }

        $gc->month = $target_month;

        $ttl_notices = 0;
        $gc->arr_notices = array();

        $notices = Memcached_DataObject::listGet('Notice', 'profile_id', array($user_id));
        $date_created = new DateTime();
        foreach($notices[1] as $notice) {
            // Get date notice was created
            try {
                $date_created->modify($notice->created);
            } catch(Exception $e) {
                // TODO: log/display error
                continue;
            }

            if($date_created->format('Y-m') == $target_month->format('Y-m')) {
                $gc->arr_notices[$date_created->format('Y-m-d')]++;
                $ttl_notices++;
            }
            else {
                continue;
            }
        }

        // FIXME: Copy/paste is bad, mmkay? (Create object-agnostic version of this and above and below)
        $ttl_following = 0;
        $gc->arr_following_hosts = array();
        $arr_following = Memcached_DataObject::listGet('Subscription', 'subscriber', array($user_id));
        foreach($arr_following[1] as $following) {
            // This is in my DB, but doesn't show up in my 'Following' total (???)
            if($following->subscriber == $following->subscribed) {
                continue;
            }

            try {
                $date_created->modify($following->created);
            } catch(Exception $e) {
                // TODO: log/display error
                continue;
            }

            if($date_created->format('Y-m') == $target_month->format('Y-m')) {
                $gc->arr_following[$date_created->format('Y-m-d')]++;
                $profile = Profile::staticGet('id', $following->subscribed);

                $gc->arr_following_hosts[parse_url($profile->profileurl, PHP_URL_HOST)]++;
            }
            elseif($date_created->format('Y-m') < $target_month->format('Y-m')) {
                $ttl_following++;
                $profile = Profile::staticGet('id', $following->subscribed);

                $gc->arr_following_hosts[parse_url($profile->profileurl, PHP_URL_HOST)]++;
                continue; // NOTE: Why is this here?
            }
        }

        $gc->ttl_following = $ttl_following;

        // FIXME: Redundant code (see above)
        $ttl_followers = 0;
        $gc->arr_followers_hosts = array();
        $arr_followers = Memcached_DataObject::listGet('Subscription', 'subscribed', array($user_id));
        foreach($arr_followers[1] as $follower) {
            // This is in my DB, but doesn't show up in my 'Following' total (???)
            if($follower->subscriber == $follower->subscribed) {
                continue;
            }

            try {
                $date_created->modify($follower->created);
            } catch(Exception $e) {
                // TODO: log/display error
                continue;
            }

            if($date_created->format('Y-m') == $target_month->format('Y-m')) {
                $gc->arr_followers[$date_created->format('Y-m-d')]++;
                $profile = Profile::staticGet('id', $follower->subscriber);

                $gc->arr_followers_hosts[parse_url($profile->profileurl, PHP_URL_HOST)]++;
            }
            elseif($date_created->format('Y-m') < $target_month->format('Y-m')) {
                $ttl_followers++;
                $profile = Profile::staticGet('id', $follower->subscriber);

                $gc->arr_followers_hosts[parse_url($profile->profileurl, PHP_URL_HOST)]++;
                continue; // NOTE: Why is this here?
            }
        }

        $gc->ttl_followers = $ttl_followers;
        return $gc;
    }
}
