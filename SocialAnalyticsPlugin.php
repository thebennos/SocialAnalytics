<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009, StatusNet, Inc.
 *
 * Plugin to give insights into what's happening in your social network over time.
 *
 * PHP version 5
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
 *
 * @category  Plugin
 * @package   StatusNet
 * @author    Stéphane Bérubé <chimo@chromic.org>
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://github.com/chimo/SocialAnalytics
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Social Analytics main class
 *
 * Plugin to give insights into what's happening in your social network over time.
 *
 * @category  Plugin
 * @package   StatusNet
 * @author    Stéphane Bérubé <chimo@chromic.org>
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://github.com/chimo/social-analytics
 */
class SocialAnalyticsPlugin extends Plugin
{
    /**
     * Initializer for this plugin
     *
     * Plugins overload this method to do any initialization they need,
     * like connecting to remote servers or creating paths or so on.
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */
    function initialize()
    {
        return true;
    }

    /**
     * Cleanup for this plugin
     *
     * Plugins overload this method to do any cleanup they need,
     * like disconnecting from remote servers or deleting temp files or so on.
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */
    function cleanup()
    {
        return true;
    }

    function onEndShowScripts($action)
    {
        $action->script('http://www.openlayers.org/api/OpenLayers.js');
        $action->script($this->path('js/sa.js'));
        return true;
    }

    function onEndShowStyles($action)
    {
        $action->cssLink($this->path('css/visualize-light.css'));
        $action->cssLink($this->path('css/visualize.css'));
        $action->cssLink($this->path('css/sa.css'));
        return true;
    }    

    /**
     * Load related modules when needed
     *
     * Most non-trivial plugins will require extra modules to do their work. Typically
     * these include data classes, action classes, widget classes, or external libraries.
     *
     * This method receives a class name and loads the PHP file related to that class. By
     * tradition, action classes typically have files named for the action, all lower-case.
     * Data classes are in files with the data class name, initial letter capitalized.
     *
     * Note that this method will be called for *all* overloaded classes, not just ones
     * in this plugin! So, make sure to return true by default to let other plugins, and
     * the core code, get a chance.
     *
     * @param string $cls Name of the class to be loaded
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */
    function onAutoload($cls)
    {
        $dir = dirname(__FILE__);

        switch ($cls)
        {
        case 'SocialAction':
            include_once $dir . '/' . strtolower(mb_substr($cls, 0, -6)) . '.php';
            return false;
        case 'Social_analytics':
            include_once $dir . '/'.$cls.'.php';
            return false;
        default:
            return true;
        }
    }

    /**
     * Map URLs to actions
     *
     * This event handler lets the plugin map URLs on the site to actions (and
     * thus an action handler class). Note that the action handler class for an
     * action will be named 'FoobarAction', where action = 'foobar'. The class
     * must be loaded in the onAutoload() method.
     *
     * @param Net_URL_Mapper $m path-to-action mapper
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */
    function onRouterInitialized($m)
    {
        $m->connect('social',
                    array('action' => 'social'));
        return true;
    }

    /**
     * Modify the default menu to link to our custom action
     *
     * Using event handlers, it's possible to modify the default UI for pages
     * almost without limit. In this method, we add a menu item to the default
     * primary menu for the interface to link to our action.
     *
     * The Action class provides a rich set of events to hook, as well as output
     * methods.
     *
     * @param Action $action The current action handler. Use this to
     *                       do any output.
     *
     * @return boolean hook value; true means continue processing, false means stop.
     *
     * @see Action
     */
    function onEndPersonalGroupNav($action)
    {
        // common_local_url() gets the correct URL for the action name
        // we provide
        $action->menuItem(common_local_url('social'),
                          // TRANS: Menu item in sample plugin.
                          _m('Social Analytics'),
                          // TRANS: Menu item title in sample plugin.
                          _m('Social Analytics Stats'), false, 'nav_social');
        return true;
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'Social Analytics',
                            'version' => '0.2.0',
                            'author' => 'Stéphane Bérubé',
                            'homepage' => 'http://status.net/wiki/Plugin:SocialAnalytics',
                            'rawdescription' =>
                          // TRANS: Plugin description.
                            _m('Plugin to give insights into what\'s happening in your social network over time.'));
        return true;
    }
}
