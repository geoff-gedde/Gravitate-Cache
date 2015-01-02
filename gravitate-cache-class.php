<?php

class GRAVITATE_CACHE {

	private $config;
	private $mcache;
	private $no_cache_reason = '';
	private $static_cache = array();
	private $debug = false;

	public function __construct()
	{
		if(defined('WP_CONTENT_DIR') && file_exists(WP_CONTENT_DIR.'/gravitate-cache-config.php'))
		{
			include(WP_CONTENT_DIR.'/gravitate-cache-config.php');

			if(!empty($gravitate_cache_config))
			{
				$this->config = $gravitate_cache_config;
			}
		}

		if(!empty($this->config['page_enabled']) && $this->can_cache())
		{
			if($cache = $this->get_cache($_SERVER['REQUEST_URI']))
			{
				echo $cache."\n<!-- Gravitate Cache - Served from Page Cache -->";
				$this->shutdown();
				exit;
			}
		}
	}

	private function set_cache_type()
	{
		if($this->config['type'] == 'auto')
		{
			if(class_exists('Memcached'))
			{
				$this->config['type'] = 'memcached';
			}
			else if(class_exists('Memcache'))
			{
				$this->config['type'] = 'memcache';
			}
			else
			{
				$this->config['type'] = 'disk';
			}
		}
	}

	public function ob_end_flush()
	{
		ob_end_flush();
	}

	public function start_cache()
	{
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

		if(is_plugin_active('gravitate-cache/gravitate-cache.php'))
		{
			if($this->can_cache())
			{
				ob_start(array($this, 'page_buffer_cache'));
			}
			else
			{
				if(!defined('DOING_AJAX') || (defined('DOING_AJAX') && !DOING_AJAX))
				{
					add_action('shutdown', array($this, 'shutdown'));
				}
			}
		}
	}

	public function page_buffer_cache($buffer)
	{
		if($this->can_cache())
		{
			// $buffer
			$this->set_cache($_SERVER['REQUEST_URI'], $buffer, 300);
		}

		return $buffer.$this->shutdown();
	}

	public function shutdown()
	{
		global $wpdb;

		$output = '';

		if(!$this->can_cache())
		{
			if(!empty($this->no_cache_reason))
			{
				$output.= "\n<!-- Gravitate Cache - Page Not Cached - ".$this->no_cache_reason." -->";
			}
		}

		if(!empty($this->config['database_enabled']) && method_exists($wpdb,'get_gravitate_cached_items'))
		{
			$output.= "\n<!-- Gravitate Cache - Database Cache Enabled - ".count($wpdb->get_gravitate_cached_items())." Querie(s) pulled from cache. ".count($wpdb->get_gravitate_raw_items())." Raw Querie(s).  -->";
		}

		if(empty($this->config['database_enabled']))
		{
			$output.= "\n<!-- Gravitate Cache - Database Cache Disabled";
		}

		if($this->debug)
		{
			if(defined('GRAVITATE_CACHE_TIMESTART') && GRAVITATE_CACHE_TIMESTART)
			{
				$output.= "\n<!-- Gravitate Cache - Execution Time - ".sprintf("%01.6f", (microtime(true)-GRAVITATE_CACHE_TIMESTART))." -->";
			}

			if(!empty($this->config['database_enabled']) && method_exists($wpdb,'get_gravitate_cached_items'))
			{

				$output.= "\n<!-- Gravitate Cache - Database Debug \n#########################\nQUERIES FROM CACHE\n#########################\n";
				foreach($wpdb->get_gravitate_cached_items() as $key => $item)
				{
					$output.= $key.') '.$item."\n";
				}

				$output.= "\n#########################\nQUERIES FROM DATABASE\n#########################\n";
				foreach($wpdb->get_gravitate_raw_items() as $key => $item)
				{
					$output.= $key.') '.$item."\n";
				}

				$output.= "\n#########################\nFIRED QUERIES\n#########################\n";
				foreach($wpdb->get_gravitate_fired_items() as $key => $item)
				{
					$output.= $key.') '.$item."\n";
				}

				$output.= "\n -->";
			}
		}

		echo $output;

		return $output;
	}

	private function has_cache()
	{
		// Do Something
		return false;
	}

	private function can_cache()
	{
		if(empty($this->config['page_enabled']))
		{
			$this->no_cache_reason = 'Page Cache Disabled';
			return false;
		}

		if(defined('WP_ADMIN'))
		{
			$this->no_cache_reason = 'In Admin Panel';
			return false;
		}

		if(!empty($_POST))
		{
			$this->no_cache_reason = 'Page has Submited Data';
			return false;
		}

		if(function_exists('is_user_logged_in'))
		{
			if(is_user_logged_in())
			{
				$this->no_cache_reason = 'User Logged In';
				return false;
			}
		}
		else if(!empty($_COOKIE))
		{
		    foreach($_COOKIE as $key => $val)
		    {
		        if(substr($key, 0, 19) === "wordpress_logged_in")
		        {
		            $this->no_cache_reason = 'User Logged In';
					return false;
		        }
		    }
		}

		if(!empty($this->config['excluded_urls']))
		{
			foreach ($this->config['excluded_urls'] as $url)
			{
				if(!empty($url))
				{
					$original_url = $url;

					if(substr($url, 0, 1) == '/')
					{
						$url = '\\'.$url;
					}

					if(substr($url, -1) == '/')
					{
						$url = substr($url, 0, -1).'\\/';
					}

					if(substr($url, -2) == '/$')
					{
						$url = substr($url, 0, -2).'\\/$';
					}

					if(preg_match('/'.$url.'/', $_SERVER['REQUEST_URI']))
					{
						$this->no_cache_reason = 'Excluded URL '.$original_url;
						return false;
					}
				}
			}
		}

		if(!empty($this->config['page_enabled']) && !defined('WP_ADMIN'))
		{
			$this->no_cache_reason = '';
			return true;
		}

		$this->no_cache_reason = 'Unknown';
		return false;
	}

	public function get_cache($key='', $group='')
	{
		$value = false;

		if(!empty($key))
		{
			$key = md5($key);

			if(isset($this->static_cache[$key]))
			{
				return $this->static_cache[$key];
			}

			$this->set_cache_type();

			if($this->config['type'] == 'disk')
			{
				if(file_exists(WP_CONTENT_DIR.'/cache/gravitate_cache/'.($group ? $group.'-' : '').$key.'.cache'))
				{
					$value = file_get_contents(WP_CONTENT_DIR.'/cache/gravitate_cache/'.($group ? $group.'-' : '').$key.'.cache');
				}
			}
			else if($this->config['type'] == 'memcached')
			{
				$server = explode(':', $this->config['server']);

				if(class_exists('Memcached'))
				{
					$this->mcache = new Memcached();
					$this->mcache->addServer($server[0], $server[1]);
				}
				else if(class_exists('Memcache'))
				{
					$this->mcache = new Memcache;
					$this->mcache->addServer($server[0], $server[1]);
				}
			}
		}

		if($value && function_exists("base64_decode"))
		{
			$value = base64_decode($value);
		}

		if(!is_null($value))
		{
			if(is_string($value))
			{
				if($value === 'N;' || preg_match('/^a:\d+:{.*?/', trim($value)) || preg_match('/^b:\d+;/', trim($value)) || preg_match('/^o:\d+:"[a-z0-9_]+":\d+:{.*?/', $value))  // If array || object
				{
					$new_value = unserialize($value);
					if(is_array($new_value) || is_object($new_value) || is_bool($new_value) || is_null($new_value))
					{
						$value = $new_value;
					}
				}
			}
		}

		return $value;
	}

	public function set_cache($key='', $value='', $expires=false, $group='')
	{
		$key = md5($key);

		$this->static_cache[$key] = $value;

		$this->set_cache_type();

		if($value === false)
		{
			return false;
		}

		if(is_array($value) || is_object($value) || is_bool($value) || is_null($value))  // If Value is Array then Serialize the Data
		{
			$value = serialize($value);
		}

		if(function_exists("base64_encode"))	// Compress Data if available to save disk space
		{
			$value = base64_encode($value);
		}

		if($this->config['type'] == 'disk')
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
				file_put_contents(WP_CONTENT_DIR.'/cache/gravitate_cache/'.($group ? $group.'-' : '').$key.'.cache', $value);
			}
		}
		else if($this->config['type'] == 'memcached')
		{
			if(class_exists('Memcached'))
			{
				$this->mcache->set($key, $value, $expires);
			}
			else if(class_exists('Memcache'))
			{
				$this->mcache->set($key, $value, 0, $expires);
			}
		}
	}
}

