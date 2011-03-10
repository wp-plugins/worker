<?php
  
class Mmb_Theme extends Mmb_Core
{
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
        
        if(!current_user_can('switch_themes'))
            return new IXR_Error(401, 'Sorry, you are not allowed to manage themes on the remote blog.');
        
        $all_themes = get_themes();
		$theme_updates = $this->mmb_get_transient('update_themes');
        $current_theme = current_theme_info();
		
        foreach($all_themes as $theme_name => $theme_data){
			if(isset($theme_updates->response[$theme_data['Template']])){
				$all_themes[$theme_name]['new_version'] = $theme_updates->response[$theme_data['Template']]['new_version'];
				$all_themes[$theme_name]['new_url'] = $theme_updates->response[$theme_data['Template']]['url'];
			}
		}
		$activated_theme = $all_themes[$current_theme->name];
        unset($all_themes[$current_theme->name]);
        
        // I don't bother paging
        // who would have 100's of themes anyway?
        return array(
            'current'   => $activated_theme,
            'inactive'  => $all_themes,
        );
    }
    
    /**
    * Activates a theme locally
    * 
    * @param mixed $args
    */
    function activate($args)
    {
        $this->_escape($args);
        $username = $args[0];
        $password = $args[1];
        $template = $args[2];
        $stylesheet = $args[3];
        
        if (!$user = $this->login($username, $password)) 
        {
            return $this->error;
        }
        
        if(!current_user_can('switch_themes'))
            return new IXR_Error(401, 'Sorry, you are not allowed to activate themes on the remote blog.');
        
        switch_theme($template, $stylesheet);
        
        // get the new updated theme list
        return $this->get_list($args);
    }
    
    /**
    * Deletes a theme locally
    * 
    * @param mixed $args
    */
    function delete($args)
    {
        $this->_escape($args);
        $username = $args[0];
        $password = $args[1];
        $template = $args[2];
        
        if (!$user = $this->login($username, $password)) 
        {
            return $this->error;
        }
        
        if(!current_user_can('update_themes'))
        {
            return new IXR_Error(401, 'Sorry, you are not allowed to delete themes from the remote blog.');
        }
        
        ob_start();
        $result = delete_theme($template);
        ob_end_clean();
        if (is_wp_error($result))
        {
            return new IXR_Error(401, 'Theme could not be deleted. ' . $result->get_error_message());
        }
        
        return TRUE;
    }
    
    /**
    * Installs a theme locally
    * 
    * @param mixed $args
    */
    function install($args)
    {
        $this->_escape($args);
        $username = $args[0];
        $password = $args[1];
        $theme = $args[2];
        $activate = (bool)$args[3];
        
        if (!$user = $this->login($username, $password)) 
        {
            return $this->error;
        }
        
        if (!current_user_can('install_themes'))
        {
            return new IXR_Error(401, 'Sorry, you are not allowed to install themes on the remote blog.');
        }
        
        ob_start();
        
//        include_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');
        include_once(ABSPATH . 'wp-admin/includes/theme-install.php');
        
        $api = themes_api('theme_information', array('slug' => $theme, 'fields' => array('sections' => false)));

        if (is_wp_error($api))
        {
            return new IXR_Error(401, 'Could not install theme. ' . $api->get_error_message());
        }

        $upgrader = new Mmb_Theme_Upgrader();
        $result = $upgrader->install($api->download_link);
        
        if (is_wp_error($result))
        {
            return new IXR_Error(401, 'Theme could not be installed. ' . $result->get_error_message());
        }
        
        // activate!        
        if ($activate && $theme_info = $upgrader->theme_info())
        {
            $stylesheet = $upgrader->result['destination_name'];
            $template = !empty($theme_info['Template']) ? $theme_info['Template'] : $stylesheet;
        
            $this->activate(array($username, $password, $template, $stylesheet));
        }
        
        ob_end_clean();
        
        // get the updated theme list
        return $this->get_list($args);
    }
    
    /**
    * Uploads a theme given its URL
    * 
    * @param mixed $args
    */
    function upload_by_url($args)
    {
        $this->_escape($args);
        $username = $args[0];
        $password = $args[1];
        $url = $args[2];
        
        if (!$user = $this->login($username, $password)) 
        {
            return $this->error;
        }
        
        if (!current_user_can('install_themes'))
        {
            return new IXR_Error(401, 'Sorry, you are not allowed to install themes on the remote blog.');
        }
        
        if (!$this->_init_filesystem())
            return new IXR_Error(401, 'Theme could not be installed: Failed to initialize file system.');
        
        
        ob_start();
        $tmp_file = download_url($url);
        
        if(is_wp_error($tmp_file))
            return new IXR_Error(401, 'Theme could not be installed. ' . $response->get_error_message());
        
        $result = unzip_file($tmp_file, WP_CONTENT_DIR . '/themes');
        unlink($tmp_file);
        
        if(is_wp_error($result))
        {
            return new IXR_Error(401, 'Theme could not be extracted. ' . $result->get_error_message());
        }
        
        unset($args[2]);
    
        return $this->get_list($args);
    }
	function upload_theme_by_url($args){
		
		$this->_escape($args);
        $username = $args[0];
        $password = $args[1];
        $url = $args[2];
		
		if (!$user = $this->login($username, $password)) 
        {
            return $this->error;
        }
		if(!current_user_can('install_themes')){
			return new IXR_Error( 401, 'Sorry, you are not allowed to manage theme install on the remote blog.');
		}
        if (!$this->_init_filesystem())
            return new IXR_Error(401, 'Theme could not be installed: Failed to initialize file system.');
		
		ob_start();
		include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		$upgrader = new Theme_Upgrader(); 
		$result = $upgrader->install($url);
		ob_end_clean();
		if(is_wp_error($upgrader->skin->result) || !$upgrader->skin->result){
			$error = is_wp_error($upgrader->skin->result) ? $upgrader->skin->result->get_error_message() : 'Check your FTP details. <a href="http://managewp.com/user-guide#ftp" title="More Info" target="_blank">More Info</a>' ;
			$this->_last_worker_message(array('error' => print_r($error,true)));
		}else {
			$theme = $upgrader->theme_info();
			$this->_last_worker_message(array('success' => 'true', 'name' => $theme['Name']));
		}
		
	}
	function upgrade($args){
		$this->_escape($args);
        $username = $args[0];
        $password = $args[1];
        $template = $args[2];
        $stylesheet = $args[3];
        $directory = $args[3];
		$chmod = false;
		
		if (!$user = $this->login($username, $password)) 
        {
            return $this->error;
        }
		if(!current_user_can('install_themes')){
			return new IXR_Error( 401, 'Sorry, you are not allowed to manage theme install on the remote blog.');
		}
		$chmod = fileperms($directory);
		if($chmod != 0755 ){
			chmod($directory, 0755);
		}
		ob_start();
		include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		$upgrader = new Theme_Upgrader( new Theme_Upgrader_Skin( compact('title', 'nonce', 'url', 'theme') ) );
		$result = $upgrader->upgrade($stylesheet);
		ob_end_clean();
		
		if(is_wp_error($upgrader->skin->result) || !$upgrader->theme_info()){
			$error = is_wp_error($upgrader->skin->result) ? $upgrader->skin->result->get_error_message() : 'Check your FTP details. <a href="http://managewp.com/user-guide#ftp" title="More Info" target="_blank">More Info</a>' ;
			$this->_last_worker_message(array('error' => print_r($error)));
		}else {
			$theme = $upgrader->theme_info();
			$this->_last_worker_message(array('success' => 'true', 'name' => $theme['Name']));
		}
		chmod($directory, $chmod);
	}
	function upgrade_all($args){
		$this->_escape($args);
        $username = $args[0];
        $password = $args[1];
        $themes = $args[2];
		$chmod = false;
		
		if (!$user = $this->login($username, $password)) 
        {
            return $this->error;
        }
		if(!current_user_can('install_themes')){
			return new IXR_Error( 401, 'Sorry, you are not allowed to manage theme install on the remote blog.');
		}

		if(!empty($themes)){
			
			
		
			ob_start();
			
			include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			$upgrader = new Theme_Upgrader(new Mmb_Bulk_Theme_Upgrader_Skin( compact('title', 'nonce', 'url', 'theme') ));
			$result = $upgrader->bulk_upgrade($themes);
			
			ob_end_clean();
			if(is_wp_error($result) || !$result){
				$error = is_wp_error($result) ? $result->get_error_message() : 'Check your FTP details. <a href="http://managewp.com/user-guide#ftp" title="More Info" target="_blank">More Info</a>' ;
				$this->_last_worker_message(array('error' => print_r($error, true)));
			}
			else {
				$message = '';
				foreach($result as $theme_tmp => $info){
					$message .= '<code>'.$theme_tmp.'</code><br />';
				}
				$this->_last_worker_message(array('success' => 'true', 'message' => $message));
			}
			
		}else {
			$this->_last_worker_message(array('error' => 'No themes to upgrade.'));
		}
	}
	
	function get_upgradable_themes(){
        
		$all_themes = get_themes();
        $upgrade_themes = array();
		
        //$this->refresh_transient();
        
//        $current = get_transient('update_plugins');
        $current = $this->mmb_get_transient('update_themes');
//        $test = $this->mmb_get_transient('update_plugins');
//        $this->_log($test);
        foreach ((array)$all_themes as $theme_template => $theme_data){
			foreach ($current->response as $current_themes => $theme){
				if ($theme_data['Template'] == $current_themes)
				{
					$current->response[$current_themes]['name'] = $theme_data['Name'];
					$current->response[$current_themes]['old_version'] = $theme_data['Version'];
					$current->response[$current_themes]['theme_tmp'] = $theme_data['Template'];
					$upgrade_themes[] = $current->response[$current_themes];
					continue;
				}
			}
        }
        
        return $upgrade_themes;
    }
}
