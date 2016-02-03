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
		if(defined('WP_CONTENT_DIR'))
		{
			if($this->is_enabled('database') || $this->is_enabled('page') || $this->is_enabled('object'))
			{
				if($this->config['type'] == 'auto' || $this->config['type'] == 'disk')
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
						return true;
					}
				}
			}
		}

		return false;

	}

	public function filter_key($key='')
	{
		return preg_replace('/[^a-zA-Z0-9\-\_\.]/', '-', $key);
	}

	public function flush()
	{
		if(defined('WP_CONTENT_DIR') && is_dir(WP_CONTENT_DIR.'/cache/gravitate_cache'))
		{
			foreach (glob(WP_CONTENT_DIR.'/cache/gravitate_cache/*') as $file)
			{
				unlink($file);
			}

			return true;
		}
	}

	public function delete($key='')
	{
		if(defined('WP_CONTENT_DIR') && is_dir(WP_CONTENT_DIR.'/cache/gravitate_cache'))
		{
			foreach (glob(WP_CONTENT_DIR.'/cache/gravitate_cache/'.$this->filter_key($key).'.cache') as $file)
			{
				unlink($file);
			}

			return true;
		}
	}

	public function get($key='')
	{
		if(file_exists(WP_CONTENT_DIR.'/cache/gravitate_cache/'.$key.'.cache'))
		{
			return unserialize(file_get_contents(WP_CONTENT_DIR.'/cache/gravitate_cache/'.$this->filter_key($key).'.cache'));
		}

		return null;
	}

	public function set($key='', $value='')
	{
		if(is_dir(WP_CONTENT_DIR.'/cache/gravitate_cache'))
		{
			return file_put_contents(WP_CONTENT_DIR.'/cache/gravitate_cache/'.$this->filter_key($key).'.cache', serialize($value));
		}
	}

	public function increment($key='', $value=1)
	{
		$contents = $this->get($key);
		if($contents !== null)
		{
			$contents = $contents + $value;
			$this->set($key, $contents);
		}
	}

	public function decrement($key='', $value=1)
	{
		$contents = $this->get($key);
		if($contents !== null)
		{
			$contents = $contents - $value;
			$this->set($key, $contents);
		}
	}

	public function clear($regex='/(.*)/')
	{
		if($regex)
		{
			if(defined('WP_CONTENT_DIR') && is_dir(WP_CONTENT_DIR.'/cache/gravitate_cache'))
			{
				$site_key = $this->site_key();

				foreach (glob(WP_CONTENT_DIR.'/cache/gravitate_cache/*') as $file)
				{
					$key = str_replace('.cache', '', basename($file));

					if(preg_match($regex, $key) && strpos($key, $site_key) !== false)
					{
						unlink($file);
					}
				}

				return true;
			}
		}

		return false;
	}

	public function get_all_keys($regex='/(.*)/')
	{
		$all_keys = array();

		if(defined('WP_CONTENT_DIR') && is_dir(WP_CONTENT_DIR.'/cache/gravitate_cache'))
		{
			$site_key = $this->site_key();

			foreach (glob(WP_CONTENT_DIR.'/cache/gravitate_cache/*') as $file)
			{
				$key = str_replace('.cache', '', basename($file));

				if(preg_match($regex, $key) && strpos($key, $site_key) !== false)
				{
					$all_keys[] = $key;
				}
			}
		}

		return $all_keys;
	}
}