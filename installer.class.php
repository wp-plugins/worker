<?php
/*************************************************************
 * 
 * installer.class.php
 * 
 * Upgrade WordPress
 * 
 * 
 * Copyright (c) 2011 Prelovac Media
 * www.prelovac.com
 **************************************************************/

class MMB_Installer extends MMB_Core
{
    function __construct()
    {
        @set_time_limit(300);
        parent::__construct();
		include_once(ABSPATH . 'wp-admin/includes/file.php');
		include_once(ABSPATH . 'wp-admin/includes/plugin.php');
		include_once(ABSPATH . 'wp-admin/includes/theme.php');
		include_once(ABSPATH . 'wp-admin/includes/misc.php');
		include_once(ABSPATH . 'wp-admin/includes/template.php');
		include_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');

		global $wp_filesystem;
        if (!$wp_filesystem)
            WP_Filesystem();
        
    }
    
    function mmb_maintenance_mode($enable = false, $maintenance_message = '')
    {
        global $wp_filesystem;
        
        $maintenance_message .= '<?php $upgrading = ' . time() . '; ?>';
        
        $file = $wp_filesystem->abspath() . '.maintenance';
        if ($enable) {
            $wp_filesystem->delete($file);
            $wp_filesystem->put_contents($file, $maintenance_message, FS_CHMOD_FILE);
        } else {
            $wp_filesystem->delete($file);
        }
    }
    
    function install_remote_file($params)
    {
        global $wp_filesystem;
        extract($params);
        
        if (!isset($package) || empty($package))
            return array(
                'error' => '<p>No files received. Internal error.</p>'
            );
        
        if (defined('WP_INSTALLING') && file_exists(ABSPATH . '.maintenance'))
            return array(
                'error' => '<p>Site under maintanace.</p>'
            );
        ;
        
        $upgrader    = new WP_Upgrader();
        $destination = $type == 'themes' ? WP_CONTENT_DIR . '/themes' : WP_PLUGIN_DIR;
        
        
        foreach ($package as $package_url) {
            $key                = basename($package_url);
            $install_info[$key] = @$upgrader->run(array(
                'package' => $package_url,
                'destination' => $destination,
                'clear_destination' => false, //Do not overwrite files.
                'clear_working' => true,
                'hook_extra' => array()
            ));
        }
        
        if ($activate) {
            $all_plugins = get_plugins();
            foreach ($all_plugins as $plugin_slug => $plugin) {
                $plugin_dir = preg_split('/\//', $plugin_slug);
                foreach ($install_info as $key => $install) {
                    if (!$install || is_wp_error($install))
                        continue;
                    
                    if ($install['destination_name'] == $plugin_dir[0]) {
                        $install_info[$key]['activated'] = activate_plugin($plugin_slug, '', false);
                    }
                }
            }
        }
		ob_clean();
        return $install_info;
    }
	
	function do_upgrade($params = null){
		
		if($params == null || empty($params))
			return array('failed' => 'No upgrades passed.');
		
		if ((!defined('FTP_HOST') || !defined('FTP_USER') || !defined('FTP_PASS')) && (get_filesystem_method(array(), false) != 'direct')) {
            return array(
                'error' => 'Failed, please <a target="_blank" href="http://managewp.com/user-guide#ftp">add FTP details</a></a>'
            );
        }
		$params = isset($params['upgrades_all']) ? $params['upgrades_all'] : $params;
			
		$core_upgrade = isset($params['wp_upgrade']) ? $params['wp_upgrade'] : array();
		$upgrade_plugins = isset($params['upgrade_plugins']) ? $params['upgrade_plugins'] : array();
		$upgrade_themes = isset($params['upgrade_themes']) ? $params['upgrade_themes'] : array();
		
		$upgrades = array();
		if(!empty($core_upgrade)){
			$upgrades['core'] = $this->upgrade_core($core_upgrade);
		}
		if(!empty($upgrade_plugins)){
			$plugin_files = array();
			foreach($upgrade_plugins as $plugin){
				$plugin_files[] = $plugin->file;
			}
			
			$upgrades['plugins'] = $this->upgrade_plugins($plugin_files);
			$this->mmb_maintenance_mode(false);
		}
		
		if(!empty($upgrade_themes)){
			$theme_temps = array();
			foreach($upgrade_themes as $theme){
				$theme_temps[] = $theme['theme_tmp'];
			}
			
			$upgrades['themes'] = $this->upgrade_themes($theme_temps);
			$this->mmb_maintenance_mode(false);
		}
		ob_clean();
		return $upgrades;
	}
    
    /**
     * Upgrades WordPress locally
     *
     */
    function upgrade_core($current)
    {
        ob_start();
        global $mmb_wp_version, $wp_filesystem;
        $upgrader = new WP_Upgrader();
        
        // Is an update available?
        if (!isset($current->response) || $current->response == 'latest')
            return array(
                'upgraded' => ' Upgraded successfully.'
            );
        
        $res = $upgrader->fs_connect(array(
            ABSPATH,
            WP_CONTENT_DIR
        ));
        if (is_wp_error($res))
            return array(
                'error' => $this->mmb_get_error($res)
            );
        
        $wp_dir = trailingslashit($wp_filesystem->abspath());
        
        $download = $upgrader->download_package($current->package);
        if (is_wp_error($download))
            return array(
                'error' => $this->mmb_get_error($download)
            );
        
        $working_dir = $upgrader->unpack_package($download);
        if (is_wp_error($working_dir))
            return array(
                'error' => $this->mmb_get_error($working_dir)
            );
        
        if (!$wp_filesystem->copy($working_dir . '/wordpress/wp-admin/includes/update-core.php', $wp_dir . 'wp-admin/includes/update-core.php', true)) {
            $wp_filesystem->delete($working_dir, true);
            return array(
                'error' => 'Unable to move update files.'
            );
        }
        
        $wp_filesystem->chmod($wp_dir . 'wp-admin/includes/update-core.php', FS_CHMOD_FILE);
        
        require(ABSPATH . 'wp-admin/includes/update-core.php');
        
        
        $update_core = update_core($working_dir, $wp_dir);
		ob_end_clean();
        
        if (is_wp_error($update_core))
            return array(
                'error' => $this->mmb_get_error($update_core)
            );
        ob_end_flush();
        return array(
            'upgraded' => ' Upgraded successfully.'
        );
    }
	
	
	function upgrade_plugins($plugins = false){
		if(!$plugins || empty($plugins))
			return array(
                'error' => 'No plugin files for upgrade.'
            );
		
		if (class_exists('Plugin_Upgrader') && class_exists('Bulk_Plugin_Upgrader_Skin')) {
			$upgrader = new Plugin_Upgrader(new Bulk_Plugin_Upgrader_Skin(compact('nonce', 'url')));
			$result   = $upgrader->bulk_upgrade($plugins);
			ob_end_clean();
			if (!empty($result)) {
				foreach ($result as $plugin_slug => $plugin_info) {
					$data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_slug);
					if (!$plugin_info || is_wp_error($plugin_info)) {
						$return .= '<code title="Please upgrade manually">' . $data['Name'] . '</code>  was not upgraded.<br />';
					} else {
						$return .= '<code>' . $data['Name'] . '</code> successfully upgraded.<br />';
					}
				}
				ob_end_clean();
				return array(
					'upgraded' => $return
				);
			   }
			   else
				return array(
					'error' => 'Upgrade failed.'
				);   
		} else {
			ob_end_clean();
			return array(
				'error' => 'WordPress update required first'
			);
		}
	}
	
	function upgrade_themes($themes = false){
		if(!$themes || empty($themes))
			return array(
                'error' => 'No theme files for upgrade.'
            );
			
		if (class_exists('Theme_Upgrader') && class_exists('Bulk_Theme_Upgrader_Skin')) {
			$upgrader = new Theme_Upgrader(new Bulk_Theme_Upgrader_Skin(compact('title', 'nonce', 'url', 'theme')));
			
			$result = $upgrader->bulk_upgrade($themes);
			
			$results = array();
			if (!empty($result)) {
				foreach ($result as $theme_tmp => $info) {
					if (is_wp_error($info) || !$info) {
						$results[$theme_tmp] = '<code title="Please upgrade manually">' . $theme_tmp . '</code> was not upgraded.<br />';
					} else {
						$results[$theme_tmp] = '<code>' . $theme_tmp . '</code> succesfully upgraded.<br />';
					}
				}
				return array(
					'upgraded' => implode('', $results)
				);
			} else
				return array(
					'error' => 'Upgrade failed.'
				);            
		} else {
			ob_end_clean();
			return array(
				'error' => 'WordPress update required first'
			);
		}
	}
    
	function get_upgradable_plugins()
    {
        $current            = $this->mmb_get_transient('update_plugins');
        $upgradable_plugins = array();
        if (!empty($current->response)) {
            foreach ($current->response as $plugin_path => $plugin_data) {
                if (!function_exists('get_plugin_data'))
                    include_once ABSPATH . 'wp-admin/includes/plugin.php';
                $data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_path);
                
                $current->response[$plugin_path]->name        = $data['Name'];
                $current->response[$plugin_path]->old_version = $data['Version'];
                $current->response[$plugin_path]->file        = $plugin_path;
                $upgradable_plugins[]                         = $current->response[$plugin_path];
            }
            return $upgradable_plugins;
        } else
            return array();
        
    }
	
	function get_upgradable_themes()
    {
        $all_themes     = get_themes();
        $upgrade_themes = array();
        
        $current = $this->mmb_get_transient('update_themes');
        foreach ((array) $all_themes as $theme_template => $theme_data) {
            if (!empty($current->response)) {
                foreach ($current->response as $current_themes => $theme) {
                    if ($theme_data['Template'] == $current_themes) {
                        $current->response[$current_themes]['name']        = $theme_data['Name'];
                        $current->response[$current_themes]['old_version'] = $theme_data['Version'];
                        $current->response[$current_themes]['theme_tmp']   = $theme_data['Template'];
                        $upgrade_themes[]                                  = $current->response[$current_themes];
                        continue;
                    }
                }
            }
        }
        
        return $upgrade_themes;
    }
}
?>