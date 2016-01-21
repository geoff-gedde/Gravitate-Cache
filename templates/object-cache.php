<?php

if(defined('WP_CONTENT_DIR') && file_exists(WP_CONTENT_DIR.'/plugins/gravitate-cache/controllers/gravitate-cache-class.php'))
{
	include_once(WP_CONTENT_DIR.'/plugins/gravitate-cache/controllers/gravitate-cache-class.php');

	if(!empty(GRAV_CACHE::$settings['type']) && in_array(GRAV_CACHE::$settings['type'], array('memcache','memcached','redis')))
	{
		include_once(WP_CONTENT_DIR.'/plugins/gravitate-cache/controllers/gravitate-object-cache-class.php');
	}
}