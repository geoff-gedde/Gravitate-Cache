<?php

/**************************************
** Created by Gravitate Cache Plugin **
**************************************/

/**
 * Extend the native wpdb class to add caching functionality
 */
class GRAV_CACHE_WPDB extends wpdb
{
	private $gravitate_cache_settings;
	private $gravitate_cache_ignores;
	private $gravitate_cached_items = array();
	private $gravitate_cache_raw_items = array();
	private $gravitate_cache_fired_items = array();
	private $passphrase = '';

	function __construct($dbuser, $dbpassword, $dbname, $dbhost)
	{
		parent::__construct($dbuser, $dbpassword, $dbname, $dbhost);

		$this->gravitate_cache_settings = GRAV_CACHE::$settings;
		$this->gravitate_cache_ignores = array(
			'_comment',
			'_cron',
			'_cache',
			'_count',
			"'cron'",
			'_edit_lock',
			'_nonce',
			'_logins',
			'_random_seed',
			'_stats',
			'_transient_timeout_plugin_slugs',
			'_site_transient_poptags',
			'_site_transient_timeout_poptags',
			"'view'",
			"'views'",
			'autoload',
			'sql_calc_found_rows',
			'found_rows',
			'w3tc_request_data',
			'FOUND_ROWS',
			'RAND()',
			'gdsr_',
			'wp_rg_',
			'_wp_session_',
		);

		//$this->gravitate_cache_ignores[] = 'ORDER BY umeta_id ASC';

		if(!empty($this->gravitate_cache_settings['type']) && $this->gravitate_cache_settings['type'] === 'disk')
		{
			$this->passphrase = (defined('AUTH_SALT') && !empty(AUTH_SALT) ? AUTH_SALT.'salted' : 'P6jRncM6dqbDXpEA4LwCfnCc3PvNbLF2D6');
		}

	}

	function get_gravitate_cached_items()
	{
		return $this->gravitate_cached_items;
	}

	function get_gravitate_cache_raw_items()
	{
		return $this->gravitate_cache_raw_items;
	}

	function get_gravitate_cache_fired_items()
	{
		return $this->gravitate_cache_fired_items;
	}

	private function gravitate_cache_is_query_ignored($query)
	{
		foreach ($this->gravitate_cache_ignores as $ignore)
		{
			if (strpos($query, $ignore) !== false)
			{
				return true;
			}
		}

		return false;
	}

	final private function key($query='')
	{
		$domain = (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '');
		preg_match_all('/('.$this->prefix.'[^(\s|\.]+)/', $query, $tables);
		return md5($domain.$query.AUTH_KEY).(!empty($tables[1]) ? '-'.implode('-',array_unique($tables[1])) : '');
	}

	final private function group($group='')
	{
		return 'db';
	}

	final private function get_gravitate_cache($query=false)
	{
		if($this->gravitate_cache_settings['debug'])
		{
			$fired_query = $query;

			if($this->gravitate_cache_is_query_ignored($query))
			{
				$fired_query.=' - REASON: Ignored List';
			}

			if(!class_exists('GRAV_CACHE'))
			{
				$fired_query.=' - REASON: No Cache Object';
			}

			if(!$query)
			{
				$fired_query.=' - REASON: Query Empty';
			}

			if(class_exists('GRAV_CACHE') && !GRAV_CACHE::is_enabled('database'))
			{
				$fired_query.=' - REASON: DB Caching Disabled';
			}

			$this->gravitate_cache_fired_items[] = $fired_query.' - '.md5($query);
		}

		if(defined('WP_ADMIN') && GRAV_CACHE::is_enabled('exclude_wp_admin', 'excludes'))
		{
			return false;
		}

		if(GRAV_CACHE::get_user_logged_in_cookie() && GRAV_CACHE::is_enabled('exclude_wp_logged_in', 'excludes'))
		{
			return false;
		}

		if(!GRAV_CACHE::can_cache())
		{
			return false;
		}

		if(empty($_POST) && !$this->gravitate_cache_is_query_ignored($query) && class_exists('GRAV_CACHE') && $query && GRAV_CACHE::is_enabled('database'))
		{
			$cache = GRAV_CACHE::get($this->key($query), $this->group(), $this->passphrase);

			if(!empty($cache) && is_array($cache))
			{
				$results = $cache['results'];
				$this->last_result = $cache['last_result'];
				$this->num_rows = $cache['num_rows'];

				$this->gravitate_cached_items[] = $this->clean_query_log($query);

				return $results;
			}
		}

		return false;
	}

	final private function set_gravitate_cache($query=false, $results=false)
	{
		if(!GRAV_CACHE::can_cache())
		{
			return false;
		}

		if(defined('WP_ADMIN') && GRAV_CACHE::is_enabled('exclude_wp_admin', 'excludes'))
		{
			return false;
		}

		if(GRAV_CACHE::get_user_logged_in_cookie() && GRAV_CACHE::is_enabled('exclude_wp_logged_in', 'excludes'))
		{
			return false;
		}

		if(class_exists('GRAV_CACHE') && $query && $results !== false && GRAV_CACHE::is_enabled('database'))
		{
			$cache_results = array('results'=>$results,'last_result'=>$this->last_result,'num_rows'=>$this->num_rows);
			return GRAV_CACHE::set($this->key($query), $cache_results, false, $this->group(), $this->passphrase);
		}

		return false;
	}

	final private function clean_query_log($query)
	{
		if(!$this->gravitate_cache_settings['debug'])
		{
			return 0;
		}
		return str_replace('  ', ' ', str_replace(array("\n","\r","\t",'  '), ' ', str_replace($this->prefix, '*_', $query)));
	}

	final function get_results( $query = null, $output = OBJECT )
	{
		$key = 'get_results__'.$query.'_'.$output;
		$results = $this->get_gravitate_cache($key);
		if($results === false)
		{
			$this->gravitate_cache_raw_items[] = 'get_results__'.$this->clean_query_log($query);
			$results = parent::get_results( $query, $output);
			$this->set_gravitate_cache($key, $results);
		}
		return $results;
	}

	final function get_row( $query = null, $output = OBJECT, $y = 0 )
	{
		$key = 'get_row__'.$query.'_'.$output.'_'.$y;
		$results = $this->get_gravitate_cache($key);
		if($results === false)
		{
			$this->gravitate_cache_raw_items[] = 'get_row__'.$this->clean_query_log($query);
			$results = parent::get_row( $query, $output, $y);
			$this->set_gravitate_cache($key, $results);
		}
		return $results;
	}

	final function get_var( $query = null, $x = 0, $y = 0 )
	{
		$key = 'get_var__'.$query.'_'.$x.'_'.$y;
		$results = false;
		if($query)
		{
			$results = $this->get_gravitate_cache($key);
		}
		if($results === false)
		{
			$this->gravitate_cache_raw_items[] = 'get_var__'.$this->clean_query_log($query);
			$results = parent::get_var( $query, $x, $y);
			$this->set_gravitate_cache($key, $results);
		}
		return $results;
	}

	final function get_col( $query = null , $x = 0 )
	{
		$key = 'get_col__'.$query.'_'.$x;
		$results = $this->get_gravitate_cache($key);
		if($results === false)
		{
			$this->gravitate_cache_raw_items[] = 'get_col__'.$this->clean_query_log($query);
			$results = parent::get_col( $query, $x);
			$this->set_gravitate_cache($key, $results);
		}
		return $results;
	}

	function check_select_query($query)
	{
		// Check Select
		if(preg_match( '/^\s*(select)\s/i', $query))
		{
			// Not sure if we can do anything here.
			if(preg_match( '/^\s*(select)\s/i', $query) && !in_array('get_var__'.str_replace($this->prefix, '*_', $query), $this->gravitate_cache_raw_items) && !in_array('get_row__'.str_replace($this->prefix, '*_', $query), $this->gravitate_cache_raw_items) && !in_array('get_results__'.str_replace($this->prefix, '*_', $query), $this->gravitate_cache_raw_items) && !in_array('get_col__'.str_replace($this->prefix, '*_', $query), $this->gravitate_cache_raw_items))
			{
				$this->gravitate_cache_raw_items[] = 'query__'.$this->clean_query_log($query);
			}
		}
	}

	function check_and_clear_write_query($query, $results)
	{
		// write operations may need to invalidate the cache
		if(class_exists('GRAV_CACHE_INIT') && !$this->gravitate_cache_is_query_ignored($query) && $results && preg_match( '/^\s*(create|alter|truncate|drop|insert|delete|update|replace)\s/i', $query))
		{
			preg_match_all('/('.$this->prefix.'[^(\s|\.]+)/', $query, $tables);
			if(!empty($tables[1]))
			{
				GRAV_CACHE::clear('/__db__.*('.str_replace("`", "", implode('|',$tables[1])).')/');
			}
		}
	}

	function query( $query )
	{
		$this->check_select_query($query);

		$results = parent::query($query);

		$this->check_and_clear_write_query($query, $results);

		return $results;
	}
}

if(defined('WP_CONTENT_DIR') && file_exists(WP_CONTENT_DIR.'/plugins/gravitate-cache/controllers/gravitate-cache-class.php'))
{
	include_once(WP_CONTENT_DIR.'/plugins/gravitate-cache/controllers/gravitate-cache-class.php');
}
