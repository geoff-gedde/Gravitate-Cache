<?php

/**************************************
** Created by Gravitate Cache Plugin **
**************************************/

if(defined('WP_CONTENT_DIR') && file_exists(WP_CONTENT_DIR.'/plugins/gravitate-cache/controllers/gravitate-db-class.php'))
{
	include_once(WP_CONTENT_DIR.'/plugins/gravitate-cache/controllers/gravitate-cache-class.php');
	include_once(WP_CONTENT_DIR.'/plugins/gravitate-cache/controllers/gravitate-db-class.php');

	if(class_exists('GRAV_CACHE_WPDB') && ((!$wpdb) || get_class($wpdb) != 'GRAV_CACHE_WPDB'))
	{
		$wpdb = new GRAV_CACHE_WPDB( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
	}
}