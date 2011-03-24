<?php

class MMB_Plugin extends MMB_Core
{
    var $worker_maitanance_mode = false;
    function __construct()
    {
        parent::__construct();
    }
    
    
    /**
     * Upgrades all upgradable plugins on this blog
     * 
     * @param mixed $args
     */
    function upgrade_all($params)
    {	
		$upgradable_plugins = $this->_get_upgradable_plugins();
		
		$ready_for_upgrade =array();
		if(!empty($upgradable_plugins)){
			foreach($upgradable_plugins as $upgrade ){
				$ready_for_upgrade[] = $upgrade->file;
			}
		}
		$return = '';
        if (!empty($ready_for_upgrade)) {
            ob_start();
            include_once(ABSPATH . 'wp-admin/includes/file.php');
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
            require_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');
            if (class_exists('Plugin_Upgrader')) {
                $upgrader = new Plugin_Upgrader(new Bulk_Plugin_Upgrader_Skin(compact('nonce', 'url')));
                $result   = $upgrader->bulk_upgrade($ready_for_upgrade);
				ob_end_clean();
                foreach ($result as $plugin_slug => $plugin_info) {
					$data    = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_slug);
					if(!$plugin_info || is_wp_error($plugin_info)){
						$return .= '<code title="Please upgrade manually">' . $data['Name'] . '</code>  was not upgraded.<br />';
					}else{
						$return .= '<code>' . $data['Name'] . '</code> successfully upgraded.<br />';
					}
                }
				ob_end_clean();
                return array('upgraded' => $return);
            }
			else {
				ob_end_clean();
				return array('error' => 'Could not initialize upgrader.');
			}
        }
        return array('error' => 'No plugins to upgrade at the moment');
    }
    
     /**
     * Uploads a plugin, given its package url
     * 
     * @param mixed $args
     */
    function upload_by_url($args)
    {
        //print_r($args);
        $this->_escape($args);
        $plugin_url      = $args['url'];
        $activate_plugin = $args['activate'];
        
        if ($plugin_url) {
            ob_start();
            include_once(ABSPATH . 'wp-admin/includes/file.php');
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
            include_once(ABSPATH . 'wp-admin/includes/misc.php');
            include_once(ABSPATH . 'wp-admin/includes/template.php');
            include_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');
            
            $upgrader = new Plugin_Upgrader();
            
            $result = $upgrader->run(array(
                'package' => $plugin_url,
                'destination' => WP_PLUGIN_DIR,
                'clear_destination' => false, //Do not overwrite files.
                'clear_working' => true,
                'hook_extra' => array()
            ));
            
            ob_end_clean();
            
            
            if (is_wp_error($upgrader->skin->result) || !$upgrader->plugin_info()) {
                return array('bool' => false, 'message' => 'Plugin was not installed.');
            }
           
            if ($activate_plugin) {
                $success = activate_plugin($upgrader->plugin_info(), '', false);
                
                if (!is_wp_error($success)) {
                    return array('bool' => true, 'message' => 'Plugin '.$upgrader->result[destination_name].' successfully installed and activated ');
                }
                return array('bool' => true, 'message' => 'Plugin '.$upgrader->result[destination_name].' successfully installed ');
            } else {
                return (!$result or is_wp_error($result)) ? array('bool' => false, 'message' => 'Upload failed.') :  array('bool' => true, 'message' => 'Plugin '.$upgrader->result[destination_name].' successfully installed ');
            }
            
        } else
            return array('bool' => false, 'message' => 'Missing plugin.');
        
    }
    
    function _get_upgradable_plugins()
    {
		$current = $this->mmb_get_transient('update_plugins');
		$upgradable_plugins = array();
		if(!empty($current->response)){
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
		}
		else return array();
        
    }
    
}
?>