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
    static function init($user_id, $sdate=NULL, $edate=NULL)
    {
        $sa = new Social_analytics();

        $sa->user_id = $user_id;
        
        $sa->sdate = (!$sdate) ? new DateTime('first day of this month') : new DateTime($sdate);
        $sa->edate = (!$edate) ? new DateTime('last day of this month') : new DateTime($edate);

        // TODO: Handle cases where 'new DateTime()' fails (due to bad param)
        //       Print notice and use default (first/last day of this month        
        // TODO: Make sure sdate < edate
        
        $sa->ttl_notices = 0;
        $sa->ttl_replies = 0;
        $sa->ttl_bookmarks = 0;

        // The list of graphs we'll be generating
        $sa->graphs = array(
            'trends' => array(),
            'hosts_you_started_to_follow' => array(),
            'hosts_who_started_to_follow_you' => array(),
            'clients' => array(),
            'people_you_replied_to' => array(),
            'people_who_mentioned_you' => array(),
            'people_you_repeated' => array()
        );
        $sa->map = array();

        // Initialize 'trends' table. We do this now since we know which rows we need in advance (all non-future days of month)
        $i_date = clone($sa->sdate);
        $today = new DateTime();
        while($i_date <= $sa->edate) {
            $sa->graphs['trends'][$i_date->format('Y-m-d')] = array(
                'notices' => array(), 
                'following' => array(), 
                'followers' => array(), 
                'faves' => array(), 
                'o_faved' => array(), 
                'bookmarks' => array(),
                'repeats' => array()
            );

            // Do not process dates from the future
            if($i_date->format('Y-m-d') == $today->format('Y-m-d')) {
                break;
            }            
            $i_date->modify('+1 day');
        }

        // Gather "Notice" information from db and place into appropriate arrays
        $notices = Memcached_DataObject::cachedQuery('Notice', sprintf("SELECT * FROM notice 
            WHERE profile_id = %d AND created >= '%s' AND created <= '%s'",
            $user_id,
            $sa->sdate->format('Y-m-d'),
            $sa->edate->format('Y-m-d')));

        $date_created = new DateTime();

        foreach($notices->_items as $notice) {
            $date_created->modify($notice->created); // String to Date

            // Repeats
            if($notice->repeat_of) {
                $repeat = Notice::staticGet('id', $notice->repeat_of);
                $u_repeat = Profile::staticGet('id', $repeat->profile_id);

                if(!is_array($sa->graphs['people_you_repeated'][$u_repeat->nickname])) {
                    $sa->graphs['people_you_repeated'][$u_repeat->nickname] = array('notices' => array());
                }
                $sa->graphs['people_you_repeated'][$u_repeat->nickname]['notices'][] = $notice;
                $sa->graphs['trends'][$date_created->format('Y-m-d')]['repeats'][] = $notice;
            }

            // Clients
            if(!is_array($sa->graphs['clients'][$notice->source])) {
                $sa->graphs['clients'][$notice->source] = array('clients' => array());
            }
            $sa->graphs['clients'][$notice->source]['clients'][] = $notice;

            // Replies
            if($notice->reply_to) {
                $reply_to = Notice::staticGet('id', $notice->reply_to);
                $repliee = Profile::staticGet('id', $reply_to->profile_id);

                if(!is_array($sa->graphs['people_you_replied_to'][$repliee->nickname])) {
                    $sa->graphs['people_you_replied_to'][$repliee->nickname] = array('notices' => array());
                }
                $sa->graphs['people_you_replied_to'][$repliee->nickname]['notices'][] = $notice;

                $sa->ttl_replies++;
            }

            // Bookmarks
            if($notice->object_type == 'http://activitystrea.ms/schema/1.0/bookmark') { // FIXME: Matching just the type ('bookmark') is probably more future-proof
                $sa->graphs['trends'][$date_created->format('Y-m-d')]['bookmarks'][] = $notice;
                $sa->ttl_bookmarks++;
            }

            // Notices
            $sa->graphs['trends'][$date_created->format('Y-m-d')]['notices'][] = $notice;
            $sa->ttl_notices++; // FIXME: Do we want to include bookmarks with notices now that we have a 'bookmarks' trend?
        }

        // Favored notices (both by 'you' and 'others')
        $sa->ttl_faves = 0;
        $sa->ttl_o_faved = 0;
        $faved = Memcached_DataObject::cachedQuery('Fave', sprintf("SELECT * FROM fave 
            WHERE modified >= '%s' AND modified <= '%s'", 
            $sa->sdate->format('Y-m-d'), 
            $sa->edate->format('Y-m-d')));
            
        foreach($faved->_items as $fave) {
            $date_created->modify($fave->modified); // String to Date
                
            $notice = Notice::staticGet('id', $fave->notice_id);

            // User's faves
            if($fave->user_id == $user_id) {
                $sa->graphs['trends'][$date_created->format('Y-m-d')]['faves'][] = $notice;
                $sa->ttl_faves++;
            }
            else { // User's notices favored by others
                $sa->graphs['trends'][$date_created->format('Y-m-d')]['o_faved'][] = $notice;
                $sa->ttl_o_faved++;
            }
        }

        // People who mentioned you
        $sa->ttl_mentions = 0;
        $mentions = Memcached_DataObject::listGet('Reply', 'profile_id', array($user_id));
        foreach($mentions[$user_id] as $mention) {
            $date_created->modify($mention->modified);
            if($date_created >= $sa->sdate && $date_created <= $sa->edate) {
                $notice = Notice::staticGet('id', $mention->notice_id);
                $profile = Profile::staticGet('id', $notice->profile_id);

                if(!is_array($sa->graphs['people_who_mentioned_you'][$profile->nickname])) {
                    $sa->graphs['people_who_mentioned_you'][$profile->nickname] = array('notices' => array());
                }
                $sa->graphs['people_who_mentioned_you'][$profile->nickname]['notices'][] = $notice;

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

            if($date_created >= $sa->sdate && $date_created <= $sa->edate) {
                $profile = Profile::staticGet('id', $following->subscribed);
                $sa->graphs['trends'][$date_created->format('Y-m-d')]['following'][] = $profile;

                if(!is_null($profile->lat) && !is_null($profile->lon)) {
                    $sa->map['following'][$profile->nickname] = array('lat' => $profile->lat, 'lon' => $profile->lon);
                }

                $hst = parse_url($profile->profileurl, PHP_URL_HOST);
                
                if(!is_array($sa->graphs['hosts_you_started_to_follow'][$hst])) {
                    $sa->graphs['hosts_you_started_to_follow'][$hst] = array('host' => array());
                }
                $sa->graphs['hosts_you_started_to_follow'][$hst]['host'][] = $profile;

                $sa->ttl_following++;
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

            if($date_created >= $sa->sdate && $date_created <= $sa->edate) {
                $profile = Profile::staticGet('id', $follower->subscriber);
                $sa->graphs['trends'][$date_created->format('Y-m-d')]['followers'][] = $profile;

                if(!is_null($profile->lat) && !is_null($profile->lon)) {
                    $sa->map['followers'][$profile->nickname] = array('lat' => $profile->lat, 'lon' => $profile->lon);
                }

                $hst = parse_url($profile->profileurl, PHP_URL_HOST);

                if(!is_array($sa->graphs['hosts_who_started_to_follow_you'][$hst])) {
                    $sa->graphs['hosts_who_started_to_follow_you'][$hst] = array('host' => array());
                }

                $sa->graphs['hosts_who_started_to_follow_you'][$hst]['host'][] = $profile;

                $sa->ttl_followers++;
            }
        }

        return $sa;
    }
}
