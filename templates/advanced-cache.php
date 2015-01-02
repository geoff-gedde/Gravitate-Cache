<?php

/**************************************
** Created by Gravitate Cache Plugin **
**************************************/

if(defined('WP_CONTENT_DIR'))
{
	if(file_exists(WP_CONTENT_DIR.'/plugins/gravitate-cache/gravitate-cache-class.php'))
	{
		include_once(WP_CONTENT_DIR.'/plugins/gravitate-cache/gravitate-cache-class.php');

		if(class_exists('GRAVITATE_CACHE'))
		{
			$gravitate_cache_class = new GRAVITATE_CACHE();
		}
	}
}

