<?php
/**
 * Plugin to give insights into what's happening in your social network over time.
 *
 * PHP version 5
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Stéphane Bérubé <chimo@chromic.org>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://github.com/chimo/SocialAnalytics
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Plugin to give insights into what's happening in your social network over time.
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Stéphane Bérubé <chimo@chromic.org>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://github.com/chimo/SocialAnalytics
 */
class SocialAction extends Action
{
    var $user = null;
    var $sa   = null;

    // TODO: Document
    function sortGraph($a, $b) {
        $c = reset($a);
        $d = reset($b);

        if(count($c) == count($d)) {
            return ;
        }

        // DESC
        return ($c > $d) ? -1 : 1;
    }

    /**
     * Take arguments for running
     *
     * This method is called first, and it lets the action class get
     * all its arguments and validate them. It's also the time
     * to fetch any relevant data from the database.
     *
     * Action classes should run parent::prepare($args) as the first
     * line of this method to make sure the default argument-processing
     * happens.
     *
     * @param array $args $_REQUEST args
     *
     * @return boolean success flag
     */
    function prepare($args)
    {
        parent::prepare($args);

        $this->user = common_current_user();

        // Custom date range
        $this->sa = Social_analytics::init($this->user->id, $_REQUEST['sdate'], $_REQUEST['edate'], $_REQUEST['period']);

        return true;
    }

    /**
     * Handle request
     *
     * This is the main method for handling a request. Note that
     * most preparation should be done in the prepare() method;
     * by the time handle() is called the action should be
     * more or less ready to go.
     *
     * @param array $args $_REQUEST args; handled in prepare()
     *
     * @return void
     */
    function handle($args)
    {
        parent::handle($args);
        if (!common_logged_in()) {
            // TRANS: Error message displayed when trying to perform an action that requires a logged in user.
            $this->clientError(_('Not logged in.'));
            return;
        } else if (!common_is_real_login()) {
            // Cookie theft means that automatic logins can't
            // change important settings or see private info, and
            // _all_ our settings are important
            common_set_returnto($this->selfUrl());
            $user = common_current_user();
            if (Event::handle('RedirectToLogin', array($this, $user))) {
                common_redirect(common_local_url('login'), 303);
            }
        } else {
            $this->showPage();
        }
    }

    /**
     * Title of this page
     *
     * Override this method to show a custom title.
     *
     * @return string Title of the page
     */
    function title()
    {
        return _m('Social Analytics');
    }

    function printCustomDateForm() {
        // Custom date range datepickers
        $this->elementStart('form', array('class' => 'social_date_picker', 'method' => 'get', 'action' => $url));
        $this->elementStart('fieldset');

        // Radio buttons
        $this->elementStart('div');
        $this->element('h3', null, 'Select a Period');
        $radios = array('day', 'week', 'month' ,'year', 'range');
        if(!(in_array($this->sa->period, $radios))) {
            $this->sa->period = 'month';
        }

        foreach($radios as $radio) {
            if($this->sa->period == $radio) {
                $this->element('input', array('id' => 'social_' . $radio, 'type' => 'radio', 'name' => 'period', 'checked' => 'checked', 'value' => $radio));
            }
            else {
                $this->element('input', array('id' => 'social_' . $radio, 'type' => 'radio', 'name' => 'period', 'value' => $radio));
            }
            $this->element('label', array('for' => 'social_' . $radio), ucfirst($radio));
        }

        $this->elementEnd('div');

        // jQueryUI calendar container
        $this->elementStart('div', array('class' => 'social_sdate_cal'));
        $this->element('h3', null, 'Select a Date');
        $this->elementEnd('div');

        $this->element('div', array('class' => 'social_edate_cal'));

        /* Period */
        $this->elementStart('div', array('class' => 'social_period'));
        $this->element('h3', null, 'Confirm');
        // Form input
        $this->element('label', array('for' => 'social_start_date'), 'Start date:');
        $this->element('input', array('id' => 'social_start_date', 'name' => 'sdate'));
        $this->element('br');
        $this->element('label', array('for' => 'social_end_date'), 'End date:');
        $this->element('input', array('id' => 'social_end_date', 'name' => 'edate'));
        $this->element('input', array('type' => 'submit', 'id' => 'social_submit_date'));
        $this->elementEnd('div');
        
        $this->elementEnd('fieldset');
        $this->elementEnd('form');        
    }

    function printNavigation($sdate, $edate, $location) {
        $interval = $sdate->diff($edate);
    	$url = common_local_url('social');

        // Prev period
        $this->elementStart('ul', array('class' => "social_nav $location"));
        $this->elementStart('li', array('class' => 'prev'));
        $this->element('a', array('href' => $url . '?sdate=' . $sdate->sub($interval)->format('Y-m-d') . '&edate=' . $edate->sub($interval)->format('Y-m-d')), _m('Previous Period'));
        $this->elementEnd('li');

        $sdate->add($interval);
        $edate->add($interval);
        
        // Custom date range link
        $this->elementStart('li', array('class' => 'cust'));
        $this->element('a', array('href' => '#'), 'Custom date range');
        
        
        $this->elementEnd('li');
        
        // Next period
        $this->elementStart('li', array('class' => 'next'));
        $this->element('a', array('href' => $url . '?sdate=' . $sdate->add($interval)->format('Y-m-d')  . '&edate=' . $edate->add($interval)->format('Y-m-d')) , _m('Next Period'));
        $this->elementEnd('li');
        $this->elementEnd('ul');
    }


    function printGraph($name, $rows) {
        if(count($rows) < 1) { // Skip empty tables
            return;
        } 

        // Title
        $this->element('h3', null, ucfirst(str_replace('_', ' ', _m($name))));

        // Graph container
        $this->element('div', array('class' => 'social_graph ' . $name . '_graph'));

        // Toggle link
        $this->element('a', array('class' => 'toggleTable', 'href' => '#'), _m('Show "' . str_replace('_', ' ', $name) . '" table'));

        // Type of graph
        $type = 'social_pie';
        if($name == 'trends') { $type = 'social_line'; }
        
        // Table
        $this->elementStart('table', array('class' => 'social_table ' . $type, 'id' => $name . '_table'));
        $this->element('caption', null, ucfirst(str_replace('_', ' ', _m($name))));
        $this->elementStart('thead');
        $this->elementStart('tr');
        $this->element('td');

        // FIXME: This is hackish
        if($name != 'trends') { // Ignore the 'trends' table since it's ok to have more than 10 rows
            $nb_rows = count($rows);

            if($nb_rows > 9) { // For other tables, limit the rows to 9 and shove everything else in 'other'
                uasort($rows, array($this, 'sortGraph'));

                $other = array();
                $keys = array_keys($rows);
                for($i=9; $i<$nb_rows; $i++) {
                    $key = array_keys($rows[$keys[$i]]);
                    $other[$key[0]] += count($rows[$keys[$i]][$key[0]]); // Sum of items in 'other'
                    unset($rows[$keys[$i]]); // Remove original item from array
                }
                $rows['other'] = $other; // Add 'other' to array
            }
        }

        // Top headers
        $foo = reset($rows);
        foreach($foo as $bar => $meh) {
            $this->element('th', null, $bar);
        }
        $this->elementEnd('tr');
        $this->elementEnd('thead');

        // Data rows
        $this->elementStart('tbody');
        foreach($rows as $date => $data) {
            $this->elementStart('tr');
            $this->element('th', null, $date);

            // Data cells
            foreach($data as $cell) {
                $this->elementStart('td');
                $this->text(count($cell));

                // Detailed information (appears onclick)
                if(count($cell) !== 0) {
                    $this->elementStart('ul');
                    switch(get_class(current($cell))) {
                        case 'Notice':
                            foreach($cell as $notice) {
                                $this->elementStart('li');
                                $this->raw($notice->rendered);
                                $this->elementEnd('li');
                            }
                            break;
                        case 'Profile':
                            foreach($cell as $follower) {
                                $this->elementStart('li');
                                $this->text($follower->nickname);
                                $this->elementEnd('li');
                            }
                            break;
                    }
                    $this->elementEnd('ul');
                }

                $this->elementEnd('td');
            }
            $this->elementEnd('tr');
        }
        $this->elementEnd('tbody');
        $this->elementEnd('table');
    }

    /**
     * Show content in the content area
     *
     * The default StatusNet page has a lot of decorations: menus,
     * logos, tabs, all that jazz. This method is used to show
     * content in the content area of the page; it's the main
     * thing you want to overload.
     *
     * This method also demonstrates use of a plural localized string.
     *
     * @return void
     */
    function showContent()
    {
        // Month
        $this->element('h2', null, sprintf(_m('From %s to %s'), $this->sa->sdate->format('Y-m-d'), $this->sa->edate->format('Y-m-d')));

        // Navigation
        $this->printNavigation($this->sa->sdate, $this->sa->edate, 'social_nav_top');

        // Custom Date Form
        $this->printCustomDateForm();

        // Summary
        $this->element('h3', null, 'Summary');
        $this->element('p', array('class' => 'summary'), 'During this time, you:');
        $this->elementStart('ul', array('class' => 'summary'));

        $this->elementStart('li');
        $this->text('posted ' . $this->sa->ttl_notices . ' notice(s). (Daily avg: ' . round($this->sa->ttl_notices/count($this->sa->graphs['trends'])) . ')');
        $this->elementEnd('li');

        $this->elementStart('li');
        $this->text('posted ' . $this->sa->ttl_bookmarks . ' bookmarks(s)');
        $this->elementEnd('li');

        $this->elementStart('li');
        $this->text('followed ' . $this->sa->ttl_following . ' new people');
        $this->elementEnd('li');

        $this->elementStart('li');
        $this->text('gained ' . $this->sa->ttl_followers . ' followers');
        $this->elementEnd('li');

        $this->elementStart('li');
        $this->text('favorited ' . $this->sa->ttl_faves . ' notices');
        $this->elementEnd('li');

        $this->elementStart('li');
        $this->text('had people favor your notices ' . $this->sa->ttl_o_faved . ' times');
        $this->elementEnd('li');

        $this->elementStart('li');
        $this->text('were mentioned ' . $this->sa->ttl_mentions . ' times, by ' . count($this->sa->graphs['people_who_mentioned_you']) . ' different people');
        $this->elementEnd('li');

        $this->elementStart('li');
        $this->text('replied to ' . count($this->sa->graphs['people_you_replied_to']) . ' people, for a total of ' . $this->sa->ttl_replies . ' replies');
        $this->elementEnd('li');        
        
        $this->elementEnd('ul');

        // Graphs
        foreach($this->sa->graphs as $title => $graph) {
                $this->printGraph($title, $graph);
        }

        // If we have map data
        if(count($this->sa->map)) {
            // Print Map title
            $this->element('h3', null, 'Location of new subscriptions');
            $this->element('p', null, 'Red: you started following, blue: started to follow you');

            // Map container
            $this->element('div', array('id' => 'mapdiv'));

            // JS variables (used by js/map.js)
            $this->inlineScript('var sa_following_coords = ' . $this->getCoords('following') . ';
                var sa_followers_coords = ' . $this->getCoords('followers') . ';');
        }

        // Navigation
        $this->printNavigation($this->sa->sdate, $this->sa->edate, 'social_nav_bottom');
    }

    function getCoords($name) {
        $markers = '[';

        // FIXME: Just store this in JS notation in $this->sa->map['following']['nickname'] to being with
        foreach($this->sa->map[$name] as $nickname => $coords) {
            $markers .= '{ lon: "' . $coords['lon'] . '", lat: "'  . $coords['lat'] . '", nickname: "' . $nickname . '"},';
        }

        $markers = rtrim($markers, ',');
        return $markers . ']';
    }

    /**
     * Return true if read only.
     *
     * Some actions only read from the database; others read and write.
     * The simple database load-balancer built into StatusNet will
     * direct read-only actions to database mirrors (if they are configured),
     * and read-write actions to the master database.
     *
     * This defaults to false to avoid data integrity issues, but you
     * should make sure to overload it for performance gains.
     *
     * @param array $args other arguments, if RO/RW status depends on them.
     *
     * @return boolean is read only action?
     */
    function isReadOnly($args)
    {
        return true;
    }
}
