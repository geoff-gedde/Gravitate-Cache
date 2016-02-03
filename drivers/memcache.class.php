<?php

class GRAV_CACHE_DRIVER_MEMCACHE extends GRAV_CACHE_DRIVER {

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
			if($this->config['type'] == 'auto' || $this->config['type'] == 'automemory' || $this->config['type'] == 'memcache')
			{
				if(class_exists('Memcache'))
				{
					if($this->connection = new Memcache)
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
		return $this->connection->delete($key);
	}

	public function get($key='')
	{
		return $this->connection->get($key);
	}

	public function set($key='', $value='', $expires=0)
	{
		return $this->connection->set($key, $value, 0, $expires);
	}

	public function increment($key='', $value=1)
	{
		return $this->connection->increment($key, $value);
	}

	public function decrement($key='', $value=1)
	{
		return $this->connection->decrement($key, $value);
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

		if(method_exists($this->connection, 'getExtendedStats'))
		{
			$site_key = $this->site_key();

		    $list = array();
		    $allSlabs = $this->connection->getExtendedStats('slabs');
		    $items = $this->connection->getExtendedStats('items');
		    foreach($allSlabs as $server => $slabs)
		    {
		        foreach($slabs as $slabId => $slabMeta)
		        {
		            $cdump = $this->connection->getExtendedStats('cachedump',(int)$slabId);
		            foreach($cdump as $arrVal)
		            {
		                if (!is_array($arrVal)) continue;
		                foreach($arrVal as $k => $v)
		                {
		                	if(preg_match($regex, $k) && strpos($k, $site_key) !== false)
							{
		                    	$all_keys[] = $k;
		                    }
		                }
		            }
		        }
		    }
		}

		return $all_keys;
	}
}