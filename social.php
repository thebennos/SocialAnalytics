<?php
/**
 * Plugin to give insights into what's happening in your social network over time.
 *
 * PHP version 5
 *
 * @category Sample
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
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
    var $gc   = null;

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

        if (!empty($this->user)) {
            $this->gc = Social_analytics::dailyAvgs($this->user->id, $_REQUEST['month']);
        }

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
        
        $this->showPage();
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

    function printNavigation($current_month) {
        $month = clone($current_month);

        $this->elementStart('ul', array('class' => 'social_nav'));
        $this->elementStart('li', array('class' => 'prev'));
        $this->element('a', array('href' => '/social?month=' . $month->modify('-1 month')->format('Y-m')), _m('Previous Month'));
        $this->elementEnd('li');

        // Don't generate a 'next' link if the next month is in the future
        $today = new DateTime();
//        if($today->format('Y-m') >= $month->modify('+2 month')->format('Y-m')) {
        if($today >= $month->modify('+2 month')) {
            $this->elementStart('li', array('class' => 'next'));
            $this->element('a', array('href' => '/social?month=' . $month->format('Y-m')), _m('Next Month'));
            $this->elementEnd('li');
        }
        $this->elementEnd('ul');
    }

    function printGraph($name, $headers, $rows) {
        // Title
        $this->element('h3', null, ucfirst(str_replace('_', ' ', _m($name))));

        // Graph container
        $this->element('div', array('class' => 'social_graph ' . $name . '_graph'));

        // Toggle link
        $this->element('a', array('class' => 'toggleTable', 'href' => '#'), _m('Show ' . str_replace('_', ' ', $name) . ' table'));

        // Data table
        $this->elementStart('table', array('class' => 'social_table ' . $name . '_table'));
        $this->elementStart('tr');
        $this->element('td');
        // First row (headers)
        foreach($headers as $header) {
            $this->element('th', null, $header);
        }
        // Data rows
        $this->elementEnd('tr');
        foreach($rows as $row) {
            $this->elementStart('tr');
            $this->element('th', null, array_shift($row)); // First cell is a header
            foreach($row as $cell) {
                $this->element('td', null, $cell); // The rest are data
            }
            $this->elementEnd('tr');
        }
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
        // Display "error" message on anonymous views
        if (empty($this->user)) {
            $this->element('p', array('class' => 'greeting'),
                           // TRANS: Message in sample plugin.
                           _m('You need to be logged in to view this page'));
            return;
        }

        // Print month and month navigation
        $this->element('h2', null, sprintf(_m('%s, %d'), $this->gc->month->format('F'), $this->gc->month->format(Y)));
        $this->printNavigation($this->gc->month);

        // Date iterator
        // TODO: Consider doing this in Social_analytics.php and have the data properly formatted once we enter this method
        $i_date = clone($this->gc->month);
        $ttl_following = $this->gc->ttl_following;
        $ttl_followers = $this->gc->ttl_followers;
        $avg_notices = 0;

        $arr_rows = array();
        $today = new DateTime();
        while($i_date->format('m') == $this->gc->month->format('m')) {
            if($i_date->format('Y-m-d') == $today->format('Y-m-d')) {
                break;
            }

            $ttl_following += intval($this->gc->arr_following[$i_date->format('Y-m-d')]);
            $ttl_followers += intval($this->gc->arr_followers[$i_date->format('Y-m-d')]);
            $avg_notices += intval($this->gc->arr_notices[$i_date->format('Y-m-d')]);

            $arr_rows[] = array(
                $i_date->format('Y-m-d'), 
                intval($this->gc->arr_notices[$i_date->format('Y-m-d')]),
                $ttl_following,
                $ttl_followers
            );

            $i_date->modify('+1 day');
        }

        // FIXME: Potentially dividing by zero, the universe could implode.
        $avg_notices = round($avg_notices/count($arr_rows));
        $this->printGraph('trends', array('Notices', 'Following', 'Followers'), $arr_rows);
        $this->element('p', null, _m("Daily average for this month: $avg_notices"));

        // Following Hosts
        // TODO: Consider doing this in Social_analytics.php and have the data properly formatted once we enter this method
        $arr_rows = array();
        foreach($this->gc->arr_following_hosts as $host => $count) {
            $arr_rows[] = array($host, $count);
        }

        $this->printGraph('hosts_following', array('nb'), $arr_rows);

        // Followers Hosts
        // TODO: Consider doing this in Social_analytics.php and have the data properly formatted once we enter this method
        $arr_rows = array();
        foreach($this->gc->arr_followers_hosts as $host => $count) {
            $arr_rows[] = array($host, $count);
        }
        
        $this->printGraph('hosts_followers', array('nb'), $arr_rows);

        // Clients
        // TODO: Consider doing this in Social_analytics.php and have the data properly formatted once we enter this method
        $arr_rows = array();
        foreach($this->gc->arr_clients as $client => $count) {
            $arr_rows[] = array($client, $count);
        }

        $this->printGraph('clients', array('nb'), $arr_rows);

        $this->printNavigation($this->gc->month);
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
