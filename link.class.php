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

class MMB_Link extends MMB_Core
{
    function __construct()
    {
        parent::__construct();
    }
    
    function add_link($args)
    {
    	extract($args);
    	
    	$params['link_url'] = esc_html($url);
			$params['link_url'] = esc_url($params['link_url']);
			$params['link_name'] = esc_html($name);
			$params['link_id'] = '';
			$params['link_description'] = $description;
			
			if(!function_exists(wp_insert_link))
			include_once (ABSPATH . 'wp-admin/includes/bookmark.php');
			
			$is_success = wp_insert_link($params);
			
			return $is_success ? true : array('error' => 'Failed to add link'); 
    }
    
}
?>