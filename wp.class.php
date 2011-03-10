<?php

class Mmb_WP extends Mmb_Core
{
    function __construct()
    {
        parent::__construct();
    }
    
    /*************************************************************
    * FACADE functions
    * (functions to be called after a remote XMLRPC from Master)
    **************************************************************/
    function check_version($args, $login_required = TRUE)
    {
        $this->_escape($args);
        
        $username = $args[0];
        if($login_required)
            $password = $args[1];
//            $password = $this->ende_instance->decrypt(base64_decode($args[1]));
        $get_default_data = (bool) $args[2];

        if ($login_required && !$user = $this->login($username, $password))
        {
                return $this->error;
        }

        if (!current_user_can('update_plugins'))
        {
            return new IXR_Error(401, 'You do not have sufficient permissions to upgrade WordPress on the remote blog.');
        }

        require_once(ABSPATH . 'wp-includes/version.php');

        $updates = get_core_updates();
		$update = $updates[0];
        global $wp_version;
        if (!isset($update->response) || 'latest' == $update->response)
        {
            if (!$get_default_data)
                return new IXR_Error(999, 'The remote blog has the latest version of WordPress. You do not need to upgrade.');

            // return default (current version) data
            // this is used when initial blog row
            return array(
                'current_version'   => $wp_version,
                'latest_version'    => FALSE,
            );
        }
        else
        {
            return array(
                'current_version'   => $wp_version,
                'latest_version'    => $update,
            );
        }
        }

        /**
        * Upgrades WordPress locally
        *
        */
    function upgrade($args){
	
        $username = $args[0];
//        $password = $this->ende_instance->decrypt(base64_decode($args[1]));
        $password = $args[1];

        if (!$user = $this->login($username, $password))
        {
            $this->_last_worker_message(array('error' => $this->error));
        }

        if(!current_user_can('administrator')){
            $this->_last_worker_message(array('error' => "You don't have permissions to upgrade this blog."));
			die();
		}

        $upgrade_info = $this->check_version($args);
		if(empty($upgrade_info['latest_version'])){
			$this->_last_worker_message(array('error' => print_r($upgrade_info, true)));
			die();
		}
        ob_start();
       
		include ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		$upgrader = new Core_Upgrader();
		$result = $upgrader->upgrade($upgrade_info['latest_version']);
		
		ob_end_clean();
		if(!$result){
			$this->_last_worker_message(array('success' => 'true', 'version' => $upgrade_info['latest_version']));
			
		}else {
			$this->_last_worker_message(array('error' => $result));
		}
    }
    
    /**
    * Gets updates to core and plugins (just like Tool->Upgrade)
    * 
    * @param mixed $args
    */
    function get_updates($args)
    {
        $this->_escape($args);
        
        $username = $args[0];
        $password = $args[1];
        
        if (!$user = $this->login($username, $password)) 
        {
                return $this->error;
        }
        
        $args[] = 1; // get default data
        
        return array(
            'core'      => $this->check_version($args, FALSE),
            'plugins'   => $this->get_plugin_instance()->get_upgradable_plugins(),
        );
    }
}