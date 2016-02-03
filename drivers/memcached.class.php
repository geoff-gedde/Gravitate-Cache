<?php

class GRAV_CACHE_DRIVER_MEMCACHED extends GRAV_CACHE_DRIVER {

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
			if($this->config['type'] == 'auto' || $this->config['type'] == 'automemory' || $this->config['type'] == 'memcached')
			{
				if(class_exists('Memcached'))
				{
					if($this->connection = new Memcached())
					{
						if(empty($this->config['servers']))
						{
							$this->config['servers'] = '127.0.0.1';
						}

						$added_server = false;

						foreach(explode(',', $this->config['servers']) as $serverport)
						{
							$split = explode(':', $serverport);

							$server = ($split[0] ? $split[0] : '127.0.0.1');
							$port = ($split[1] ? $split[1] : '11211');

							if($this->connection->addServer($server, $port))
							{
								$added_server = true;
							}
						}

						return $added_server;
					}
				}
			}
		}

		return false;
	}

	public function flush()
	{
		return $this->connection->flush();
	}

	public function delete($key='')
	{
		if($all_keys = $this->get($this->site_key('__getAllKeys')))
		{
			if(is_array($all_keys))
			{
				if($index = array_search($key, $all_keys))
				{
					unset($all_keys[$index]);
				}
				$this->connection->set($this->site_key('__getAllKeys'), array_unique($all_keys), 0);
			}
		}

		return $this->connection->delete($key);
	}

	public function get($key='')
	{
		return $this->connection->get($key);
	}

	public function set($key='', $value='', $expires=0)
	{
		$result = $this->connection->set($key, $value, $expires);

		if($result)
		{
			$all_keys = $this->get($this->site_key('__getAllKeys'));

			if(empty($all_keys))
			{
				$all_keys = array();
			}

			$all_keys[] = $key;
			$this->connection->set($this->site_key('__getAllKeys'), array_unique($all_keys), 0);
		}

		return $result;
	}

	public function increment($key='', $value=1)
	{
		return $this->connection->increment($key, 1, $value);
	}

	public function decrement($key='', $value=1)
	{
		return $this->connection->decrement($key, 1, $value);
	}

	public function clear($regex='/(.*)/')
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
		$keys = $this->get($this->site_key('__getAllKeys'));
		$site_key = $this->site_key();

		foreach ((array) $keys as $key)
		{
			if(preg_match($regex, $key) && strpos($key, $site_key) !== false)
			{
				$all_keys[] = $key;
			}
		}

		return $all_keys;
	}
}