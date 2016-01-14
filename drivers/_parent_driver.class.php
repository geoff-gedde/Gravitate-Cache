<?php

class GRAVITATE_CACHE_DRIVER {

	private $connection;
	private $config;

	public function __construct()
	{

	}

	public function key($key='')
	{
		$domain = (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '');
		return $domain.'::'.trim($key);
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
		return GRAVITATE_CACHE::is_enabled($cache_type);
	}
}