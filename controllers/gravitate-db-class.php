<?php

// ini_set('error_reporting', E_ALL);
// ini_set('display_errors', 1);

// $num = bin2hex(md5(AUTH_SALT));


// $var = 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. .This is a Very Long String for Sure 1234567890';



// echo '<pre>';print_r($var);echo '</pre>';

// $var = bin2hex(bin2hex(base64_encode($var)));

// echo '<pre>';print_r($var);echo '</pre>';

// exit;

// //chunk_split($var, 3,

// $var = number_format((floor(bin2hex(bin2hex($var)))), 0, '', '');

// echo '<pre>';print_r($var);echo '</pre>';

// //$var str_split($var, 3

// echo '<pre>';print_r($num);echo '</pre>';


// $var = number_format(($var + $num), 0, '', '');


// echo '<pre>- - - <br>';print_r($var);echo '<br>- - - - - </pre>';

// $var = ($var - 8945934879283579572987498759287298742);

// $var  = number_format($var, 0, '', '');

// echo '<pre>';print_r($var);echo '</pre>';

// $var = pack("H*" , $var);

// echo '<pre>';print_r($var);echo '</pre>';












// exit;


// $var = chunk_split($var, 3, md5(AUTH_SALT));

// echo '<pre>';print_r($var);echo '</pre>';

// $var = chunk_split($var, 3, md5(AUTH_SALT));

// echo '<pre>';print_r($var);echo '</pre>';



// $var = str_replace(md5(AUTH_SALT), '', $var);

// echo '<pre>';print_r($var);echo '</pre>';

// $var = gzdeflate($var);

// echo '<pre>';print_r($var);echo '</pre>';

// $var = base64_encode($var);

// echo '<pre>';print_r($var);echo '</pre>';

// $var = base64_decode($var);

// echo '<pre>';print_r($var);echo '</pre>';

// $var = gzinflate($var);

// echo '<pre>';print_r($var);echo '</pre>';

// $var = base64_decode($var);

// echo '<pre>';print_r($var);echo '</pre>';

// exit;


/**************************************
** Created by Gravitate Cache Plugin **
**************************************/

/**
 * Extend the native wpdb class to add caching functionality
 */
class GRAVITATE_CACHE_WPDB extends wpdb
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

		$this->gravitate_cache_settings = GRAVITATE_CACHE::$settings;
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
			'view',
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

	final private function get_key($query='')
	{
		$domain = (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '');
		preg_match_all('/('.$this->prefix.'[^(\s|\.]+)/', $query, $tables);
		return 'db::'.md5($domain.$query.AUTH_KEY).(!empty($tables[1]) ? '-'.implode('-',array_unique($tables[1])) : '');
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

			if(!class_exists('GRAVITATE_CACHE'))
			{
				$fired_query.=' - REASON: No Cache Object';
			}

			if(!$query)
			{
				$fired_query.=' - REASON: Query Empty';
			}

			if(class_exists('GRAVITATE_CACHE') && !GRAVITATE_CACHE::is_enabled('database'))
			{
				$fired_query.=' - REASON: DB Caching Disabled';
			}

			$this->gravitate_cache_fired_items[] = $fired_query.' - '.md5($query);
		}


		if(defined('WP_ADMIN') && GRAVITATE_CACHE::is_enabled('exclude_wp_admin', 'excludes'))
		{
			return false;
		}

		if(GRAVITATE_CACHE::get_user_logged_in_cookie() && GRAVITATE_CACHE::is_enabled('exclude_wp_logged_in', 'excludes'))
		{
			return false;
		}

		if(empty($_POST) && !$this->gravitate_cache_is_query_ignored($query) && class_exists('GRAVITATE_CACHE') && $query && GRAVITATE_CACHE::is_enabled('database'))
		{
			$cache = GRAVITATE_CACHE::get($this->get_key($query), 'db', $this->passphrase);

			if(!empty($cache['value']) && is_array($cache['value']))
			{
				$results = $cache['value']['results'];
				$this->last_result = $cache['value']['last_result'];
				$this->num_rows = $cache['value']['num_rows'];

				$this->gravitate_cached_items[] = str_replace(array("\n","\r","\t"), '', str_replace($this->prefix, '*_', $query));

				return $results;
			}
		}

		return false;
	}

	final private function set_gravitate_cache($query=false, $results=false)
	{
		if(defined('WP_ADMIN') && GRAVITATE_CACHE::is_enabled('exclude_wp_admin', 'excludes'))
		{
			return false;
		}

		if(GRAVITATE_CACHE::get_user_logged_in_cookie() && GRAVITATE_CACHE::is_enabled('exclude_wp_logged_in', 'excludes'))
		{
			return false;
		}

		if(class_exists('GRAVITATE_CACHE') && $query && $results !== false && GRAVITATE_CACHE::is_enabled('database'))
		{
			$cache_results = array('results'=>$results,'last_result'=>$this->last_result,'num_rows'=>$this->num_rows);
			return GRAVITATE_CACHE::set($this->get_key($query), $cache_results, false, 'db', $this->passphrase);
		}

		return false;
	}

	final function get_results( $query = null, $output = OBJECT )
	{
		$key = 'get_results::'.$query.'_'.$output;
		$results = $this->get_gravitate_cache($key);
		if($results === false)
		{
			$this->gravitate_cache_raw_items[] = 'get_results::'.str_replace(array("\n","\r","\t"), '', str_replace($this->prefix, '*_', $query));
			$results = parent::get_results( $query, $output);
			$this->set_gravitate_cache($key, $results);
		}
		return $results;
	}

	final function get_row( $query = null, $output = OBJECT, $y = 0 )
	{
		$key = 'get_row::'.$query.'_'.$output.'_'.$y;
		$results = $this->get_gravitate_cache($key);
		if($results === false)
		{
			$this->gravitate_cache_raw_items[] = 'get_row::'.str_replace(array("\n","\r","\t"), '', str_replace($this->prefix, '*_', $query));
			$results = parent::get_row( $query, $output, $y);
			$this->set_gravitate_cache($key, $results);
		}
		return $results;
	}

	final function get_var( $query = null, $x = 0, $y = 0 )
	{
		$key = 'get_var::'.$query.'_'.$x.'_'.$y;
		$results = false;
		if($query)
		{
			$results = $this->get_gravitate_cache($key);
		}
		if($results === false)
		{
			$this->gravitate_cache_raw_items[] = 'get_var::'.str_replace(array("\n","\r","\t"), '', str_replace($this->prefix, '*_', $query));
			$results = parent::get_var( $query, $x, $y);
			$this->set_gravitate_cache($key, $results);
		}
		return $results;
	}

	final function get_col( $query = null , $x = 0 )
	{
		$key = 'get_col::'.$query.'_'.$x;
		$results = $this->get_gravitate_cache($key);
		if($results === false)
		{
			$this->gravitate_cache_raw_items[] = 'get_col::'.str_replace(array("\n","\r","\t"), '', str_replace($this->prefix, '*_', $query));
			$results = parent::get_col( $query, $x);
			$this->set_gravitate_cache($key, $results);
		}
		return $results;
	}

	final function query( $query )
	{
		// Check Select
		if(preg_match( '/^\s*(select)\s/i', $query))
		{
			// Not sure if we can do anything here.
			if(!in_array('get_var::'.str_replace($this->prefix, '*_', $query), $this->gravitate_cache_raw_items) && !in_array('get_row::'.str_replace($this->prefix, '*_', $query), $this->gravitate_cache_raw_items) && !in_array('get_results::'.str_replace($this->prefix, '*_', $query), $this->gravitate_cache_raw_items) && !in_array('get_col::'.str_replace($this->prefix, '*_', $query), $this->gravitate_cache_raw_items))
			{
				$this->gravitate_cache_raw_items[] = 'query::'.str_replace(array("\n","\r","\t"), '', str_replace($this->prefix, '*_', $query));
			}
		}

		$results = parent::query($query);

		// write operations may need to invalidate the cache
		if(class_exists('GRAVITATE_CACHE_INIT') && !$this->gravitate_cache_is_query_ignored($query) && $results && preg_match( '/^\s*(create|alter|truncate|drop|insert|delete|update|replace)\s/i', $query))
		{
			preg_match_all('/('.$this->prefix.'[^(\s|\.]+)/', $query, $tables);
			if(!empty($tables[1]))
			{
				GRAVITATE_CACHE::clear('/('.implode('|',$tables[1]).')/');
			}
		}

		return $results;
	}
}

if(defined('WP_CONTENT_DIR') && file_exists(WP_CONTENT_DIR.'/plugins/gravitate-cache/controllers/gravitate-cache-class.php'))
{
	include_once(WP_CONTENT_DIR.'/plugins/gravitate-cache/controllers/gravitate-cache-class.php');
}

