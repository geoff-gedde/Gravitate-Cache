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
add_action('save_post', array( 'GRAVITATE_CACHE_INIT', 'save_post' ));
add_action('updated_option', array( 'GRAVITATE_CACHE_INIT', 'updated_option' ));
add_action('wp_ajax_gravitate_clear_cache', array( 'GRAVITATE_CACHE_INIT', 'ajax_clear_cache' ));
add_action('admin_bar_menu', array( 'GRAVITATE_CACHE_INIT', 'admin_bar_menu' ), 999);

if(!empty($gravitate_cache_class))
{
	add_action('init', array( $gravitate_cache_class, 'start_cache' ));
}

class GRAVITATE_CACHE_INIT {

	static $version = '0.9.0';

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
			// Create Advanced Cache Dropin
			if(!file_exists(WP_CONTENT_DIR.'/advanced-cache.php'))
			{
				if($advanced_cache = file_get_contents(dirname(__FILE__).'/templates/advanced-cache.php'))
				{
					file_put_contents(WP_CONTENT_DIR.'/advanced-cache.php', $advanced_cache);
				}
			}

			// Create DB Cache Dropin
			if(!file_exists(WP_CONTENT_DIR.'/db.php'))
			{
				if($advanced_cache = file_get_contents(dirname(__FILE__).'/templates/db.php'))
				{
					file_put_contents(WP_CONTENT_DIR.'/db.php', $advanced_cache);
				}
			}

			// Add WP_CACHE
			$config_file = ABSPATH.'wp-config.php';
			if((!defined('WP_CACHE') || !WP_CACHE) && file_exists($config_file))
			{
				$config_data = file_get_contents($config_file);

				if($config_data)
				{
					$config_data = preg_replace(
		            '~<\?(php)?~',
		            "\\0\r\n" . "/** Enable Page Cache */\r\n" .
            "define('WP_CACHE', true); // Added by Gravitate Cache\r\n" .
            "define('GRAVITATE_CACHE_TIMESTART', microtime(true)); // Added by Gravitate Cache\r\n",
		            $config_data,
		            1);

					file_put_contents($config_file, $config_data);
				}
			}

			// Create Config File
			if(!file_exists(WP_CONTENT_DIR.'/gravitate-cache-config.php'))
			{
				if($gravitate_cache_config = file_get_contents(dirname(__FILE__).'/templates/gravitate-cache-config.php'))
				{
					file_put_contents(WP_CONTENT_DIR.'/gravitate-cache-config.php', $gravitate_cache_config);
				}
			}

			self::clear_all_cache();
		}
	}

	static function deactivate()
	{
		self::clear_all_cache();
	}

	static function save_post()
	{
		self::clear_all_cache();
		self::pre_load_pages();
		file_put_contents(WP_CONTENT_DIR.'/data-grav.txt', date("dS g:i:sa")." - Post Saved\n\r", FILE_APPEND);
	}

	static function ajax_clear_cache()
	{
		if(is_user_logged_in() && current_user_can('manage_options'))
		{
			self::clear_all_cache();
			self::pre_load_pages();
			file_put_contents(WP_CONTENT_DIR.'/data-grav.txt', date("dS g:i:sa")." - Forced Clear \n\r", FILE_APPEND);
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
		$clear_options = array(
			'permalink_structure',
			'siteurl',
			'home',
			'posts_per_page',
			);

		if(in_array($option, $clear_options))
		{
			self::clear_all_cache();
			self::pre_load_pages();
			file_put_contents(WP_CONTENT_DIR.'/data-grav.txt', date("dS g:i:sa")." - Updated Option \n\r", FILE_APPEND);
		}
	}

	static function clear_all_cache()
	{
		self::clear_cache('.cache');
		file_put_contents(WP_CONTENT_DIR.'/data-grav.txt', date("dS g:i:sa")." - ALL \n\r", FILE_APPEND);
	}

	private static function clear_cache($group='')
	{
		if(defined('WP_CONTENT_DIR'))
		{
			if(is_dir(WP_CONTENT_DIR.'/cache/gravitate_cache'))
			{
				foreach (glob(WP_CONTENT_DIR.'/cache/gravitate_cache/*'.$group.'*') as $file)
				{
					unlink($file);
				}


			}
		}
	}

	static function get_config()
	{
		$gravitate_cache_config = false;

		if(file_exists(WP_CONTENT_DIR.'/gravitate-cache-config.php'))
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
		}

		return $gravitate_cache_config;
	}

	private static function pre_load_pages()
	{
		$config = self::get_config();

		if(!empty($config['preload_urls']) && $config['preload_urls'] != 'none')
		{
			// Preload Menu Links
			if($config['preload_urls'] == 'menus')
			{
				if($menus = get_registered_nav_menus())
				{
					foreach ($menus as $menu => $title)
					{
						$locations = get_nav_menu_locations();

						if(isset($locations[ $menu ]))
						{
							if(!empty($menu->termd_id))
							{
								$menu = wp_get_nav_menu_object( $locations[ $menu ] );
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
								else
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
			}

			// Preload All Pages
			if($config['preload_urls'] == 'pages')
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
	}

	private static function pre_load_page($url)
	{
		if(!empty($url))
		{
			$headers = get_headers($url);

			// If file error then remove from preload
			if(empty($headers[0]) || strpos($headers[0], '200') === false)
			{
				$nurl = str_replace('//', '', $url);
				$split = substr($nurl, strpos($nurl, '/'));
				$hash = md5($split);
				$file = WP_CONTENT_DIR.'/cache/gravitate_cache/'.$hash.'.cache';
				if(file_exists($file))
				{
					unlink($file);
				}
			}
		}
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
				'meta'  => array( 'onclick' => "jQuery(\".gravitate-cache-admin-bar-menu > a\").addClass(\"loading\");jQuery.post(\"".admin_url('admin-ajax.php')."\", {\"action\": \"gravitate_clear_cache\"}, function(response) {jQuery(\".gravitate-cache-admin-bar-menu > a\").removeClass(\"loading\");if(response){alert(response);}});" )
			);
			$wp_admin_bar->add_node( $args );
		}
	}

	static function settings()
	{
		if(!empty($_GET['page']) && $_GET['page'] == 'gravitate_cache_settings')
		{
			$config = self::get_config();

			if(empty($config))
			{
				$error = 'Missing Config File';
			}

			if(defined('GRAVITATE_CACHE_LOCK_SETTINGS') && GRAVITATE_CACHE_LOCK_SETTINGS == true)
			{
				$error = 'The Settings have been locked.  Please see your Web Developer.  This is most likely intensional as they don\'t want you to mess with the settings :)';
			}

			// Check for Error
			if(!empty($error))
			{
				?>
					<div class="wrap">
					<h2>Gravitate Cache Settings</h2>
					<h4 style="margin: 6px 0;">Version <?php echo self::$version;?></h4>
					<?php if($error){?><div class="error"><p><?php echo $error; ?></p></div><?php } ?>
					</div>
				<?php
			}
			else
			{
				// Save Config
				if(!empty($_POST['save_settings']) && !empty($_POST['config']))
				{
					if($config_str = file_get_contents(dirname(__FILE__).'/templates/gravitate-cache-config.php'))
					{

						$config_str = str_replace("false,/*page_enabled*/", (!empty($_POST['config']['page_enabled']) ? 'true,' : 'false,'), $config_str);
						$config['page_enabled'] = (!empty($_POST['config']['page_enabled']) ? true : false);

						$config_str = str_replace("false,/*database_enabled*/", (!empty($_POST['config']['database_enabled']) ? 'true,' : 'false,'), $config_str);
						$config['database_enabled'] = (!empty($_POST['config']['database_enabled']) ? true : false);

						$config_str = str_replace("false,/*object_enabled*/", (!empty($_POST['config']['object_enabled']) ? 'true,' : 'false,'), $config_str);
						$config['object_enabled'] = (!empty($_POST['config']['object_enabled']) ? true : false);

						if(!empty($_POST['config']['type']))
						{
							$config_str = str_replace("'disk'", "'".sanitize_key($_POST['config']['type'])."'", $config_str);
							$config['type'] = $_POST['config']['type'];
						}

						if(!empty($_POST['config']['server']))
						{
							$config_str = str_replace("'127.0.0.1:11211'", "'".str_replace(array("'",'"',' '), '', $_POST['config']['server'])."'", $config_str);
							$config['server'] = str_replace(' ', '', $_POST['config']['server']);
						}

						if(!empty($_POST['config']['excluded_urls']))
						{
							$config_str = str_replace("'wp-.*\\\\.php'", "'".implode(",", explode("\n", str_replace(array("'",'"',' ',"\r"), array('','','',''), trim($_POST['config']['excluded_urls']))))."'", $config_str);
							$config['excluded_urls'] = str_replace(array("'",'"',' ',"\r"), '', trim($_POST['config']['excluded_urls']));
						}

						if(!empty($_POST['config']['preload_urls']))
						{
							$config_str = str_replace("'menus'", "'".sanitize_key($_POST['config']['preload_urls'])."'", $config_str);
							$config['preload_urls'] = $_POST['config']['preload_urls'];
						}

						if($fp = fopen(WP_CONTENT_DIR.'/gravitate-cache-config.php', 'w'))
						{
							if(fwrite($fp, $config_str))
							{
								$success = 'Settings Saved Successfully';
								self::pre_load_pages();
							}
							else
							{
								$error = 'There was an error saving the Settings (Cannot write to disk). Please try again.';
							}
							fclose($fp);
						}
						else
						{
							$error = 'There was an error saving the Settings (Cannot access disk). Please try again.';
						}
					}
				}

				// If No Error then Show Form
				?>
					<div class="wrap">
						<h2>Gravitate Cache Settings</h2>
						<h4 style="margin: 6px 0;">Version <?php echo self::$version;?></h4>

						<?php if(!empty($success)){?><div class="updated"><p><?php echo $success; ?></p></div><?php } ?>
						<?php if(!empty($error)){?><div class="error"><p><?php echo $error; ?></p></div><?php } ?>

						<br>
						This Plugin is still in Beta
						<br>
						<br>
						<form method="post">
							<input type="hidden" name="save_settings" value="1">
							<div>
								<label for="page_enabled"><input id="page_enabled" type="checkbox" name="config[page_enabled]" value="1" <?php checked($config['page_enabled'], true);?> <?php disabled( defined('GRAVITATE_CACHE_CONFIG_PAGE_ENABLED'), true ); ?>> &nbsp; <strong>Enable Page Cache</strong></label><br>
								<label for="database_enabled"><input id="database_enabled" type="checkbox" name="config[database_enabled]" value="1" <?php checked($config['database_enabled'], true);?><?php disabled( defined('GRAVITATE_CACHE_CONFIG_DATABASE_ENABLED'), true ); ?>> &nbsp; <strong>Enable Database Cache</strong></label><br>
								<label for="object_enabled"><input id="object_enabled" type="checkbox" name="config[object_enabled]" value="1" <?php checked($config['object_enabled'], true);?><?php disabled( defined('GRAVITATE_CACHE_CONFIG_OBJECT_ENABLED'), true ); ?>> &nbsp; <strong>Enable Object Cache</strong></label><br><br><br>
							</div>
							<div>
								<label for="type"><strong>Caching Type</strong></label>
								<br>
								<select id="type" name="config[type]" <?php disabled( defined('GRAVITATE_CACHE_CONFIG_TYPE'), true ); ?>>
									<option value="auto" <?php selected($config['type'], 'auto');?>>Auto Detect the best Method</option>
									<option value="disk" <?php selected($config['type'], 'disk');?>>Disk (Simple and works on most Servers)</option>
									<option value="memcache" <?php selected($config['type'], 'memcache');?>>Memcache (Faster and more Secure)</option>
									<option value="memcached" <?php selected($config['type'], 'memcached');?>>MemcacheD (Faster and more Secure)</option>
								</select>
								<br>
								<br>
							</div>
							<div>
								<label for="server"><strong>Memcache/MemcacheD Server IP and Port</strong></label>
								<br>
								<input style="width: 180px;" type="text" value="<?php echo (!empty($config['server']) ? $config['server'] : '127.0.0.1:11211');?>" id="server" name="config[server]" <?php disabled( defined('GRAVITATE_CACHE_CONFIG_SERVER'), true ); ?>> default is 127.0.0.1:11211
								<br>
								<br>
							</div>
							<div>
								<label for="type"><strong>Page Caching - Excluded Urls (Regex)</strong></label>
								<br>
								<textarea id="type" name="config[excluded_urls]" rows="5" cols="40" <?php disabled( defined('GRAVITATE_CACHE_CONFIG_EXCLUDED_URLS'), true ); ?>><?php echo str_replace('\\\\', '\\', (implode("\n", explode(',', $config['excluded_urls']))));?></textarea>
								<br>
								<br>
							</div>
							<div>
								<label for="type"><strong>Page Caching - Preload Urls when cache is cleared.</strong></label>
								<br>
								<select id="type" name="config[preload_urls]" <?php disabled( defined('GRAVITATE_CACHE_CONFIG_PRELOAD_URLS'), true ); ?>>
									<option value="none" <?php selected($config['preload_urls'], 'none');?>>No, Do Not Preload Pages.</option>
									<option value="menus" <?php selected($config['preload_urls'], 'menus');?>>Only Items listed in the Menus. (Recommended)</option>
									<option value="pages" <?php selected($config['preload_urls'], 'pages');?>>All Pages. (This does not include Posts or custom post types)</option>
								</select>
								<br>
								<br>
							</div>
							<br>

							<p><input type="submit" value="Save Settings" class="button button-primary" id="submit" name="submit"></p>
						</form>

				    </div>
				<?php
			}
		}
	}
}
