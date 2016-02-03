<?php

class GRAV_CACHE_DRIVER {

	private $connection;
	private $config;

	public function __construct()
	{

	}

	public function site_key($key='', $group='')
	{
		return GRAV_CACHE::site_key($key, $group);
	}

	/**
	 * Grabs the settings from the Settings class
	 *
	 * @param boolean $force
	 *
	 * @return void
	 */
	public static function is_enabled($cache_type='')
	{
		return GRAV_CACHE::is_enabled($cache_type);
	}
}