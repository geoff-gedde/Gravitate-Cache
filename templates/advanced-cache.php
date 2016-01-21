<?php

/**************************************
** Created by Gravitate Cache Plugin **
**************************************/

if(defined('WP_CONTENT_DIR') && file_exists(WP_CONTENT_DIR.'/plugins/gravitate-cache/controllers/gravitate-cache-class.php'))
{
	include_once(WP_CONTENT_DIR.'/plugins/gravitate-cache/controllers/gravitate-cache-class.php');

	if(class_exists('GRAV_CACHE'))
	{
		GRAV_CACHE::init_page_cache();
	}
}

