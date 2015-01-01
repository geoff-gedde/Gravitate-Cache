<?php

class GRAVITATE_CACHE {

	private $config;
	private $m;
	private $no_cache_reason = '';

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

		if(!empty($this->config['page_enabled']) && $this->can_cache())
		{
			if($this->config['type'] == 'disk')
			{
				if(file_exists(WP_CONTENT_DIR.'/cache/gravitate_cache/'.md5($_SERVER['REQUEST_URI']).'.cache'))
				{
					$contents = base64_decode(file_get_contents(WP_CONTENT_DIR.'/cache/gravitate_cache/'.md5($_SERVER['REQUEST_URI']).'.cache'));
					if(!empty($contents))
					{
						echo $contents."\n<!-- Gravitate Cache - Served from Cache -->";
						exit;
					}
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
			$this->save_cache($_SERVER['REQUEST_URI'], $buffer, 300);
		}

		return $buffer;
	}

	public function shutdown()
	{
		if(!$this->can_cache())
		{
			if(!empty($this->no_cache_reason))
			{
				echo "\n<!-- Gravitate Cache - NOT CACHED - ".$this->no_cache_reason." -->";
			}
		}
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

	private function save_cache($key='', $value='', $expires=false, $group='')
	{
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
				if($fp = fopen(WP_CONTENT_DIR.'/cache/gravitate_cache/'.($group ? $group.'-' : '').md5($key).'.cache', 'w'))
				{
					fwrite($fp, base64_encode($value));
					fclose($fp);
				}
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

