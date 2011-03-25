<?php
/*************************************************************
 * 
 * wp.class.php
 * 
 * Upgrade WordPress
 * 
 * 
 * Copyright (c) 2011 Prelovac Media
 * www.prelovac.com
 **************************************************************/
class MMB_WP extends MMB_Core
{
    function __construct()
    {
        parent::__construct();
    }
    
    /*************************************************************
     * FACADE functions
     * (functions to be called after a remote call from Master)
     **************************************************************/
    function check_version()
    {
        require_once(ABSPATH . 'wp-includes/version.php');
        require_once(ABSPATH . '/wp-admin/includes/update.php');
        
        $updates = get_core_updates();
        $update  = $updates[0];
        global $wp_version;
        
        if (!isset($update->response) || 'latest' == $update->response) {
            return array(
                'current_version' => $wp_version,
                'latest_version' => false
            );
        } else {
            return array(
                'current_version' => $wp_version,
                'latest_version' => $update
            );
        }
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