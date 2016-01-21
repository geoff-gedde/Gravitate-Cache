<?php

class GRAV_CACHE_DRIVER {

	private $connection;
	private $config;

	public function __construct()
	{

	}

	public function key($key='')
	{
		return GRAV_CACHE::site_key($key);
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