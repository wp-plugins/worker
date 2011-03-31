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

include_once(ABSPATH . 'wp-admin/includes/file.php');
include_once(ABSPATH . 'wp-admin/includes/plugin.php');
include_once(ABSPATH . 'wp-admin/includes/theme.php');
include_once(ABSPATH . 'wp-admin/includes/misc.php');
include_once(ABSPATH . 'wp-admin/includes/template.php');
include_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');
if(!$wp_filesystem){
	WP_Filesystem();
}
class MMB_Installer extends MMB_Core
{
    function __construct()
    {	
		@set_time_limit( 300 );
		parent::__construct();
    }
   
	function mmb_maintenance_mode($enable = false, $maintenance_message = '') {
		global $wp_filesystem;
		
		$maintenance_message .= '<?php $upgrading = ' . time() . '; ?>';
		
		$file = $wp_filesystem->abspath() . '.maintenance';
		if($enable){
			$wp_filesystem->delete($file);
			$wp_filesystem->put_contents($file, $maintenance_message, FS_CHMOD_FILE);
		}else {
			$wp_filesystem->delete($file);
		}
	}
	
    function install_remote_file($params){
		
		global $wp_filesystem;
		extract($params);
		
		if(!isset($package) || empty($package))
			return array('error'  => '<p>No files received. Internal error.</p>');
		
		if(defined('WP_INSTALLING') && file_exists(ABSPATH . '.maintenance'))
			return array('error'  => '<p>Site under maintanace.</p>');;
		
		$upgrader = new WP_Upgrader();
		$destination = $type == 'themes' ? WP_CONTENT_DIR . '/themes' : WP_PLUGIN_DIR;
		
		
		foreach($package as $package_url){
			$key = basename($package_url);
			$install_info[$key] = @$upgrader->run(array(
                'package' => $package_url,
                'destination' => $destination,
                'clear_destination' => false, //Do not overwrite files.
                'clear_working' => true,
                'hook_extra' => array()
            ));
		}
		
		if($activate){
			$all_plugins = get_plugins();
			foreach($all_plugins as $plugin_slug => $plugin){
				$plugin_dir = preg_split('/\//', $plugin_slug);
				foreach($install_info as $key => $install){
					if(!$install || is_wp_error($install))
						continue;
					
					if($install['destination_name'] == $plugin_dir[0]){
						$install_info[$key]['activated'] = activate_plugin($plugin_slug, '', false);
					}
				}
			}
		}
		return $install_info;
	}
	
    /**
     * Upgrades WordPress locally
     *
     */
    function upgrade($params)
    {
        ob_start();
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/misc.php');
        require_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');
        
		if(!$wp_filesystem)
			WP_Filesystem();
        
		global $wp_version, $wp_filesystem;
        $upgrader = new WP_Upgrader();
        $updates  = $this->mmb_get_transient('update_core');
        $current  = $updates->updates[0];
        
        
        // Is an update available?
        if (!isset($current->response) || $current->response == 'latest')
            return array(
                'upgraded' => ' has latest ' . $wp_version . ' WordPress version.'
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
        ob_end_clean();
        
        $update_core = update_core($working_dir, $wp_dir);
        
        if (is_wp_error($update_core))
            return array(
                'error' => $this->mmb_get_error($update_core)
            );
        
        $this->mmb_delete_transient('update_core');
        return array(
            'upgraded' => ' upgraded sucessfully.'
        );
    }
    
}
?>