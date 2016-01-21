<?php

class GRAV_CACHE_DRIVER_DISK extends GRAV_CACHE_DRIVER {

	private $connection;
	private $config;

	public function __construct($config=array())
	{
		$this->config = $config;
	}

	public function init()
	{
		if($this->is_enabled('database') || $this->is_enabled('page') || $this->is_enabled('object'))
		{
			if($this->config['type'] == 'auto' || $this->config['type'] == 'disk')
			{
				return true;
			}
		}

		return false;

	}

	public function flush()
	{
		//return $this->connection->flush();
	}

	public function delete($key='', $group='')
	{
		if(defined('WP_CONTENT_DIR') && is_dir(WP_CONTENT_DIR.'/cache/gravitate_cache'))
		{
			foreach (glob(WP_CONTENT_DIR.'/cache/gravitate_cache/'.($group ? $group.'-' : '').$key.'.cache') as $file)
			{
				unlink($file);
			}
		}
	}

	public function get($key='', $group='')
	{
		if(file_exists(WP_CONTENT_DIR.'/cache/gravitate_cache/'.($group ? $group.'-' : '').$key.'.cache'))
		{
			return file_get_contents(WP_CONTENT_DIR.'/cache/gravitate_cache/'.($group ? $group.'-' : '').$key.'.cache');
		}

		return null;
	}

	public function set($key='', $value='', $group='')
	{
		if(!is_dir(WP_CONTENT_DIR.'/cache'))
		{
			mkdir(WP_CONTENT_DIR.'/cache');
		}

		if(!is_dir(WP_CONTENT_DIR.'/cache/gravitate_cache'))
		{
			mkdir(WP_CONTENT_DIR.'/cache/gravitate_cache');
		}

		if(is_dir(WP_CONTENT_DIR.'/cache/gravitate_cache'))
		{
			return file_put_contents(WP_CONTENT_DIR.'/cache/gravitate_cache/'.($group ? $group.'-' : '').$key.'.cache', $value);
		}
	}

	public function increment($key='', $value=1, $group='')
	{

	}

	public function decrement($key='', $value=1, $group='')
	{
		return self::$connection->decrement($key, $value);
	}

	public function clear($regex='')
	{
		if($regex)
		{
			if($keys = $this->get_all_keys($regex))
			{
				foreach ($keys as $key)
				{
					$this->delete($key);
				}

				return true;
			}
		}

		return false;
	}

	public function get_all_keys($regex='*')
	{
		$all_keys = array();

		if(defined('WP_CONTENT_DIR') && is_dir(WP_CONTENT_DIR.'/cache/gravitate_cache'))
		{
			foreach (glob(WP_CONTENT_DIR.'/cache/gravitate_cache/*'.$regex.'*') as $file)
			{
				$all_keys[] = basename($file);
			}
		}

		return $all_keys;
	}
}