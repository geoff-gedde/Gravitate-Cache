<?php

/**************************************
** Created by Gravitate Cache Plugin **
**************************************/

$gravitate_cache_config = array(
	'page_enabled' => false,
	'database_enabled' => false,
	'object_enabled' => false,
	'browser_enabled' => false,
	'type' => 'auto', 						/*  type = auto|disk|memcache|memcached  */
	'server' => '127.0.0.1:11211',			/*  only used for memcache|memcached     */
	'preload_urls' => 'menus', 				/*  preload_urls = none|menus|pages  */
	'excluded_urls' => 'wp-.*\\.php'		/*  Specify which Urls should not be Cached, separated by commas  */
);