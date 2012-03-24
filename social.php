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
        if (empty($this->user)) {
            $this->element('p', array('class' => 'greeting'),
                           // TRANS: Message in sample plugin.
                           _m('You need to be logged in to view this page'));
            return;
        }

        $this->element('h2', null, sprintf(_m('%s, %d'), $this->gc->month->format('F'), $this->gc->month->format(Y)));
        $this->element('h3', null, _m('Posts per day'));

        $this->elementStart('table', array('class' => 'social_stats notices'));
        $this->element('caption', null, 'Posts per day');

        // Date iterator
        $i_date = clone($this->gc->month);

        // For each day in month, create a 2col table row with the date in the 1st column and the number of posts in the 2nd.
        while($i_date->format('m') == $this->gc->month->format('m')) {
            $this->elementStart('tr');
            $this->element('th', null, $i_date->format('Y-m-d'));
            $this->element('td', null, intval($this->gc->arr_notices[$i_date->format('Y-m-d')])); // intval change null into zeros for postless days
            $this->elementEnd('tr');
            $i_date->modify('+1 day');
        }
        $this->elementEnd('table');

        $this->element('h3', null, _m('Following trend'));

        // FIXME: Duplicate code. Merge this and the above (object agnostic)
        $this->elementStart('table', array('class' => 'social_stats following'));
        $this->element('caption', null, 'Posts per day');

        // Date iterator
        $i_date = clone($this->gc->month);

        // For each day in month, create a 2col table row with the date in the 1st column and the number of posts in the 2nd.
        $ttl_following = $this->gc->ttl_following;
        while($i_date->format('m') == $this->gc->month->format('m')) {
            $ttl_following += intval($this->gc->arr_following[$i_date->format('Y-m-d')]);
            $this->elementStart('tr');
            $this->element('th', null, $i_date->format('Y-m-d'));
            $this->element('td', null, $ttl_following); // intval change null into zeros for postless days
            $this->elementEnd('tr');
            $i_date->modify('+1 day');
        }
        $this->elementEnd('table');


        $this->element('h3', null, _m('Followers trend'));

        // FIXME: Duplicate code. Merge this and the above (object agnostic)
        $this->elementStart('table', array('class' => 'social_stats followers'));
        $this->element('caption', null, 'Posts per day');

        // Date iterator
        $i_date = clone($this->gc->month);

        // For each day in month, create a 2col table row with the date in the 1st column and the number of posts in the 2nd.
        $ttl_followers = $this->gc->ttl_followers;
        while($i_date->format('m') == $this->gc->month->format('m')) {
            $ttl_followers += intval($this->gc->arr_followers[$i_date->format('Y-m-d')]);
            $this->elementStart('tr');
            $this->element('th', null, $i_date->format('Y-m-d'));
            $this->element('td', null, $ttl_followers); // intval change null into zeros for postless days
            $this->elementEnd('tr');
            $i_date->modify('+1 day');
        }

        $this->elementEnd('table');

        // TODO: Clean this up (DateTime::modify()?)
        $this->gc->month->sub(new DateInterval('P1M')); // Previous Month
        $this->element('a', array('href' => '/social?month=' . $this->gc->month->format('Y-m')), 'Previous Month');
        $this->gc->month->add(new DateInterval('P2M')); // Next Month
        $this->element('a', array('href' => '/social?month=' . $this->gc->month->format('Y-m'), 'style' => 'float: right;'), 'Next Month');
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
