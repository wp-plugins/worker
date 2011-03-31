<?php
/*************************************************************
 * 
 * plugin.class.php
 * 
 * Upgrade Plugins
 * 
 * 
 * Copyright (c) 2011 Prelovac Media
 * www.prelovac.com
 **************************************************************/
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
        include_once(ABSPATH . 'wp-admin/includes/file.php');
        
        if ((!defined('FTP_HOST') || !defined('FTP_USER') || !defined('FTP_PASS')) && (get_filesystem_method(array(), false) != 'direct'))
        {
                return array(
                    'error' => 'Failed, please <a target="_blank" href="http://managewp.com/user-guide#ftp">add FTP details</a></a>'
                );
        }


        $upgradable_plugins = $this->get_upgradable_plugins();
        
        $ready_for_upgrade = array();
        if (!empty($upgradable_plugins)) {
            foreach ($upgradable_plugins as $upgrade) {
                $ready_for_upgrade[] = $upgrade->file;
            }
        }
        $return = '';
        if (!empty($ready_for_upgrade)) {
            ob_start();            
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
            require_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');
            if (class_exists('Plugin_Upgrader')) {
                $upgrader = new Plugin_Upgrader(new Bulk_Plugin_Upgrader_Skin(compact('nonce', 'url')));
                $result   = $upgrader->bulk_upgrade($ready_for_upgrade);
                ob_end_clean();
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
            } else {
                ob_end_clean();
                return array(
                    'error' => 'Could not initialize upgrader.'
                );
            }
        }
        return array(
            'error' => 'No plugins to upgrade at the moment'
        );
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
    
}
?>