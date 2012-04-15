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
    /**
     * TODO: Document 
     */
    static function init($user_id, $target_month=NULL)
    {
        $sa = new Social_analytics();
        $sa->user_id = $user_id;
        $sa->month = (!$target_month) ? new DateTime('first day of this month') : new DateTime($target_month . '-01');

        $sa->ttl_notices = 0;

        // The list of graphs we'll be generating
        $sa->graphs = array(
            'trends' => array(),
            'hosts_you_are_following' => array(),
            'hosts_who_follow_you' => array(),
            'clients' => array(),
            'people_you_replied_to' => array(),
            'people_who_mentioned_you' => array()
        );

        // Initialize 'trends' table. We do this now since we know which rows we need in advance (all non-future days of month)
        $i_date = clone($sa->month);
        $today = new DateTime();
        while($i_date->format('m') == $sa->month->format('m')) {
            $sa->graphs['trends'][$i_date->format('Y-m-d')] = array('notices' => 0, 'following' => 0, 'followers' => 0, 'faves' => 0, 'o_faved' => 0);

            // Do not process dates from the future
            if($i_date->format('Y-m-d') == $today->format('Y-m-d')) {
                break;
            }            
            $i_date->modify('+1 day');
        }

        // Gather "Notice" information from db and place into appropriate arrays
        $notices = Memcached_DataObject::listGet('Notice', 'profile_id', array($user_id));
        $date_created = new DateTime();

        foreach($notices[$user_id] as $notice) {
            $date_created->modify($notice->created);

            if($date_created->format('Y-m') == $sa->month->format('Y-m')) {
                $sa->graphs['clients'][$notice->source]++;

                if($notice->reply_to) {
                    $reply_to = Notice::staticGet('id', $notice->reply_to);
                    $repliee = Profile::staticGet('id', $reply_to->profile_id);
                    $sa->graphs['people_you_replied_to'][$repliee->nickname]++;
                }

                $sa->graphs['trends'][$date_created->format('Y-m-d')]['notices']++;
                $sa->ttl_notices++;
            }
        }

        // Favored notices (both by 'you' and 'others')
        $sa->ttl_faves = 0;
        $sa->ttl_o_faved = 0;
        $faved = Memcached_DataObject::cachedQuery('Fave', 'SELECT * FROM fave');
        foreach($faved->_items as $fave) {
            $date_created->modify($fave->modified);
            if($date_created->format('Y-m') == $sa->month->format('Y-m')) {
                if($fave->user_id == $user_id) {
                    $sa->graphs['trends'][$date_created->format('Y-m-d')]['faves']++;
                    $sa->ttl_faves++;
                }
                else {
                    $sa->graphs['trends'][$date_created->format('Y-m-d')]['o_faved']++;
                    $sa->ttl_o_faved++;
                }
            }
        }

        // People who mentioned you
        $sa->ttl_mentions = 0;
        $mentions = Memcached_DataObject::listGet('Reply', 'profile_id', array($user_id));
        foreach($mentions[$user_id] as $mention) {
            $date_created->modify($mention->modified);
            if($date_created->format('Y-m') == $sa->month->format('Y-m')) {
                $notice = Notice::staticGet('id', $mention->notice_id);
                $profile = Profile::staticGet('id', $notice->profile_id);
                $sa->graphs['people_who_mentioned_you'][$profile->nickname]++;
                $sa->ttl_mentions++;
            }
        }

        // Hosts you are following
        $sa->ttl_following = 0;
        $arr_following = Memcached_DataObject::listGet('Subscription', 'subscriber', array($user_id));
        foreach($arr_following[$user_id] as $following) {
            // This is in my DB, but doesn't show up in my 'Following' total (???)
            if($following->subscriber == $following->subscribed) {
                continue;
            }

            $date_created->modify($following->created); // Convert string to DateTime

            if($date_created->format('Y-m') == $sa->month->format('Y-m')) {
                $sa->graphs['trends'][$date_created->format('Y-m-d')]['following']++;
                $profile = Profile::staticGet('id', $following->subscribed);

                $sa->graphs['hosts_you_are_following'][parse_url($profile->profileurl, PHP_URL_HOST)]++;
                $sa->ttl_following++;
            }
            elseif($date_created->format('Y-m') < $sa->month->format('Y-m')) {
                $profile = Profile::staticGet('id', $following->subscribed);
                $sa->graphs['hosts_you_are_following'][parse_url($profile->profileurl, PHP_URL_HOST)]++;
            }
        }

        // Hosts who follow you
        $sa->ttl_followers = 0;
        $followers = Memcached_DataObject::listGet('Subscription', 'subscribed', array($user_id));
        foreach($followers[$user_id] as $follower) {
            // This is in my DB, but doesn't show up in my 'Following' total (???)
            if($follower->subscriber == $follower->subscribed) {
                continue;
            }

            $date_created->modify($follower->created); // Convert string to DateTime

            if($date_created->format('Y-m') == $sa->month->format('Y-m')) {
                $sa->graphs['trends'][$date_created->format('Y-m-d')]['followers']++;
                $profile = Profile::staticGet('id', $follower->subscriber);

                $sa->graphs['hosts_who_follow_you'][parse_url($profile->profileurl, PHP_URL_HOST)]++;
                $sa->ttl_followers++;
            }
            elseif($date_created->format('Y-m') < $sa->month->format('Y-m')) {
                $profile = Profile::staticGet('id', $follower->subscriber);
                $sa->graphs['hosts_who_follow_you'][parse_url($profile->profileurl, PHP_URL_HOST)]++;
            }
        }

/*        foreach($sa->graphs['trends'] as &$day) {
            $day['followers'] += $ttl_followers;
            $ttl_followers = $day['followers'];

            $day['following'] += $ttl_following;
            $ttl_following = $day['following'];
} */

        return $sa;
    }
}
