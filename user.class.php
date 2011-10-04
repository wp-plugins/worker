<?php
/*************************************************************
 * 
 * user.class.php
 * 
 * Add Users
 * 
 * 
 * Copyright (c) 2011 Prelovac Media
 * www.prelovac.com
 **************************************************************/

class MMB_User extends MMB_Core
{
    function __construct()
    {
        parent::__construct();
    }
    
    function add_user($args)
    {
    	
    	if(!function_exists('username_exists') || !function_exists('email_exists'))
    	 include_once(ABSPATH . WPINC . '/registration.php');
      
      if(username_exists($args['user_login']))
    	 return array('error' => 'Username already exists');
    	
    	if (email_exists($args['user_email']))
    		return array('error' => 'Email already exists');
    	
			if(!function_exists('wp_insert_user'))
			 include_once (ABSPATH . 'wp-admin/includes/user.php');
			
			$user_id =  wp_insert_user($args);
			
			if($user_id){
			
				if($args['email_notify']){
					//require_once ABSPATH . WPINC . '/pluggable.php';
					wp_new_user_notification($user_id, $args['user_pass']);
					
				}
			
				return $user_id;
			}else{
				return array('error' => 'User not added. Please try again.');
			}
			 
    }
    
}
?>