<?php

if(defined('WP_CONTENT_DIR') && file_exists(WP_CONTENT_DIR.'/plugins/gravitate-cache/gravitate-cache-class.php'))
{
	include_once(WP_CONTENT_DIR.'/plugins/gravitate-cache/gravitate-cache-class.php');

	if(!empty(GRAVITATE_CACHE::$config['type']) && in_array(GRAVITATE_CACHE::$config['type'], array('memcache','memcached')))
	{
		include_once(WP_CONTENT_DIR.'/plugins/gravitate-cache/controllers/gravitate-object-cache-class-'.GRAVITATE_CACHE::$config['type'].'.php');
	}
}