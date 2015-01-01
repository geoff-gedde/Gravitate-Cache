<?php

/**************************************
** Created by Gravitate Cache Plugin **
**************************************/

if(defined('WP_CONTENT_DIR'))
{
	if(file_exists(WP_CONTENT_DIR.'/plugins/gravitate-cache/gravitate-cache-class.php'))
	{
		include(WP_CONTENT_DIR.'/plugins/gravitate-cache/gravitate-cache-class.php');

		if(class_exists('GRAVITATE_CACHE'))
		{
			$grav_cache = new GRAVITATE_CACHE();
		}
	}
}

