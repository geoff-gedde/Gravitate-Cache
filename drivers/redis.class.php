<?php

class GRAV_CACHE_DRIVER_REDIS extends GRAV_CACHE_DRIVER {

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
			if($this->config['type'] == 'auto' || $this->config['type'] == 'automemory' || $this->config['type'] == 'redis')
			{
				if(class_exists('Redis'))
				{
					if($this->connection = new Redis())
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
							$port = ($split[1] ? $split[1] : '6379');

							if($this->connection->pconnect($server, $port))
							{
								$this->connection->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
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
		return $this->connection->flushAll();
	}

	public function delete($key='')
	{
		return $this->connection->delete($key);
	}

	public function get($key='')
	{
		return $this->connection->get($key);
	}

	public function set($key='', $value='', $expires=0)
	{

		return $this->connection->set($key, $value);
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

		if($keys = $this->connection->keys('*'))
		{
			$site_key = $this->site_key();

			foreach ($keys as $key)
			{
				if(preg_match($regex, $key) && strpos($key, $site_key) !== false)
				{
					$all_keys[] = $key;
				}
			}
		}

		return $all_keys;
	}
}