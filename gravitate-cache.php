<?php
/*
Plugin Name: Gravitate Cache
Description: Simple Memcache/Memcached Caching.
Version: 0.9.0
Plugin URI: http://www.gravitatedesign.com
Author: Gravitate

*/

// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
	echo 'Gravitate Cache.';
	exit;
}

register_activation_hook( __FILE__, array( 'GRAVITATE_CACHE_INIT', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'GRAVITATE_CACHE_INIT', 'deactivate' ) );
add_action('admin_menu', array( 'GRAVITATE_CACHE_INIT', 'admin_menu' ));
add_action('updated_option', array( 'GRAVITATE_CACHE_INIT', 'updated_option' ));
add_action('wp_ajax_gravitate_clear_cache', array( 'GRAVITATE_CACHE_INIT', 'ajax_clear_cache' ));
add_action('admin_bar_menu', array( 'GRAVITATE_CACHE_INIT', 'admin_bar_menu' ), 999);
add_action('init', array( 'GRAVITATE_CACHE_INIT', 'start_cache' ));
add_filter( 'plugin_action_links_'.plugin_basename(__FILE__), array('GRAVITATE_CACHE_INIT', 'plugin_settings_link' ));


// Clear Cache on Certain hooks
add_action('save_post', array( 'GRAVITATE_CACHE_INIT', 'update_post' ));
// add_action('create_category', array( 'GRAVITATE_CACHE_INIT', 'clear_and_load' ));
// add_action('add_attachment', array( 'GRAVITATE_CACHE_INIT', 'clear_and_load' ));
// add_action('delete_attachment', array( 'GRAVITATE_CACHE_INIT', 'clear_and_load' ));
// add_action('delete_category', array( 'GRAVITATE_CACHE_INIT', 'clear_and_load' ));
// add_action('trashed_post', array( 'GRAVITATE_CACHE_INIT', 'clear_and_load' ));
// add_action('untrashed_post', array( 'GRAVITATE_CACHE_INIT', 'clear_and_load' ));
// add_action('deleted_post', array( 'GRAVITATE_CACHE_INIT', 'clear_and_load' ));
// add_action('edit_attachment', array( 'GRAVITATE_CACHE_INIT', 'clear_and_load' ));
// add_action('edit_category', array( 'GRAVITATE_CACHE_INIT', 'clear_and_load' ));
// add_action('updated_postmeta', array( 'GRAVITATE_CACHE_INIT', 'clear_and_load' ));
// add_action('comment_post', array( 'GRAVITATE_CACHE_INIT', 'clear_and_load' ));
// add_action('edit_comment', array( 'GRAVITATE_CACHE_INIT', 'clear_and_load' ));
// add_action('deleted_comment', array( 'GRAVITATE_CACHE_INIT', 'clear_and_load' ));
// add_action('trashed_comment', array( 'GRAVITATE_CACHE_INIT', 'clear_and_load' ));
// add_action('comment_closed', array( 'GRAVITATE_CACHE_INIT', 'clear_and_load' ));
// add_action('profile_update', array( 'GRAVITATE_CACHE_INIT', 'clear_and_load' ));
// add_action('user_register', array( 'GRAVITATE_CACHE_INIT', 'clear_and_load' ));
// add_action('delete_user', array( 'GRAVITATE_CACHE_INIT', 'clear_and_load' ));
add_action('updated_option', array( 'GRAVITATE_CACHE_INIT', 'updated_option' ));


include_once(WP_CONTENT_DIR.'/plugins/gravitate-cache/controllers/gravitate-cache-class.php');


class GRAVITATE_CACHE_INIT {

	private static $version = '0.9.0';
	private static $settings = array();
	private static $option_key = 'gravitate_cache_settings';

	static function activate()
	{
		if(is_plugin_active('w3-total-cache/w3-total-cache.php'))
		{
			trigger_error('You Must Deactivate W3TC Plugin as it will conflict with Gravitate Cache.', E_USER_ERROR);
			return false;
		}

		if(is_plugin_active('wp-super-cache/wp-cache.php'))
		{
			trigger_error('You Must Deactivate WP Super Cache Plugin as it will conflict with Gravitate Cache.', E_USER_ERROR);
			return false;
		}

		if(defined('WP_CONTENT_DIR'))
		{
			if(GRAVITATE_CACHE::is_enabled('page') || GRAVITATE_CACHE::is_enabled('object') || GRAVITATE_CACHE::is_enabled('database'))
			{
				self::add_wp_cache();
			}
			else
			{
				self::remove_wp_cache();
			}

			self::add_grav_cache_settings_file();

			self::clear_all_cache();
		}
	}

	static function deactivate()
	{
		self::clear_all_cache();

		self::disable_browser_cache();

		self::disable_db_cache();

		self::disable_page_cache();

		self::remove_wp_cache();
	}

	//This is needed as the CACHE Class does not have access to the Database.
	private static function add_grav_cache_settings_file()
	{
		if(!is_dir(WP_CONTENT_DIR.'/cache'))
		{
			mkdir(WP_CONTENT_DIR.'/cache');
		}

		if(is_dir(WP_CONTENT_DIR.'/cache'))
		{
			$contents = "<?php return '".stripslashes(json_encode(self::$settings, JSON_PRETTY_PRINT | JSON_FORCE_OBJECT))."';";
			file_put_contents(WP_CONTENT_DIR.'/cache/grav-cache-settings.php', $contents);
		}
	}

	private static function add_wp_cache()
	{
		if(defined('WP_CONTENT_DIR'))
		{
			// Add WP_CACHE
			$config_file = ABSPATH.'wp-config.php';
			if((!defined('WP_CACHE') || !WP_CACHE) && file_exists($config_file))
			{
				$config_data = file_get_contents($config_file);

				if($config_data && strpos($config_data, 'WP_CACHE'))
				{
					$config_data = str_replace("define('WP_CACHE', false);", "define('WP_CACHE', true);", $config_data);
					$config_data = str_replace("define('WP_CACHE',false);", "define('WP_CACHE', true);", $config_data);
					$config_data = str_replace("define('WP_CACHE', FALSE);", "define('WP_CACHE', true);", $config_data);
					$config_data = str_replace("define('WP_CACHE',FALSE);", "define('WP_CACHE', true);", $config_data);
					file_put_contents($config_file, $config_data);
				}
				else if($config_data)
				{
					$config_data = preg_replace(
		            '~<\?(php)?~',
		            "\\0\r\n" . "/** Enable Gravitate Cache */\r\n" .
		            "define('WP_CACHE', true); // Added by Gravitate Cache\r\n",
		            $config_data,
		            1);

					file_put_contents($config_file, $config_data);
				}


				if($config_data && !strpos($config_data, 'GRAVITATE_CACHE_TIMESTART'))
				{
					$config_data = preg_replace(
		            '~<\?(php)?~',
		            "\\0\r\n" . "define('GRAVITATE_CACHE_TIMESTART', microtime(true)); // Added by Gravitate Cache\r\n",
		            $config_data,
		            1);

					file_put_contents($config_file, $config_data);
				}
			}
		}
	}

	private static function remove_wp_cache()
	{
		$config_file = ABSPATH.'wp-config.php';
		if(defined('WP_CACHE') && WP_CACHE && file_exists($config_file))
		{
			$config_data = file_get_contents($config_file);

			if($config_data && strpos($config_data, "WP_CACHE"))
			{
				$wp_cache_list = array(
					"define('WP_CACHE', true);",
					"define('WP_CACHE',true);",
					"define('WP_CACHE', TRUE);",
					"define('WP_CACHE',TRUE);",
					'define("WP_CACHE", true);',
					'define("WP_CACHE",true);',
					'define("WP_CACHE", TRUE);',
					'define("WP_CACHE",TRUE);'
					);

				$config_data = str_replace($wp_cache_list, '', $config_data);
				file_put_contents($config_file, $config_data);
			}
		}
	}

	private static function disable_browser_cache()
	{
		$htaccess_file = ABSPATH.'.htaccess';
		if(file_exists($htaccess_file))
		{
			if($contents = file_get_contents($htaccess_file))
			{
				$browser_contents = file_get_contents(dirname(__FILE__).'/templates/htaccess.txt');
				if($browser_contents && strpos($contents, 'Gravitate Cache/GZip Content'))
				{
					file_put_contents($htaccess_file, str_replace($browser_contents, '', $contents));
				}
			}
		}
	}

	private static function disable_page_cache()
	{
		if(defined('WP_CONTENT_DIR') && file_exists(WP_CONTENT_DIR.'/advanced-cache.php'))
		{
			unlink(WP_CONTENT_DIR.'/advanced-cache.php');
		}
	}

	private static function disable_db_cache()
	{
		if(defined('WP_CONTENT_DIR') && file_exists(WP_CONTENT_DIR.'/db.php'))
		{
			unlink(WP_CONTENT_DIR.'/db.php');
		}
	}

	private static function disable_object_cache()
	{
		if(defined('WP_CONTENT_DIR') && file_exists(WP_CONTENT_DIR.'/object-cache.php'))
		{
			unlink(WP_CONTENT_DIR.'/object-cache.php');
		}
	}

	static function start_cache()
	{
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		include plugin_dir_path( __FILE__ ).'gravitate-plugin-settings.php';

		new GRAV_CACHE_PLUGIN_SETTINGS(self::$option_key);

		self::get_settings(true);

		if(is_plugin_active('gravitate-cache/gravitate-cache.php') && class_exists('GRAVITATE_CACHE'))
		{
			if(GRAVITATE_CACHE::can_page_cache())
			{
				ob_start(array(__CLASS__, 'page_buffer_cache'));
			}
			else // Still add Details to page for debugging even if cache is not applicable
			{
				if(!defined('DOING_AJAX') &&
				   !defined('DOING_CRON') &&
				   !defined('APP_REQUEST') &&
				   !defined('XMLRPC_REQUEST') &&
				   (!defined('SHORTINIT') || (defined('SHORTINIT') && !SHORTINIT) ))
				{
					add_action('shutdown', array('GRAVITATE_CACHE_INIT', 'shutdown'));
				}
			}
		}
	}

	static function page_buffer_cache($buffer)
	{
		if(GRAVITATE_CACHE::can_page_cache())
		{
			// $buffer
			GRAVITATE_CACHE::set('page::uid'.GRAVITATE_CACHE::get_userid_hash().'::'.$_SERVER['REQUEST_URI'], $buffer, 0, 'pg', AUTH_SALT);
		}

		return $buffer.self::shutdown();
	}

	static function shutdown()
	{
		return GRAVITATE_CACHE::details();
	}

	static function clear_and_load()
	{
		self::get_settings();
		self::clear_all_cache();
		self::pre_load_pages();
	}

	static function clear_post($post_id=0, $reset=false)
	{
		if($post_id)
		{
			if($post_url = get_permalink($post_id))
			{
				self::clear_url($post_url);
				GRAVITATE_CACHE::clear('/post\.php(.+)'.$post_id.'/');
				GRAVITATE_CACHE::clear('/edit\.php(.+)post_type\='.get_post_type($post_id).'/');
				//GRAVITATE_CACHE::clear('/(wp_gg_posts)/');
				if($reset)
				{
					self::pre_load_page($post_url);
				}
			}
		}
		return true;
	}

	static function update_post($post_id=0)
	{
		self::clear_post($post_id, true);
	}

	static function clear_url($url)
	{
		if(strpos($url, '//') !== false)
		{
			$url = substr($url, (strpos($url, '//')+2));
			if(strpos($url, '/') !== false)
			{
				$url = substr($url, strpos($url, '/'));
			}
		}
		return self::delete('page::'.$url, true);
	}

	static function delete($key)
	{
		return GRAVITATE_CACHE::delete($key);
	}

	static function ajax_clear_cache()
	{
		if(is_user_logged_in() && current_user_can('manage_options'))
		{
			self::clear_and_load();
			//file_put_contents(WP_CONTENT_DIR.'/data-grav.txt', date("dS g:i:sa")." - Forced Clear \n\r", FILE_APPEND);
			echo 'Cached has been Cleared Successfully!';
		}
		else
		{
			echo 'Error: You Must be logged in and have the correct permissions to clear the cache.';
		}
		exit;
	}

	static function updated_option($option)
	{
		// $clear_options = array(
		// 	'permalink_structure',
		// 	'siteurl',
		// 	'home',
		// 	'posts_per_page',
		// 	);

		//if(in_array($option, $clear_options))
		if(!empty($_POST))
		{
			self::clear_and_load();
			//file_put_contents(WP_CONTENT_DIR.'/data-grav.txt', date("dS g:i:sa")." - Updated Option \n\r", FILE_APPEND);
		}
	}

	static function clear_all_cache()
	{
		self::clear_cache();
		//file_put_contents(WP_CONTENT_DIR.'/data-grav.txt', date("dS g:i:sa")." - ALL \n\r", FILE_APPEND);
	}

	private static function clear_cache($group='')
	{
		GRAVITATE_CACHE::clear($group);
	}

	private static function pre_load_pages()
	{
		if(!empty(self::$settings['preload_urls']) && self::$settings['preload_urls'] != 'none')
		{
			// Preload Menu Links
			if(self::$settings['preload_urls'] == 'menus')
			{
				if($menus = get_registered_nav_menus())
				{
					foreach ($menus as $menu => $title)
					{
						$locations = get_nav_menu_locations();

						if(isset($locations[ $menu ]))
						{
							$menu = wp_get_nav_menu_object( $locations[ $menu ] );

							if(!empty($menu->term_id))
							{
								$items = wp_get_nav_menu_items( $menu->term_id );

								if(!empty($items))
								{
									foreach ($items as $item)
									{
										if(strpos($item->url, site_url()) !== false)
										{
											self::pre_load_page($item->url);
										}
									}
								}
							}

							if(empty($items))
							{
								$menu = wp_page_menu( array('echo' => false) );
								preg_match_all('/href\=\"([^"]*)\"/s', $menu, $matches);

								if(!empty($matches[1]))
								{
									foreach ($matches[1] as $url)
									{
										self::pre_load_page($url);
									}
								}
							}
						}
					}
				}
			}

			// Preload All Pages
			if(self::$settings['preload_urls'] == 'pages')
			{
				if($pages = get_pages())
				{
					foreach ($pages as $page)
					{
						self::pre_load_page(get_permalink($page->ID));
					}
				}
			}

			// Preload Home Page
			self::pre_load_page(site_url());
		}

		if(is_user_logged_in() && !GRAVITATE_CACHE::is_enabled('exclude_wp_logged_in', 'excludes'))
		{
			self::pre_load_page('http://gg.local.com/wp-admin/edit.php');
			self::pre_load_page('http://gg.local.com/wp-admin/edit.php?post_type=page');
		}
	}

	private static function pre_load_page($page_url)
	{
		if(!empty($page_url))
		{
			$passed = true;

			if(!empty(self::$settings['excluded_urls']))
			{
				foreach (array_map('trim', explode(',', self::$settings['excluded_urls'])) as $url)
				{
					if(!empty($url))
					{
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

						if(preg_match('/'.$url.'/', $page_url))
						{
							$passed = false;
						}
					}
				}
			}

			if($passed)
			{
				$headers = get_headers($page_url);

				// If file error then remove from preload
				// if(empty($headers[0]) || strpos($headers[0], '200') === false)
				// {
				// 	$nurl = str_replace('//', '', $page_url);
				// 	$split = substr($nurl, strpos($nurl, '/'));
				// 	$hash = md5($split);
				// 	$file = WP_CONTENT_DIR.'/cache/gravitate_cache/'.$hash.'.cache';
				// 	if(file_exists($file))
				// 	{
				// 		unlink($file);
				// 	}
				// }
			}
		}
	}

	public static function plugin_settings_link($links)
	{
		$settings_link = '<a href="options-general.php?page=gravitate_cache_settings">Settings</a>';
		array_unshift($links, $settings_link);
		return $links;
	}

	static function admin_menu()
	{
		add_submenu_page( 'options-general.php', 'Grav Cache Settings', 'Grav Cache Settings', 'manage_options', 'gravitate_cache_settings', array( __CLASS__, 'settings' ));
	}

	static function admin_bar_menu( $wp_admin_bar )
	{
		$args = array(
			'id'    => 'gravitate_cache',
			'title' => 'Grav Cache',
			'href' => admin_url('options-general.php?page=gravitate_cache_settings'),
			'meta'  => array( 'class' => 'gravitate-cache-admin-bar-menu' )
		);
		$wp_admin_bar->add_node( $args );

		if(is_user_logged_in() && current_user_can('manage_options'))
		{
			$args = array(
				'id' => 'gravitate_cache_clear',
				'parent'    => 'gravitate_cache',
				'title' => 'Clear All Cache',
				'href' => '#',
				'meta'  => array( 'onclick' => "jQuery(\".gravitate-cache-admin-bar-menu > a\").addClass(\"loading\");jQuery.post(\"".admin_url('admin-ajax.php')."\", {\"action\": \"gravitate_clear_cache\"}, function(response) {jQuery(\".gravitate-cache-admin-bar-menu > a\").removeClass(\"loading\");if(response){alert(response);jQuery.get(\"".admin_url('index.php')."\");jQuery.get(\"".admin_url('edit.php')."\");jQuery.get(\"".admin_url('edit.php?post_type=page')."\");jQuery.get(\"".admin_url('plugins.php')."\");}});return false;" )
			);
			$wp_admin_bar->add_node( $args );
		}
	}

	/**
     * Returns the Settings Fields for specifc location.
     *
     * @param string $location
     *
     * @return array
     */
	private static function get_settings_fields($location = 'general')
	{
		switch ($location)
		{

			case 'general':

				$enables = array(
					'page' => 'Page Cache',
					'database' => 'Database Cache',
					'object' => 'Object Cache',
					'browser' => 'Browser Cache (For Apache Only)'
				);

				$types = array(
					'automemory' => 'Auto Detect from In Memory Only (More Secure)',
					'auto' => 'Auto Detect the best Method from all',
					'disk' => 'Disk (Simple and works on most Servers)',
					'memcache' => 'In Memory - Memcache (Faster and more Secure)',
					'memcached' => 'In Memory - MemcacheD (Modern, Faster and more Secure)',
					'redis' => 'In Memory - Redis (Modern, Faster and more Secure)',
				);

				$preloads = array(
					'none' => 'No, Do Not Preload Pages.',
					'menus' => 'Only Items listed in the Menus. (Recommended)',
					'pages' => 'All Pages. (Does not include Posts or custom post types). May take while...'
				);

				$excludes = array(
					'exclude_wp_admin' => 'WordPress Admin Panel - Don\'t use Caching while in the Admin Panel.',
					'exclude_wp_logged_in' => 'Logged In - Don\'t use Caching while logged in.'
				);

				$fields['enabled'] = array('type' => 'checkbox', 'label' => 'Enable', 'options' => $enables, 'description' => 'Check each Cache you wish to Enable');
				$fields['type'] = array('type' => 'select', 'label' => 'Caching Type', 'options' => $types, 'description' => 'Determine which cache Driver to use.');
				$fields['servers'] = array('type' => 'text', 'label' => 'In Memory Servers', 'description' => 'IP and Port.  Ex 127.0.0.1:11211 (Comma Seperated for Multiple Servers)');
				$fields['encryption'] = array('type' => 'checkbox', 'label' => 'Enable Encryption', 'options' => array('encrypt' => 'Enable'), 'description' => 'This may slow down the Caching, but should be used when using caching type "Disk" or on a Shared Hosting Server.<br>Contact your Hosting Provider to find out if your on a Shared Hosting Server.<br>(For fastest encryption make sure your Hosting Provider has "openssl" installed)');
				$fields['excludes'] = array('type' => 'checkbox', 'label' => 'Exclude', 'options' => $excludes, 'description' => 'Determine when caching should not be used.');
				$fields['excluded_urls'] = array('type' => 'textarea', 'label' => 'Page Caching - Excluded Urls', 'description' => 'Can use (Regex) One item per line.');
				$fields['preload_urls'] = array('type' => 'select', 'label' => 'Preload Urls', 'options' => $preloads, 'description' => 'Preload Urls when cache is cleared.');


			break;

		}

		return $fields;
	}

	/**
	 * Grabs the settings from the Settings class
	 *
	 * @param boolean $force
	 *
	 * @return void
	 */
	public static function get_settings($force=false)
	{
		self::$settings = GRAV_CACHE_PLUGIN_SETTINGS::get_settings($force);
	}

	static function settings()
	{
		// Get Settings
		self::get_settings(true);

		if(defined('GRAVITATE_CACHE_LOCK_SETTINGS') && GRAVITATE_CACHE_LOCK_SETTINGS == true)
		{
			$error = 'The Settings have been locked.  Please see your Web Developer.  This is most likely intensional as they don\'t want you to mess with the settings :)';
		}

		if(empty($error))
		{
			// Save Settings if POST
			$response = GRAV_CACHE_PLUGIN_SETTINGS::save_settings();

			if($response['error'])
			{
				$error = 'Error saving Settings. Please try again.';
			}
			else if($response['success'])
			{
				$success = 'Settings saved successfully.';
				self::pre_load_pages();

				// Update Plugin Settings
				self::get_settings(true);

				// Update CACHE File Settings - This is needed as the CACHE Class does not have access to the Database.
				self::add_grav_cache_settings_file();

				// Update CACHE Class Settings
				GRAVITATE_CACHE::get_settings();
			}
		}

		?>
			<div class="wrap">
			<h2>Gravitate Cache Settings BETA</h2>
			<h4 style="margin: 6px 0;">Version <?php echo self::$version;?></h4>

			<br>
			This Plugin is still in Beta
			<br>

		<?php

		// Save Config
		if(!empty($_POST['save_grav_settings']))
		{
			if(GRAVITATE_CACHE::is_enabled('browser'))
			{
				$htaccess_file = ABSPATH.'.htaccess';
				if(file_exists($htaccess_file))
				{
					if($contents = file_get_contents($htaccess_file))
					{
						$browser_contents = file_get_contents(dirname(__FILE__).'/templates/htaccess.txt');
						if($browser_contents && !strpos($contents, 'Gravitate Cache/GZip Content'))
						{
							if(!file_put_contents($htaccess_file, $browser_contents, FILE_APPEND))
							{
								$error = 'There was an error writing to the .htaccess file. Please try again.';
							}
						}
					}
				}
			}
			else
			{
				self::disable_browser_cache();
			}

			if(GRAVITATE_CACHE::is_enabled('page'))
			{
				if(defined('WP_CONTENT_DIR'))
				{
					if($advanced_cache = file_get_contents(dirname(__FILE__).'/templates/advanced-cache.php'))
					{
						file_put_contents(WP_CONTENT_DIR.'/advanced-cache.php', $advanced_cache);
					}
				}
			}
			else
			{
				self::disable_page_cache();
			}

			if(GRAVITATE_CACHE::is_enabled('database'))
			{
				if(defined('WP_CONTENT_DIR'))
				{
					if($advanced_cache = file_get_contents(dirname(__FILE__).'/templates/db.php'))
					{
						file_put_contents(WP_CONTENT_DIR.'/db.php', $advanced_cache);
					}
				}
			}
			else
			{
				self::disable_db_cache();
			}

			if(GRAVITATE_CACHE::is_enabled('object'))
			{
				if(defined('WP_CONTENT_DIR'))
				{
					if($advanced_cache = file_get_contents(dirname(__FILE__).'/templates/object-cache.php'))
					{
						file_put_contents(WP_CONTENT_DIR.'/object-cache.php', $advanced_cache);
					}
				}
			}
			else
			{
				self::disable_object_cache();
			}

			if(GRAVITATE_CACHE::is_enabled('page') || GRAVITATE_CACHE::is_enabled('object') || GRAVITATE_CACHE::is_enabled('database'))
			{
				self::add_wp_cache();
			}
			else
			{
				self::remove_wp_cache();
			}
		}

		if(!empty($success))
		{
			?><div class="updated"><p><?php echo $success; ?></p></div><?php
		}

		if(!empty($error))
		{
			?><div class="error"><p><?php echo $error; ?></p></div><?php
		}

		$section = (!empty($_GET['section']) ? $_GET['section'] : 'settings');

		if(!defined('GRAVITATE_CACHE_LOCK_SETTINGS') || (defined('GRAVITATE_CACHE_LOCK_SETTINGS') && GRAVITATE_CACHE_LOCK_SETTINGS == false))
		{
			switch($section)
			{
				// case 'advanced':
				// 	self::form('advanced');
				// break;

				// case 'developers':
				// 	self::developers();
				// break;

				default:
				case 'settings':
					self::form();
				break;
			}
		}

		?>
		</div>

		<?php
	}

	/**
	 * Outputs the Form with the correct fields
	 *
	 * @param string $location
	 *
	 * @return type
	 */
	private static function form($location = 'general')
	{
		// Get Form Fields
		switch ($location)
		{
			default;
			case 'general':
				$fields = self::get_settings_fields();
				break;

			// case 'advanced':
			// 	$fields = self::get_settings_fields('advanced');
			// 	break;
		}

		GRAV_CACHE_PLUGIN_SETTINGS::get_form($fields);
	}
}
