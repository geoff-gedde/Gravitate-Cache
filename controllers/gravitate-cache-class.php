<?php

// ini_set('error_reporting', E_ALL);
// ini_set('display_errors', 1);

class GRAVITATE_CACHE {

	private static $mcache;
	private static $no_cache_reason = '';
	private static $static_cache = array();
	public static $debug = false;
	public static $settings;
	public static $driver = false;
	public static $drivers = array('redis','memcached','memcache','disk');

	/**
	 *
	 *
	 *
	 */
	public static function init()
	{
		self::get_settings();

		if(!empty(self::$settings))
		{
			include_once(dirname(dirname(__FILE__)).'/drivers/_parent_driver.class.php');

			foreach (self::$drivers as $driver)
			{
				$driver_class = 'GRAVITATE_CACHE_DRIVER_'.strtoupper($driver);
				include_once(dirname(dirname(__FILE__)).'/drivers/'.$driver.'.class.php');
				$driver_obj = new $driver_class(self::$settings);
				if($driver_obj->init())
				{
					self::$settings['type'] = $driver;
					self::$driver = $driver_obj;
					break;
				}
			}
		}
	}

	/**
	 *
	 *
	 *
	 */
	public static function get_settings()
	{
		if(defined('WP_CONTENT_DIR') && file_exists(WP_CONTENT_DIR.'/cache/grav-cache-settings.php'))
		{
			$grav_cache_settings = include(WP_CONTENT_DIR.'/cache/grav-cache-settings.php');

			if(!empty($grav_cache_settings) && is_string($grav_cache_settings))
			{
				$grav_cache_settings = trim($grav_cache_settings);

				if(preg_match('/^\{.*\}?/is', $grav_cache_settings))
				{
					$grav_cache_settings = json_decode(str_replace('\\', '\\\\', $grav_cache_settings), true);
				}

				if(!empty($grav_cache_settings) && is_array($grav_cache_settings))
				{
					foreach ($grav_cache_settings as $key => $value)
					{
						$def = str_replace('-', '_', strtoupper('GRAVITATE_CACHE_CONFIG_'.$key));
						if(defined($def))
						{
							$grav_cache_settings[$key] = constant($def);
						}
					}

					self::$settings = $grav_cache_settings;

					self::$settings['debug'] = false;

					if(defined('GRAVITATE_CACHE_DEBUG') && GRAVITATE_CACHE_DEBUG)
					{
						self::$settings['debug'] = true;
					}
				}
			}
		}
	}

	/**
	 *
	 *
	 *
	 */
	public static function get_userid_hash($with_user_info=true)
	{
		if($with_user_info)
		{
			$cookiehash = self::get_user_logged_in_cookie();
			$hash = (!empty($cookiehash) ? md5($cookiehash) : md5(AUTH_KEY));
		}
		else
		{
			$hash = md5(AUTH_KEY);
		}

		return $hash;
	}

	/**
	 *
	 *
	 *
	 */
	public static function get_user_logged_in_cookie()
	{
		if(defined('COOKIEHASH') && COOKIEHASH)
		{
			$cookiehash = COOKIEHASH;
		}
		else
		{
			$cookiehash = md5('http'.(!empty($_SERVER['HTTPS']) ? 's' : '').'://'.$_SERVER['HTTP_HOST']);
		}

		return (!empty($_COOKIE['wordpress_logged_in_'.$cookiehash]) ? $_COOKIE['wordpress_logged_in_'.$cookiehash] : false);
	}

	/**
	 *
	 *
	 *
	 */
	public static function init_page_cache()
	{
		if(self::is_enabled('page') && self::can_page_cache())
		{
			$cache = self::get(self::get_page_key(), 'page');
			if(!empty($cache['value']))
			{
				echo $cache['value']."\n<!-- Gravitate Cache - Served from Page Cache on (".date('m/d/Y H:i:s', $cache['time']).") -->";
				if(self::is_enabled('database'))
				{
					echo "\n<!-- Gravitate Cache - Database Cache not needed when using Page Cache -->";
				}
				self::details();
				exit;
			}
		}
	}

	public static function site_key($key='')
	{
		$domain = (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '');
		return $domain.'::'.trim($key);
	}

	/**
	 *
	 *
	 *
	 */
	public static function get_page_key($url='', $logged_in=true)
	{
		$url = ($url ? $url : $_SERVER['REQUEST_URI']);
		return 'page::uid'.self::get_userid_hash($logged_in).'::'.$url;
	}

	/**
	 *
	 *
	 *
	 */
	public static function details()
	{
		global $wpdb, $table_prefix;

		$output = '';

		if(defined('WP_CONTENT_DIR') && !file_exists(WP_CONTENT_DIR.'/cache/grav-cache-settings.php'))
		{
			$output.= "\n<!-- Gravitate Cache - MISSING CACHE FILE SETTINGS.  You may need to Re-save your settings -->";
		}

		if(!self::can_page_cache())
		{
			if(!empty(self::$no_cache_reason))
			{
				$output.= "\n<!-- Gravitate Cache - Page Not Cached - ".self::$no_cache_reason." -->";
			}
		}

		if(self::is_enabled('page') && self::can_page_cache())
		{
			$output.= "\n<!-- Gravitate Cache - Page Not Cached - Not Cached Yet. Try Reloading -->";
		}

		if(self::is_enabled('database') && method_exists($wpdb,'get_gravitate_cached_items'))
		{
			$output.= "\n<!-- Gravitate Cache - Database Cache Enabled - ".count($wpdb->get_gravitate_cached_items())." Querie(s) pulled from cache. ".count($wpdb->get_gravitate_cache_raw_items())." Raw Querie(s).  -->";
		}

		if(!self::is_enabled('database'))
		{
			$output.= "\n<!-- Gravitate Cache - Database Cache Disabled -->";
		}

		if(!self::is_enabled('object'))
		{
			$output.= "\n<!-- Gravitate Cache - Object Cache Disabled -->";
		}

		if(self::is_enabled('object'))
		{
			global $wp_object_cache;

			if($wp_object_cache)
			{
				$output.= "\n<!-- Gravitate Cache - Object Cache Stats - ";
				$output.= "Cache Hits: ".$wp_object_cache->grav_cache_hits." / ";
				$output.= "Cache Misses: ".$wp_object_cache->grav_cache_misses." -->";
			}
		}

		if(self::$settings['debug'])
		{
			if(!empty(self::$settings['type']))
			{
				$output.= "\n<!-- Gravitate Cache - DEBUG: Caching Type is (".self::$settings['type'].") -->";
			}

			if(self::is_enabled('database') && method_exists($wpdb,'get_gravitate_cached_items'))
			{

				$output.= "\n<!-- Gravitate Cache - DEBUG: Database \n#########################\nQUERIES FROM CACHE\n#########################\n";
				foreach($wpdb->get_gravitate_cached_items() as $key => $item)
				{
					$output.= $key.') '.$item."\n";
				}

				$output.= "\n#########################\nQUERIES FROM DATABASE\n#########################\n";
				foreach($wpdb->get_gravitate_cache_raw_items() as $key => $item)
				{
					$output.= $key.') '.$item."\n";
				}

				$output.= "\n -->";
			}

			$output.= "\n<!-- ALL KEYS ";
			if(self::$driver)
			{
				if($keys = self::$driver->get_all_keys())
				{
					$output.= " (".count($keys).") \n";
					foreach ($keys as $key => $value)
					{
						$output.= "\t".preg_replace('/[0-9a-z]{32}/', '*', str_replace(array($table_prefix, self::site_key()), '*', $value))."\n";
						//$output.= "\t".$value."\n";
					}
				}
			}
			$output.= " -->";
		}

		if(defined('GRAVITATE_CACHE_TIMESTART') && GRAVITATE_CACHE_TIMESTART)
		{
			$output.= "\n<!-- Gravitate Cache - DEBUG: Server Execution Time was ".sprintf("%01.6f", (microtime(true)-GRAVITATE_CACHE_TIMESTART))." Seconds -->";
		}


		echo $output;

		return $output;
	}


	/**
	 * Checks if Cache Type is Enabled
	 *
	 * @param string $cache_type
	 *
	 * @return bool
	 */
	public static function is_enabled($cache_type='', $group='enabled')
	{
		if(!empty(self::$settings[$group]) && in_array($cache_type, self::$settings[$group]))
		{
			return true;
		}

		return false;
	}


	/**
	 *
	 *
	 *
	 */
	public static function can_cache()
	{

		if(defined('DOING_AJAX') && DOING_AJAX)
		{
			self::$no_cache_reason = 'Ajax was initilized';
			return false;
		}

		/**
         * Skip if doing cron
         */
        if(defined('DOING_CRON'))
        {
        	self::$no_cache_reason = 'Cron was initilized';
            return false;
        }

        /**
         * Skip if APP request
         */
        if(defined('APP_REQUEST'))
        {
        	self::$no_cache_reason = 'App was Requested';
            return false;
        }

        /**
         * Skip if XMLRPC request
         */
        if(defined('XMLRPC_REQUEST'))
        {
        	self::$no_cache_reason = 'Was XMLRPC Request';
            return false;
        }

        /**
         * Check for WPMU's and WP's 3.0 short init
         */
        if(defined('SHORTINIT') && SHORTINIT)
        {
        	self::$no_cache_reason = 'Was XMLRPC Request';
            return false;
        }

		if(!empty($_POST))
		{
			self::$no_cache_reason = 'Page has Submited POST Data';
			return false;
		}

		return true;
	}

	/**
	 *
	 *
	 *
	 */
	public static function can_page_cache()
	{
		if(!self::can_cache())
		{
			return false;
		}

		if(!self::is_enabled('page'))
		{
			self::$no_cache_reason = 'Page Cache Disabled';
			return false;
		}

		if(defined('WP_ADMIN') && self::is_enabled('exclude_wp_admin', 'excludes'))
		{
			self::$no_cache_reason = 'Admin Panel is Excluded';
			return false;
		}

		if(function_exists('is_user_logged_in'))
		{
			if(is_user_logged_in() && GRAVITATE_CACHE::is_enabled('exclude_wp_logged_in', 'excludes'))
			{
				self::$no_cache_reason = 'User Logged In Excluded';
				return false;
			}
		}
		else if(!empty($_COOKIE))
		{
		    foreach($_COOKIE as $key => $val)
		    {
		        if(substr($key, 0, 19) === "wordpress_logged_in" && GRAVITATE_CACHE::is_enabled('exclude_wp_logged_in', 'excludes'))
		        {
		            self::$no_cache_reason = 'User Logged In Excluded';
					return false;
		        }
		    }
		}

		$excluded_urls = array();
		if(!empty(self::$settings['excluded_urls']))
		{
			$excluded_urls = array_map('trim', explode(',', self::$settings['excluded_urls']));
		}

		$excluded_urls[] = 'gravitate_cache_settings';

		foreach ($excluded_urls as $url)
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

		if(self::is_enabled('page'))
		{
			self::$no_cache_reason = '';
			return true;
		}

		self::$no_cache_reason = 'Unknown';
		return false;
	}

	/**
	 *
	 *
	 *
	 */
	public static function flush()
	{
		if(self::$driver)
		{
			return self::$driver->flush();
		}
		return false;
	}

	/**
	 *
	 *
	 *
	 */
	public static function clear($regex='/(.*)/')
	{
		if(self::$driver)
		{
			//self::$driver->flush();
			return self::$driver->clear($regex);
		}
		return false;
	}

	/**
	 *
	 *
	 *
	 */
	public static function delete($key='', $group='') // clear_key
	{
		if(self::$driver)
		{
			return self::$driver->delete($key, $group);
		}
	}

	/**
	 *
	 *
	 *
	 */
	public static function get_all_keys($regex='/(.*)/')
	{
		if(self::$driver)
		{
			return self::$driver->get_all_keys($regex);
		}
	}


	/**
	 *
	 *
	 *
	 */
	public static function has_key($key)
	{
		if(self::$driver)
		{
			if($keys = self::$driver->get_all_keys())
			{
				if(in_array(self::$driver->key($key), $keys))
				{
					return true;
				}
			}
		}

		return false;
	}

	/**
	 *
	 *
	 *
	 */
	public static function get($key='', $group='', $passphrase='')
	{
		$value = '';

		if(!empty($key))
		{
			if(isset(self::$static_cache[$key]))
			{
				return self::$static_cache[$key];
			}

			if(self::has_key($key))
			{
				$value = self::$driver->get($key);
			}
		}

		if(strpos($key, 'object') !== false)
		{
			return $value;
		}

		if(!$passphrase && self::is_enabled('encrypt', 'encryption'))
		{
			$passphrase = (defined('AUTH_SALT') && !empty(AUTH_SALT) ? AUTH_SALT.'salted' : 'P6jRncM6dqbDXpEA4LwCfnCc3PvNbLF2D6');
		}

		if($passphrase && function_exists("base64_decode"))
		{
			if(function_exists('openssl_decrypt'))
			{
				$value = openssl_decrypt($value, 'aes128', $passphrase, 0, '1234567890193756');
			}
			else
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

				$value = $new_value;
			}
		}

		if(function_exists("base64_decode"))	// Compress Data if available to save disk space
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

	/**
	 *
	 *
	 *
	 */
	public static function set($key='', $value='', $expires=0, $group='', $passphrase='')
	{
		if(!empty($group) && is_array($group))
		{
			$group = implode('-', array_unique($group));
		}

		if($value === false)
		{
			self::$static_cache[$key] = false;
			return false;
		}

		self::$static_cache[$key] = $value;

		if(strpos($key, 'object') === false)
		{

			$value = array('time' => time(), 'value' => $value);

			$value = serialize($value);

			if(function_exists("base64_encode"))	// Compress Data if available to save disk space
			{
				$value = base64_encode($value);
			}

			if(!$passphrase && self::is_enabled('encrypt', 'encryption'))
			{
				$passphrase = (defined('AUTH_SALT') && !empty(AUTH_SALT) ? AUTH_SALT.'salted' : 'P6jRncM6dqbDXpEA4LwCfnCc3PvNbLF2D6');
			}

			if($passphrase && function_exists("base64_encode"))
			{
				if(function_exists('openssl_encrypt'))
				{
					$value = openssl_encrypt($value, 'aes128', $passphrase, 0, '1234567890193756');
				}
				else
				{
					$new_value = $value;
					$path = array_merge(range('a', 'z'), range('A', 'Z'), range(0, 9));
					$passphrase = str_replace(':', '', str_pad($passphrase, 62 , AUTH_KEY).implode('', $path));
					$passphrase = array_values(array_unique(str_split($passphrase)));

					foreach ($path as $str_key => $str_val)
					{
						$new_value = str_replace($str_val, $passphrase[$str_key].':', $new_value);
					}

					$value = $new_value;
				}
			}
		}

		return self::$driver->set($key, $value, $expires=0, $group);
	}

	/**
	 *
	 *
	 *
	 */
	public static function get_multi($keys=array(), $group='')
	{
		$values = array();
		foreach ($keys as $key)
		{
			$values[$key] = self::get($key, $group);
		}
		return $values;
	}

	/**
	 *
	 *
	 *
	 */
	public static function set_multi($items=array(), $group='', $expires=0)
	{
		foreach ($items as $key => $value)
		{
			self::set($key, $value, $expires, $group);
		}
		return true;
	}

	/**
	 *
	 *
	 *
	 */
	public static function increment($key='', $value=1, $group='')
	{
		return self::$driver->increment($key, $value, $group);

		return false;
	}

	/**
	 *
	 *
	 *
	 */
	public static function decrement($key='', $value=1, $group='')
	{
		return self::$driver->decrement($key, $value, $group);

		return false;
	}
}

GRAVITATE_CACHE::init();

