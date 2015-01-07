<?php

/**************************************
** Created by Gravitate Cache Plugin **
**************************************/

/**
 * Extend the native wpdb class to add caching functionality
 */
class GRAVITATE_CACHE_WPDB extends wpdb
{
	private $gravitate_config;
	private $gravitate_ignores;
	private $gravitate_cached_items = array();
	private $gravitate_raw_items = array();
	private $gravitate_fired_items = array();
	private $gravitate_debug = false;

	function __construct($dbuser, $dbpassword, $dbname, $dbhost)
	{
		parent::__construct($dbuser, $dbpassword, $dbname, $dbhost);

		$this->gravitate_config = GRAVITATE_CACHE::$config;
		$this->gravitate_debug = GRAVITATE_CACHE::$debug;
		//$this->gravitate_config['database_enabled'] = false;

		$this->gravitate_ignores = array(
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
			'view',
			'FOUND_ROWS',
			'RAND()',
			'gdsr_',
			'wp_rg_',
			'_wp_session_',
		);

		//$this->gravitate_ignores[] = 'ORDER BY umeta_id ASC';

	}

	function get_gravitate_cached_items()
	{
		return $this->gravitate_cached_items;
	}

	function get_gravitate_raw_items()
	{
		return $this->gravitate_raw_items;
	}

	function get_gravitate_fired_items()
	{
		return $this->gravitate_fired_items;
	}

	private function gravitate_is_query_ignored($query)
	{
		foreach ($this->gravitate_ignores as $ignore)
		{
			if (strpos($query, $ignore) !== false)
			{
				return true;
			}
		}

		return false;
	}

	final private function get_gravitate_cache($query=false)
	{
		if($this->gravitate_debug)
		{
			$fired_query = $query;

			if($this->gravitate_is_query_ignored($query))
			{
				$fired_query.=' - REASON: Ignored List';
			}

			if(!class_exists('GRAVITATE_CACHE'))
			{
				$fired_query.=' - REASON: No Cache Object';
			}

			if(!$query)
			{
				$fired_query.=' - REASON: Query Empty';
			}

			if(empty($this->gravitate_config['database_enabled']))
			{
				$fired_query.=' - REASON: DB Caching Disabled';
			}

			$this->gravitate_fired_items[] = $fired_query.' - '.md5($query);
		}

		if(empty($_POST) && !$this->gravitate_is_query_ignored($query) && class_exists('GRAVITATE_CACHE') && $query && !empty($this->gravitate_config['database_enabled']))
		{
			$cache = GRAVITATE_CACHE::get($query, 'db', AUTH_KEY);
			if(!empty($cache) && is_array($cache))
			{
				$results = $cache['results'];
				$this->last_result = $cache['last_result'];
				$this->num_rows = $cache['num_rows'];

				$this->gravitate_cached_items[] = str_replace($this->prefix, '*_', $query);

				return $results;
			}
		}

		return false;
	}

	final private function set_gravitate_cache($query=false, $results=false)
	{
		if(class_exists('GRAVITATE_CACHE') && $query && $results !== false && !empty($this->gravitate_config['database_enabled']))
		{
			$cache_results = array('results'=>$results,'last_result'=>$this->last_result,'num_rows'=>$this->num_rows);
			return GRAVITATE_CACHE::set($query, $cache_results, false, 'db', AUTH_KEY);
		}

		return false;
	}

	final function get_results( $query = null, $output = OBJECT )
	{
		$results = $this->get_gravitate_cache('get_results::'.$query);
		if($results === false)
		{
			$this->gravitate_raw_items[] = 'get_results::'.str_replace($this->prefix, '*_', $query);
			$results = parent::get_results( $query, $output);
			$this->set_gravitate_cache('get_results::'.$query, $results);
		}
		return $results;
	}

	final function get_row( $query = null, $output = OBJECT, $y = 0 )
	{
		$results = $this->get_gravitate_cache('get_row::'.$query);
		if($results === false)
		{
			$this->gravitate_raw_items[] = 'get_row::'.str_replace($this->prefix, '*_', $query);
			$results = parent::get_row( $query, $output, $y);
			$this->set_gravitate_cache('get_row::'.$query, $results);
		}
		return $results;
	}

	final function get_var( $query = null, $x = 0, $y = 0 )
	{
		$results = $this->get_gravitate_cache('get_var::'.$query);
		if($results === false)
		{
			$this->gravitate_raw_items[] = 'get_var::'.str_replace($this->prefix, '*_', $query);
			$results = parent::get_var( $query, $x, $y);
			$this->set_gravitate_cache('get_var::'.$query, $results);
		}
		return $results;
	}

	final function get_col( $query = null , $x = 0 )
	{
		$results = $this->get_gravitate_cache('get_col::'.$query);
		if($results === false)
		{
			$this->gravitate_raw_items[] = 'get_col::'.str_replace($this->prefix, '*_', $query);
			$results = parent::get_col( $query, $x);
			$this->set_gravitate_cache('get_col::'.$query, $results);
		}
		return $results;
	}

	final function query( $query )
	{
		// Check Select
		if(preg_match( '/^\s*(select)\s/i', $query))
		{
			// Not sure if we can do anything here.
			if(!in_array('get_var::'.$query, $this->gravitate_raw_items) && !in_array('get_row::'.$query, $this->gravitate_raw_items) && !in_array('get_results::'.$query, $this->gravitate_raw_items) && !in_array('get_col::'.$query, $this->gravitate_raw_items))
			{
				$this->gravitate_raw_items[] = str_replace($this->prefix, '*_', $query);
			}
		}

		$results = parent::query($query);

		// write operations may need to invalidate the cache
		if(class_exists('GRAVITATE_CACHE_INIT') && !$this->gravitate_is_query_ignored($query) && $results && preg_match( '/^\s*(create|alter|truncate|drop|insert|delete|update|replace)\s/i', $query))
		{
			GRAVITATE_CACHE_INIT::clear_all_cache();
			//file_put_contents(dirname(__FILE__).'/data-grav.txt', date("dS g:i:sa").' - SQL: '.$query."\n\r", FILE_APPEND);
		}

		return $results;
	}
}

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
	}

	if(!empty($gravitate_cache_config['database_enabled']) && file_exists(WP_CONTENT_DIR.'/plugins/gravitate-cache/gravitate-cache-class.php'))
	{
		include_once(WP_CONTENT_DIR.'/plugins/gravitate-cache/gravitate-cache-class.php');
	}
}

