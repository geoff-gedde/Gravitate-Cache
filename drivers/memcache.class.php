<?php

class GRAVITATE_CACHE_DRIVER_MEMCACHE extends GRAVITATE_CACHE_DRIVER {

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
			if(!empty($this->config['server']) && ($this->config['type'] == 'auto' || $this->config['type'] == 'automemory' || $this->config['type'] == 'memcache'))
			{
				$server = explode(':', $this->config['server']);

				if(class_exists('Memcache') && !empty($server[0]) && !empty($server[1]))
				{
					if($this->connection = new Memcache)
					{
						if($this->connection->addServer($server[0], $server[1]))
						{
							return true;
						}
					}
				}
			}
		}

		return false;

	}

	// public function key($key='')
	// {
	// 	$domain = (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '');
	// 	return $domain.'::'.trim($key);
	// }

	public function flush()
	{
		return $this->connection->flush();
	}

	public function delete($key='')
	{
		$key = $this->key($key);
		return $this->connection->delete($key);
	}

	public function get($key='', $group='')
	{
		$key = $this->key($key);
		return $this->connection->get($key);
	}

	public function set($key='', $value='', $expires=0, $group='')
	{
		$key = $this->key($key);
		return $this->connection->set($key, $value, 0, $expires);
	}

	public function increment($key='', $value=1, $group='')
	{
		$key = $this->key($key);
		return $this->connection->increment($key, $value);
	}

	public function decrement($key='', $value=1, $group='')
	{
		$key = $this->key($key);
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
		                foreach($arrVal AS $k => $v)
		                {
		                    $keys[] = $k;
		                }
		            }
		        }
		    }
		}

		if(!empty($keys))
		{
			$site_key = $this->key();

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