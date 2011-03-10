<?php

class Mmb_Category extends Mmb_Core
{
    function __construct()
    {
        parent::__construct();
    }
    
    /*************************************************************
    * FACADE functions
    * (functions to be called after a remote XMLRPC from Master)
    **************************************************************/
    /**
    * Gets a list of local (slave) category
    * 
    * @param mixed $args
    * @return mixed
    */
    function get_list($args)
    {
        $this->_escape($args);
        $username = $args[0];
        $password = $args[1];
        $offset = $args[2];
        $per_page = $args[3];
        
        if (!$user = $this->login($username, $password)) 
        {
            return $this->error;
        }
        
        $count = count(get_categories(array('hide_empty' => FALSE)));
        $categories= get_categories(array(
                    'offset' => $offset,
                    'number' => $per_page,
                    'hide_empty' => FALSE
                ));
        
        $cat_dropdown = wp_dropdown_categories(array('echo' => false, 'hide_empty' => 0, 'hide_if_empty' => false, 'name' => 'category_parent', 'orderby' => 'name', 'hierarchical' => true, 'show_option_none' => __('None'))); 
        
        if(!current_user_can('manage_categories'))
            return new IXR_Error(401, 'Sorry, you cannot manage categories on the remote blog.');
        
        return array('categories' => $categories, 'count' => $count,'dropdown' => $cat_dropdown);
        
        //return get_categories(array('hide_empty' => FALSE));
    }
    
    /**
    * Updates a category locally
    * 
    * @param mixed $args
    */
    function update($args)
    {
        $this->_escape($args);
        $username = $args[0];
        $password = $args[1];
        $id = $args[2];
        $name = $args[3];
        $slug = $args[4];
        $description = $args[5];
        $parent = $args[6];
        
        if (!$user = $this->login($username, $password)) 
        {
            return $this->error;
        }
        
        if(!current_user_can('manage_categories'))
            return new IXR_Error(401, 'Sorry, you cannot manage categories on the remote blog.');
        
        $is_success = wp_update_category(array(
            'cat_ID'                => $id,
            'category_description'  => $description,
            'cat_name'              => $name,
            'category_nicename'     => $slug,
            'category_parent'				=> $parent,
        ));

        if(!$is_success)
        return new IXR_Error(401, 'Error Updating Category. Try Again !!!');
            else
        return TRUE;
    }
    
    /**
    * Adds a new category locally
    * 
    * @param mixed $args
    */
    function add($args)
    {
        $this->_escape($args);
        $username = $args[0];
        $password = $args[1];
        $params = $args[2];
        
        
        
        if (!$user = $this->login($username, $password)) 
        {
            return $this->error;
        }
        
        if(!current_user_can('manage_categories'))
            return new IXR_Error(401, 'Sorry, you cannot manage categories on the remote blog.');
            
        // wordpress' category adding function
        if ($cat_id = wp_insert_category($params))
        {
            return get_category($cat_id, ARRAY_A);
        }
        
        return FALSE;
    }
}
