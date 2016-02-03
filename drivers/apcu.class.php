<?php

class GRAV_CACHE_DRIVER_APCU extends GRAV_CACHE_DRIVER {

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
			if($this->config['type'] == 'auto' || $this->config['type'] == 'automemory' || $this->config['type'] == 'apcu')
			{
				if(function_exists('apcu_add') && class_exists('APCUIterator'))
				{
					if($this->connection = true)
					{
						return true;
					}
				}
			}
		}

		return false;
	}

	public function flush()
	{
		return apcu_clear_cache();
	}

	public function delete($key='')
	{
		return apcu_delete($key);
	}

	public function get($key='')
	{
		return apcu_fetch($key);
	}

	public function set($key='', $value='', $expires=0)
	{
		return apcu_store($key, $value, $expires);
	}

	public function increment($key='', $value=1)
	{
		return apcu_inc($key, $value);
	}

	public function decrement($key='', $value=1)
	{
		return apcu_dec($key, $value);
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

	public function get_all_keys($regex='/(.*)/')
	{
		$all_keys = array();
		$site_key = $this->site_key();

		foreach (new APCUIterator($regex) as $item)
		{
		    if($key = $item['key'])
		    {
			    if(strpos($key, $site_key) !== false)
				{
					$all_keys[] = $key;
				}
			}
		}

		return $all_keys;
	}
}