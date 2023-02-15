<?php
/*
Plugin Name: Orbisius SEO Editor
Plugin URI: https://orbisius.com/products/wordpress-plugins/orbisius-seo-editor/
Description: Allows you to bulk update meta titles, descriptions summary of pages, posts and WooCommerce products
Version: 1.0.2
Author: Svetoslav Marinov (Slavi)
Author URI: https://orbisius.com
*/

/*  Copyright 2012-2050 Svetoslav Marinov (Slavi) <slavi@orbisius.com>

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// Don't even bother loading, if it's WP heartbeat ajax request.
if ((!empty($_REQUEST['page']) && $_REQUEST['page'] == 'heartbeat')
    ||
    (!empty($_REQUEST['action']) && $_REQUEST['action'] == 'heartbeat')
) {
    return;
}

define('ORBISIUS_SEO_EDITOR_BASE_DIR', __DIR__);
define('ORBISIUS_SEO_EDITOR_BASE_PLUGIN', __FILE__);
define('ORBISIUS_SEO_EDITOR_PLUGIN_SLUG', str_replace('.php', '', basename(ORBISIUS_SEO_EDITOR_BASE_PLUGIN)));

if (is_admin()) {
	require_once( ORBISIUS_SEO_EDITOR_BASE_DIR . '/lib/util.php' );
	require_once( ORBISIUS_SEO_EDITOR_BASE_DIR . '/lib/request.php' );
	require_once( ORBISIUS_SEO_EDITOR_BASE_DIR . '/lib/string_util.php' );
	require_once( ORBISIUS_SEO_EDITOR_BASE_DIR . '/lib/result.php' );
	require_once( ORBISIUS_SEO_EDITOR_BASE_DIR . '/lib/plugin_manager.php' );

    // Let's load the files only when the plugin needs them.
	$req_obj = Orbisius_SEO_Editor_Request::getInstance();

    if ($req_obj->isPluginRequest()) {
        require_once( ORBISIUS_SEO_EDITOR_BASE_DIR . '/lib/csv.php' );
        require_once( ORBISIUS_SEO_EDITOR_BASE_DIR . '/lib/media.php' );
        require_once( ORBISIUS_SEO_EDITOR_BASE_DIR . '/lib/html.php' );
        require_once( ORBISIUS_SEO_EDITOR_BASE_DIR . '/lib/file.php' );
        require_once( ORBISIUS_SEO_EDITOR_BASE_DIR . '/lib/debug.php' );
        require_once( ORBISIUS_SEO_EDITOR_BASE_DIR . '/lib/plugin_addon_base.php' );
    }

    // Let's still load the admin so the menu items shows up
	require_once(ORBISIUS_SEO_EDITOR_BASE_DIR . '/admin/admin.php');
}
