<?php

class Mmb_Plugin extends Mmb_Core
{	
	var $worker_maitanance_mode = false;
    function __construct()
    {
        parent::__construct();
    }
    
    /*************************************************************
    * FACADE functions
    * (functions to be called after a remote XMLRPC from Master)
    **************************************************************/
    
    function get_list($args)
    {
        $this->_escape($args);
        $username = $args[0];
        $password = $args[1];
        
        if (!$user = $this->login($username, $password)) 
        {
            return $this->error;
        }
        
        if(!current_user_can('activate_plugins'))
            return new IXR_Error(401, 'Sorry, you cannot manage plugins on the remote blog.');
        
        $this->refresh_transient();
        
        $all_plugins = get_plugins();
        
        // we don't allow managing our own plugin this way
        // better force the user to directly manage it!
         global $mmb_plugin_dir;
         $worker_plug = basename($mmb_plugin_dir).'/init.php';
         unset($all_plugins[$worker_plug]);
        
//        $current = get_transient('update_plugins');
        $current = $this->mmb_get_transient('update_plugins');
        
        foreach ((array)$all_plugins as $plugin_file => $plugin_data) 
        {
            //Translate, Apply Markup, Sanitize HTML
            $plugin_data = _get_plugin_data_markup_translate($plugin_file, $plugin_data, false, true);
            $all_plugins[$plugin_file] = $plugin_data;

            //Filter into individual sections
            if (is_plugin_active($plugin_file)) 
            {
                $all_plugins[$plugin_file]['status'] = 'active';
                $active_plugins[$plugin_file] = $plugin_data;
            } 
            else 
            {
                $all_plugins[$plugin_file]['status'] = 'inactive';
                $inactive_plugins[$plugin_file] = $plugin_data;
            }

            if (isset($current->response[$plugin_file]))
            {
                $all_plugins[$plugin_file]['new_version'] = $current->response[$plugin_file];
            }
        }
        
        return $all_plugins;
    }
    
    /**
    * Deactivates a plugin locally
    * 
    * @param mixed $args
    */
    function deactivate($args)
    {
        $this->_escape($args);
        $username = $args[0];
        $password = $args[1];
        $plugin_files = $args[2];
        
        if (!$user = $this->login($username, $password)) 
        {
            return $this->error;
        }
        
        if(!current_user_can('activate_plugins'))
        {
            return new IXR_Error(401, 'Sorry, you are not allowed to deactivate plugins on the remote blog.');
        }
        
        $this->refresh_transient();
        
        $success = deactivate_plugins($plugin_files);
        if(is_wp_error($success))
            return false;
        chdir(WP_PLUGIN_DIR);

        if(is_array($plugin_files)) return true;
        // get the plugin again
        return $this->_get_plugin_data($plugin_files);
    }
    
    /**
    * Activates a plugin locally
    * 
    * @param mixed $args
    */
    function activate($args)
    {
        $this->_escape($args);
        $username = $args[0];
        $password = $args[1];
        $plugin_files = $args[2];
        if (!$user = $this->login($username, $password)) 
        {
            return $this->error;
        }
        
        if(!current_user_can('activate_plugins'))
        {
            return new IXR_Error( 401, 'Sorry, you are not allowed to manage plugins on the remote blog.');
        }
        
        $this->refresh_transient();
        //@lk test
//        $this->_log($plugin_file);
//        ob_start();
//        var_dump($plugin_file);
//        $lk_data = ob_get_clean();
//        file_put_contents('testlog.txt', $lk_data);
        $success = activate_plugins($plugin_files, '', FALSE);
        if(is_wp_error($success))
            return false;
        chdir(WP_PLUGIN_DIR);
        
        if(is_array($plugin_files)) return true;
        // get the plugin again
        return $this->_get_plugin_data($plugin_files);
    }
    
    /**
    * Upgrades a plugin locally
    * 
    * @param mixed $args
    */
    function upgrade($args, $login_required = TRUE, $reget_plugin_data = TRUE)
    {
        $this->_escape($args);
        $username = $args[0];
        $password = $args[1];
        $plugin_file = $args[2];
        
        if ($login_required && !$user = $this->login($username, $password)) 
        {
            return $this->error;
        }
        
        if(!current_user_can('activate_plugins'))
        {
            return new IXR_Error(401, 'Sorry, you are not allowed to upgrade plugins on the remote blog.');
        }
        
//        $current = get_transient('update_plugins');
        $current = $this->mmb_get_transient('update_plugins');
//        $this->_log($current);
        // keep track of plugin active status
        $needs_reactivaton = is_plugin_active($plugin_file);
        
        // the Plugin upgrader will echo some HTML on its own
        // so we wrap it into some output buffering to avoid
        // breaking the XML response
        ob_start();
//        include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        
        $upgrader = new Mmb_Plugin_Upgrader();
        $result = $upgrader->upgrade($plugin_file);
//                $this->_log($result);

        // $this->_log($output);
        
        if (is_wp_error($result))
        {
            return new IXR_Error(401, 'Sorry, this plugin could not be upgraded. ' . $result->get_error_message());
        }
        
        // remember to reactivate the plugin if needed
        if($needs_reactivaton)
        {
            activate_plugin($plugin_file);
        }
        
        unset($current->response[$plugin_file]);
        set_transient('update_plugins', $current);
        
        $output = ob_get_clean();
        
        if ($reget_plugin_data)
        {
            chdir(WP_PLUGIN_DIR);
            
            // get the plugin again. 
            return $this->_get_plugin_data($plugin_file);
        }
    }
    
    function upgrade_multiple($args)
    {
		include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		$this->_escape($args);
        $username = $args[0];
        $password = $args[1];
        $plugin_files = $args[2];

        if ($this->worker_maitanance_mode){
			$this->_last_worker_message(array('error' => 'Blog is currently under maintenance. Please try again after few minutes.'));
            die();
        }
		if (!$user = $this->login($username, $password)) 
        {
            return $this->error;
        }
        
        if(!current_user_can('activate_plugins'))
        {
            return new IXR_Error(401, 'Sorry, you are not allowed to upgrade plugins on the remote blog.');
        }
		$upgrader = new Plugin_Upgrader( new Bulk_Plugin_Upgrader_Skin( compact( 'nonce', 'url' ) ) ); 
		$this->worker_maitanance_mode = true;
		$result = $upgrader->bulk_upgrade( $plugin_files );
	    if(is_wp_error($upgrader->skin->result) || !$upgrader->plugin_info()){
			$this->worker_maitanance_mode = false;
			$error = is_wp_error($upgrader->skin->result) ? $upgrader->skin->result->get_error_message() : 'Check your FTP details. <a href="http://managewp.com/user-guide#ftp" title="More Info" target="_blank">More Info</a>' ;
			$this->_last_worker_message(array('error' => print_r($error, true)));
		}else {
			$this->worker_maitanance_mode = false;
			$return_pl = array();
			foreach($result as $plugin_file => $data){
				$data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_file);
				$return_pl[] = $data['Name'];
			}
			$this->_last_worker_message(array('success' => $return_pl));
		}
    }
    /**
    * Upgrades all upgradable plugins on this blog
    * 
    * @param mixed $args
    */
    function upgrade_all($args)
    {
		include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        $this->_escape($args);
        $username = $args[0];
        $password = $args[1];
        
		if ($this->worker_maitanance_mode){
			$this->_last_worker_message(array('error' => 'Blog is currently under maintenance. Please try again after few minutes.'));
            die();
        }
        if (!$user = $this->login($username, $password)) 
        {
            return $this->error;
        }

		$plugin_files = array();
        $current = $this->mmb_get_transient('update_plugins');
		foreach ((array)$current->response as $file => $data){
			$plugin_files[] = $file;
		}
		$upgrader = new Plugin_Upgrader( new Bulk_Plugin_Upgrader_Skin( compact( 'nonce', 'url' ) ) );
		$this->worker_maitanance_mode = true;
		$result = $upgrader->bulk_upgrade( $plugin_files );
		if(is_wp_error($upgrader->skin->result) || !$upgrader->plugin_info()){
			$this->worker_maitanance_mode = false;
			$error = is_wp_error($upgrader->skin->result) ? $upgrader->skin->result->get_error_message() : 'Check your FTP details. <a href="http://managewp.com/user-guide#ftp" title="More Info" target="_blank">More Info</a>' ;
			$this->_last_worker_message(array('error' => print_r($error, true)));
		}else {
			$this->worker_maitanance_mode = false;
			$this->_last_worker_message(array('success' => $result));
		}
    }
    
    /**
    * Deletes a plugin locally
    * 
    * @param mixed $args
    */
    function delete($args)
    {
        $this->_escape($args);
        $username = $args[0];
        $password = $args[1];
        $plugin_files = $args[2];
        
        if (!$user = $this->login($username, $password)) 
        {
            return $this->error;
        }
        
        if(!current_user_can('delete_plugins'))
        {
            return new IXR_Error(401, 'Sorry, you are not allowed to delete plugins from the remote blog.');
        }
        
        $this->refresh_transient();
        
        ob_start();

        // WP is rather stupid here
        // the agrument MUST be an array????
        if(!is_array($plugin_files))
            $plugin_files = array($plugin_files);
        
        $result = delete_plugins($plugin_files);
        ob_end_clean();
        if (is_wp_error($result))
        {
            return new IXR_Error(401, 'Sorry, this plugin could not be deleted. ' . $result->get_error_message());
        }
        
        return TRUE;
    }
    
    /**
    * Our own functions to get plugin data that fits our needs
    * (that is, with status and new version info)
    * 
    * @param mixed $plugin_file
    */
    function _get_plugin_data($plugin_file)
    {
        $plugin = get_plugin_data($plugin_file);
        $plugin['status'] = is_plugin_active($plugin_file) ? 'active' : 'inactive';
        
        
        // check for new version
//        $current = get_transient('update_plugins');
        $current = $this->mmb_get_transient('update_plugins');
        
        if (isset($current->response[$plugin_file]))
        {
            $plugin['new_version'] = $current->response[$plugin_file];
        }
        
        return $plugin;
    }
    
    /**
    * Gets a list of plugins with upgrade available
    * 
    */
    function get_upgradable_plugins()
    {
        $all_plugins = get_plugins();
        $upgrade_plugins = array();

        $this->refresh_transient();
        
//        $current = get_transient('update_plugins');
        $current = $this->mmb_get_transient('update_plugins');
//        $test = $this->mmb_get_transient('update_plugins');
//        $this->_log($test);
        foreach ((array)$all_plugins as $plugin_file => $plugin_data) 
        {
            //Translate, Apply Markup, Sanitize HTML
            $plugin_data = _get_plugin_data_markup_translate($plugin_file, $plugin_data, false, true);
            if (isset($current->response[$plugin_file]))
            {
                $current->response[$plugin_file]->name = $plugin_data['Name'];
                $current->response[$plugin_file]->old_version = $plugin_data['Version'];
                $current->response[$plugin_file]->file = $plugin_file;
                $upgrade_plugins[] = $current->response[$plugin_file];
            }
        }
        
        return $upgrade_plugins;
    }
    
    /**
    * Installs a plugin by its slug
    * 
    * @param mixed $args
    */
    function install($args)
    {
        $this->_escape($args);
        $username = $args[0];
        $password = $args[1];
        $slug = $args[2];
        $activate = (bool)$args[3];
        
        if (!$user = $this->login($username, $password)) 
        {
            return $this->error;
        }
        if (!current_user_can('install_plugins'))
            return new IXR_Error(401, 'You do not have sufficient permissions to install plugins for this blog.');
        
        $this->refresh_transient();
            
        ob_start();
        include_once ABSPATH . 'wp-admin/includes/plugin-install.php'; 
//        include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

        $api = plugins_api('plugin_information', array('slug' => $slug, 'fields' => array('sections' => false))); 

        if (is_wp_error($api))
             return new IXR_Error(401, 'Failed to install plugin. ' . $api->get_error_message());
        
//        $upgrader = new Plugin_Upgrader();
        $upgrader = new Mmb_Plugin_Upgrader();
        $upgrader->install($api->download_link);
        
        $output = ob_get_clean();

        if ($activate)
        {
            $this->activate(array($username, $password, $upgrader->plugin_info()));
        }
        
        // return $this->get_list(array($username, $password));
        // list refresh should be requested by the client to have WP update the plugin list itself
        return TRUE;
    }
    
    function refresh_transient()
    {
        delete_transient('update_plugins');
        $current = $this->mmb_get_transient('update_plugins');
        wp_update_plugins();
        
        return $current;
    }
    
    /**
    * Uploads a plugin, given its package url
    * 
    * @param mixed $args
    */
    function upload_by_url($args)
    {
        $this->_escape($args);
        $username = $args[0];
        $password = $args[1];
        $url = $args[2];
        $activate = $args[3];
		
        if (!$user = $this->login($username, $password)) 
        {
            return $this->error;
        }
        if($activate && !current_user_can('activate_plugins')){
			return new IXR_Error( 401, 'Sorry, you are not allowed to manage plugins on the remote blog.');
		}
        if (!current_user_can('install_plugins')){
            return new IXR_Error(401, 'Sorry, you are not allowed to install plugins on the remote blog.');
        }
        
        if (!$this->_init_filesystem())
            return new IXR_Error(401, 'Plugin could not be installed: Failed to initialize file system.');
		/*if($this->_is_ftp_writable){
				$this->_last_worker_message(array('error' => 'Blog needs a ftp permissions to complete task.'));
				die();
		}*/
		include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		$upgrader = new Plugin_Upgrader( new Plugin_Installer_Skin( compact( 'nonce', 'url' ) ) ); 
		ob_start();
		$result = $upgrader->install($url);
		ob_end_clean();
		
		if(is_wp_error($upgrader->skin->result) || !$upgrader->plugin_info()){
			$error = is_wp_error($upgrader->skin->result) ? $upgrader->skin->result->get_error_message() : 'Check your FTP details. <a href="http://managewp.com/user-guide#ftp" title="More Info" target="_blank">More Info</a>' ;
			$this->_last_worker_message(array('error' => print_r($error, true)));
		}else {
			if($activate){
				$success = activate_plugin($upgrader->plugin_info(), '', FALSE);
				if(is_wp_error($success)){
					$this->_last_worker_message($success);
					return false;
				}
			}
			$data = get_plugin_data(WP_PLUGIN_DIR . '/' . $upgrader->plugin_info());
			$this->_last_worker_message(array('success' => $upgrader->plugin_info(), 'name' => $data['Name'], 'activate' => print_r($activate, true)));
		}
	}

	
}