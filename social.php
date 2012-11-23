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

// For SingleNoticeItem class
// require_once INSTALLDIR.'/actions/shownotice.php';

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
    public $user = null;
    public $sa   = null;

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
        $this->sa = Social_analytics::init($this->user->id, $_REQUEST['sdate'], $_REQUEST['edate']);

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

    function printNavigation($sdate, $edate, $location) {
        $url = common_local_url('social');

        $_sdate = clone($sdate);
        $_edate = clone($edate);

        $_sdate->modify('first day of last month');

        // Prev period
        $this->elementStart('ul', array('class' => 'sa-nav sa-nav-' . $location));
        $this->elementStart('li', array('class' => 'sa-prev'));
        $this->element('a', array('href' => $url . '?sdate=' . $_sdate->format('Y-m-d') . '&edate=' . $_sdate->modify('last day of this month')->format('Y-m-d')), 'Previous month');
        $this->elementEnd('li');

        // Custom date range link
        $this->elementStart('li', array('class' => 'sa-cust'));
        $this->element('a', array('href' => '#'), 'Custom date range');
        
        // Custom date range datepickers
        $this->elementStart('form', array('class' => 'sa-picker sa-picker-' . $location, 'method' => 'get', 'action' => $url));
        $this->elementStart('fieldset');
        $this->element('label', array('for' => 'sa-date-s-' . $location), 'Start date:');
        $this->element('input', array('id' => 'sa-date-s-' . $location, 'name' => 'sdate'));
        $this->element('br');
        $this->element('label', array('for' => 'sa-date-e-' . $location), 'End date:');
        $this->element('input', array('id' => 'sa-date-e-' . $location, 'name' => 'edate'));
        $this->element('input', array('type' => 'submit', 'id' => 'sa-submit'));
        $this->elementEnd('fieldset');
        $this->elementEnd('form');        
        
        $this->elementEnd('li');
        
        // Next period
        $_edate->modify('first day of next month');
        $this->elementStart('li', array('class' => 'sa-next'));
        $this->element('a', array('href' => $url . '?sdate=' . $_edate->format('Y-m-d') . '&edate=' . $_edate->modify('last day of this month')->format('Y-m-d')), 'Next month');
        $this->elementEnd('li');
        $this->elementEnd('ul');
    }


    function printGraph($name, $rows) {
        if(count($rows) < 1) { // Skip empty tables
            return;
        }

        // Wrapper
        $this->elementStart('div', array('class' => 'sa-wrap ' . $name . '_wrapper')); 

        // Title
        $this->element('h3', null, ucfirst(str_replace('_', ' ', _m($name))));

        // Graph container
        $this->element('div', array('class' => 'sa-graph ' . 'sa-' . $name . '-graph'));

        // Toggle link
        $this->element('a', array('class' => 'sa-toggle', 'href' => '#'), _m('Show "' . str_replace('_', ' ', $name) . '" table'));

        // Type of graph
        $type = 'sa-pie';
        if($name == 'trends') { $type = 'sa-line'; }
        
        // Table
        $this->elementStart('table', array('class' => 'sa-table ' . $type, 'id' => 'sa-' . $name));
        $this->element('caption', null, ucfirst(str_replace('_', ' ', _m($name))));
        $this->elementStart('thead');
        $this->elementStart('tr');
        $this->element('td');

        // FIXME: This is hackish
        if($name != 'trends') { // Ignore the 'trends' table since it's ok to have more than 10 rows
            $nb_rows = count($rows);
            uasort($rows, array($this, 'sortGraph'));

            if($nb_rows > 9) { // For other tables, limit the rows to 9 and shove everything else in 'other'
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
            $this->element('th', null, $date); // TODO: Rich data except for the 'trends' table
                                               //       This can be a profile, group, hashtag, host, client
                                               //       switch/case on $name would allow us to know what we're dealing with

            // Data cells
            foreach($data as $cell) {
                $this->elementStart('td');
                $this->element('span', null, count($cell));

                // Detailed information (appears onclick)
                if(count($cell) !== 0) {
                    $this->elementStart('ul');
                    switch(get_class(current($cell))) {
                        case 'Notice':
                            foreach($cell as $notice) {
                                $this->elementStart('li', array('class' => 'sa-notice ' . $notice->id));
                                $this->raw($notice->rendered);
                                $this->elementEnd('li');
                            }
                            break;
                        case 'Profile':
                            foreach($cell as $follower) {
                                $avatar = $follower->getAvatar(48);

                                // Building 'notice-like' HTML to display brief profile info
                                $this->elementStart('li', array('class' => 'notice')); // notice class is for CSS styles only
                                $this->elementStart('div', array('class' => 'entry-title'));
                                $this->elementStart('div', array('class' => 'author'));
                                $this->elementStart('span', array('class' => 'vcard author'));
                                $this->elementStart('a', array('class' => 'url', 'title' => $follower->nickname, 'href' => $follower->profileurl));
                                $this->element('img', array('width' => '48', 'height' => '48', 'alt' => $follower->nickname, 'class' => 'avatar photo', 'src' => $avatar->url));
                                $this->element('span', array('class' => 'fn'), $follower->nickname);
                                $this->elementEnd('a');
                                $this->elementEnd('span');
                                $this->elementEnd('div');
                                $this->element('p', array('class' => 'entry-content'), $follower->bio);
                                $this->elementEnd('div');
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

        $this->elementEnd('div'); // Wrapper
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
        $this->printNavigation($this->sa->sdate, $this->sa->edate, 't');

        // JS switcher
        $jslibs = array();
        if($fd = opendir(dirname(__FILE__) . '/js/lib')) {
            while (false !== ($entry = readdir($fd))) {
                if(preg_match('/\.js$/', $entry)) {
                    $jslibs[] = $entry;
                }
            }
            closedir($fd);

            $this->elementStart('select', array('id' => 'sa-js-switch', 'style' => (count($jslibs) > 1) ? '' : 'display: none;'));
            foreach($jslibs as $jslib) {
                $name = explode('.', $jslib);
                $this->element('option', array('value' => $jslib), $name[0]);
            }
            $this->elementEnd('select');
        }

        // Summary
		$this->elementStart('div', array('class' => 'sa-summary'));
        $this->element('h3', null, 'Summary');
        $this->element('p', 'During this time, you:');
        $this->elementStart('ul');

        $this->elementStart('li', array('class' => 'sa-posts'));
        $this->text('posted ' . $this->sa->ttl_notices . ' notice(s). (Daily avg: ' . round($this->sa->ttl_notices/count($this->sa->graphs['trends'])) . ')');
        $this->elementEnd('li');

        $this->elementStart('li', array('class' => 'sa-bookmarks'));
        $this->text('posted ' . $this->sa->ttl_bookmarks . ' bookmarks(s)');
        $this->elementEnd('li');

        $this->elementStart('li', array('class' => 'sa-follow'));
        $this->text('followed ' . $this->sa->ttl_following . ' new people');
        $this->elementEnd('li');

        $this->elementStart('li', array('class' => 'sa-follow'));
        $this->text('gained ' . $this->sa->ttl_followers . ' followers');
        $this->elementEnd('li');

        $this->elementStart('li', array('class' => 'sa-favs'));
        $this->text('favorited ' . $this->sa->ttl_faves . ' notices');
        $this->elementEnd('li');

        $this->elementStart('li', array('class' => 'sa-favs'));
        $this->text('had people favor your notices ' . $this->sa->ttl_o_faved . ' times');
        $this->elementEnd('li');

        $this->elementStart('li', array('class' => 'sa-replies'));
        $this->text('were mentioned ' . $this->sa->ttl_mentions . ' times, by ' . count($this->sa->graphs['people_who_mentioned_you']) . ' different people');
        $this->elementEnd('li');

        $this->elementStart('li', array('class' => 'sa-replies'));
        $this->text('replied to ' . count($this->sa->graphs['people_you_replied_to']) . ' people, for a total of ' . $this->sa->ttl_replies . ' replies');
        $this->elementEnd('li');        
        
        $this->elementEnd('ul');
		$this->elementEnd('div');

        // Graphs
        foreach($this->sa->graphs as $title => $graph) {
                $this->printGraph($title, $graph);
        }

        // If we have map data
        if(count($this->sa->map)) {
            // Wrapper
            $this->elementStart('div', array('class' => 'sa-map-wrap'));

            // Print Map title
            $this->element('h3', null, 'Location of new subscriptions');
            $this->element('p', null, 'Red: you started following, blue: started to follow you');

            // Map container
            $this->element('div', array('id' => 'sa-map'));

            // JS variables (used by js/sa.js)
            $this->inlineScript('var sa_following_coords = [' . implode(',', $this->sa->map['following']) . '];
                var sa_followers_coords = [' . implode(',', $this->sa->map['followers']) . '];');

            $this->elementEnd('div'); // Wrapper
        }

        // Navigation
        $this->printNavigation($this->sa->sdate, $this->sa->edate, 'b');
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
