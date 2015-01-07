<?php

class GRAVITATE_CACHE {

	private static $mcache;
	private static $no_cache_reason = '';
	private static $static_cache = array();
	public static $debug = false;
	public static $config;

	static function init()
	{
		if(defined('WP_CONTENT_DIR') && file_exists(WP_CONTENT_DIR.'/gravitate-cache-config.php'))
		{
			include(WP_CONTENT_DIR.'/gravitate-cache-config.php');

			if(!empty($gravitate_cache_config))
			{
				foreach ($gravitate_cache_config as $key => $value)
				{
					$def = str_replace('-', '_', strtoupper('GRAVITATE_CACHE_CONFIG_'.$key));
					if(defined($def))
					{
						$gravitate_cache_config[$key] = constant($def);
					}
				}

				self::$config = $gravitate_cache_config;
				self::set_cache_type();
			}
		}

		if(defined('GRAVITATE_CACHE_DEBUG') && GRAVITATE_CACHE_DEBUG)
		{
			self::$debug = true;
		}
	}

	public static function init_page_cache()
	{
		if(!empty(self::$config['page_enabled']) && self::can_page_cache())
		{
			if($cache = self::get($_SERVER['REQUEST_URI']))
			{
				echo $cache."\n<!-- Gravitate Cache - Served from Page Cache -->";
				if(!empty(self::$config['database_enabled']))
				{
					echo "\n<!-- Gravitate Cache - Database Cache not needed when using Page Cache -->";
				}
				self::details();
				exit;
			}
		}
	}

	private static function set_cache_type()
	{
		if(!empty(self::$config['database_enabled']) || !empty(self::$config['page_enabled']) || !empty(self::$config['object_enabled']))
		{
			if(self::$config['type'] == 'auto' || self::$config['type'] == 'memcached')
			{
				if(class_exists('Memcached'))
				{
					$server = explode(':', self::$config['server']);
					if(!empty($server[0]) && !empty($server[0]))
					{
						if(self::$mcache = new Memcached())
						{
							if(self::$mcache->addServer($server[0], $server[1]))
							{
								self::$config['type'] = 'memcached';
							}
						}
					}
				}
			}

			if(self::$config['type'] == 'auto' || self::$config['type'] == 'memcache')
			{
				if(class_exists('Memcache'))
				{
					if(!empty($server[0]) && !empty($server[0]))
					{
						if(self::$mcache = new Memcache)
						{
							if(self::$mcache->addServer($server[0], $server[1]))
							{
								self::$config['type'] = 'memcache';
							}
						}
					}
				}
			}
		}

		// Default to Disk if Memcache is not available
		if(self::$config['type'] == 'auto')
		{
			self::$config['type'] = 'disk';
		}
	}

	public static function details()
	{
		global $wpdb;

		$output = '';

		if(!self::can_page_cache())
		{
			if(!empty(self::$no_cache_reason))
			{
				$output.= "\n<!-- Gravitate Cache - Page Not Cached - ".self::$no_cache_reason." -->";
			}
		}

		if(!empty(self::$config['database_enabled']) && method_exists($wpdb,'get_gravitate_cached_items'))
		{
			$output.= "\n<!-- Gravitate Cache - Database Cache Enabled - ".count($wpdb->get_gravitate_cached_items())." Querie(s) pulled from cache. ".count($wpdb->get_gravitate_raw_items())." Raw Querie(s).  -->";
		}

		if(empty(self::$config['database_enabled']))
		{
			$output.= "\n<!-- Gravitate Cache - Database Cache Disabled -->";
		}

		if(self::$debug)
		{
			if(!empty(self::$config['type']))
			{
				$output.= "\n<!-- Gravitate Cache - DEBUG: Caching Type is (".self::$config['type'].") -->";
			}

			if(defined('GRAVITATE_CACHE_TIMESTART') && GRAVITATE_CACHE_TIMESTART)
			{
				$output.= "\n<!-- Gravitate Cache - DEBUG: Server Execution Time was ".sprintf("%01.6f", (microtime(true)-GRAVITATE_CACHE_TIMESTART))." Seconds -->";
			}

			if(!empty(self::$config['database_enabled']) && method_exists($wpdb,'get_gravitate_cached_items'))
			{

				$output.= "\n<!-- Gravitate Cache - DEBUG: Database \n#########################\nQUERIES FROM CACHE\n#########################\n";
				foreach($wpdb->get_gravitate_cached_items() as $key => $item)
				{
					$output.= $key.') '.$item."\n";
				}

				$output.= "\n#########################\nQUERIES FROM DATABASE\n#########################\n";
				foreach($wpdb->get_gravitate_raw_items() as $key => $item)
				{
					$output.= $key.') '.$item."\n";
				}

				$output.= "\n -->";
			}
		}

		echo $output;

		return $output;
	}

	public static function can_page_cache()
	{
		if(empty(self::$config['page_enabled']))
		{
			self::$no_cache_reason = 'Page Cache Disabled';
			return false;
		}

		if(defined('WP_ADMIN'))
		{
			self::$no_cache_reason = 'In Admin Panel';
			return false;
		}

		if(!empty($_POST))
		{
			self::$no_cache_reason = 'Page has Submited POST Data';
			return false;
		}

		if(function_exists('is_user_logged_in'))
		{
			if(is_user_logged_in())
			{
				self::$no_cache_reason = 'User Logged In';
				return false;
			}
		}
		else if(!empty($_COOKIE))
		{
		    foreach($_COOKIE as $key => $val)
		    {
		        if(substr($key, 0, 19) === "wordpress_logged_in")
		        {
		            self::$no_cache_reason = 'User Logged In';
					return false;
		        }
		    }
		}

		if(!empty(self::$config['excluded_urls']))
		{
			foreach (array_map('trim', explode(',', self::$config['excluded_urls'])) as $url)
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
						self::$no_cache_reason = 'Excluded URL '.$original_url;
						return false;
					}
				}
			}
		}

		if(!empty(self::$config['page_enabled']) && !defined('WP_ADMIN'))
		{
			self::$no_cache_reason = '';
			return true;
		}

		self::$no_cache_reason = 'Unknown';
		return false;
	}

	public function clear($group='')
	{
		if(self::$config['type'] == 'disk')
		{
			if(defined('WP_CONTENT_DIR') && is_dir(WP_CONTENT_DIR.'/cache/gravitate_cache'))
			{
				$num = 0;
				foreach (glob(WP_CONTENT_DIR.'/cache/gravitate_cache/*'.$group.'*') as $file)
				{
					unlink($file);
					$num++;
				}
			}
		}
		else if((self::$config['type'] == 'memcached' || self::$config['type'] == 'memcache') && !empty(self::$mcache))
		{
			if(self::$mcache->flush())
			{
				//echo 'Removed Memcache';
			}
		}
	}

	public function get($key='', $group='', $passphrase='')
	{
		$value = '';

		if(!empty($key))
		{
			$key = md5($key.AUTH_KEY);

			if(isset(self::$static_cache[$key]))
			{
				return self::$static_cache[$key];
			}

			if(self::$config['type'] == 'disk')
			{
				if(file_exists(WP_CONTENT_DIR.'/cache/gravitate_cache/'.($group ? $group.'-' : '').$key.'.cache'))
				{
					$value = file_get_contents(WP_CONTENT_DIR.'/cache/gravitate_cache/'.($group ? $group.'-' : '').$key.'.cache');
				}
			}
			else if((self::$config['type'] == 'memcached' || self::$config['type'] == 'memcache') && !empty(self::$mcache))
			{
				$value = self::$mcache->get($key);
			}
		}

		if($value !== '' && function_exists("base64_decode"))
		{
			$value = base64_decode($value);
		}

		if($passphrase)
		{
			$new_value = $value;
			$path = array_merge(range('a', 'z'), range('A', 'Z'), range(0, 9));
			$passphrase = str_replace(':', '', str_pad($passphrase, 62 , AUTH_KEY).implode('', $path));
			$passphrase = array_values(array_unique(str_split($passphrase)));

			$range = range(61, 0);

			foreach ($range as $range_key => $range_val)
			{
				$new_value = str_replace($passphrase[$range_val].':', $path[$range_val], $new_value);
			}

			if(function_exists("base64_decode"))	// Compress Data if available to save disk space
			{
				$value = base64_decode($new_value);
			}
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

	public function set($key='', $value='', $expires=false, $group='', $passphrase='')
	{
		$key = md5($key.AUTH_KEY);

		self::$static_cache[$key] = $value;

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

		if($passphrase)
		{
			$new_value = $value;
			$path = array_merge(range('a', 'z'), range('A', 'Z'), range(0, 9));
			$passphrase = str_replace(':', '', str_pad($passphrase, 62 , AUTH_KEY).implode('', $path));
			$passphrase = array_values(array_unique(str_split($passphrase)));

			foreach ($path as $str_key => $str_val)
			{
				$new_value = str_replace($str_val, $passphrase[$str_key].':', $new_value);
			}

			if(function_exists("base64_encode"))	// Compress Data if available to save disk space
			{
				$value = base64_encode($new_value);
			}
		}

		if(self::$config['type'] == 'disk')
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
		else if(!empty(self::$mcache))
		{
			if(self::$config['type'] == 'memcached')
			{
				return self::$mcache->set($key, $value, $expires);
			}
			else if(self::$config['type'] == 'memcache')
			{
				return self::$mcache->set($key, $value, 0, $expires);
			}
		}

		return false;
	}
}

GRAVITATE_CACHE::init();

