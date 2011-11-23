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
			$params['link_target'] = $link_target;
			$params['link_category'] = array();
			
			//Add Link category
			if(is_array($link_category) && !empty($link_category)){
				$terms = get_terms('link_category',array('hide_empty' => 0));
				
				if($terms){
					foreach($terms as $term){
						if(in_array($term->name,$link_category)){
							$params['link_category'][] = $term->term_id;
						}
					}
				}
			}
			
			//Add Link Owner
			$user_obj = get_userdatabylogin($user);
			if($user_obj && $user_obj->ID){
				$params['link_owner'] = $user_obj->ID;
			}
			
			
			if(!function_exists('wp_insert_link'))
				include_once (ABSPATH . 'wp-admin/includes/bookmark.php');
			
			$is_success = wp_insert_link($params);
			
			return $is_success ? true : array('error' => 'Failed to add link.'); 
    }
    
}
?>