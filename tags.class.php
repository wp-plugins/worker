<?php

class Mmb_Tags extends Mmb_Core
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
        
        if(!current_user_can('manage_categories'))
            return new IXR_Error(401, 'Sorry, you cannot manage Tags on the remote blog.');
        global $wpdb;

        $count = count(get_tags(array('hide_empty' => FALSE)));
        $tags = get_tags(array(
                    'offset' => $offset,
                    'number' => $per_page,
                    'hide_empty' => FALSE
                ));
        return array('tags' => $tags, 'count' => $count);
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
        $taxonomy = $args[6];
        
        if (!$user = $this->login($username, $password)) 
        {
            return $this->error;
        }
        
        if(!current_user_can('manage_categories'))
            return new IXR_Error(401, 'Sorry, you cannot manage categories on the remote blog.');

        if(empty($slug))
            return new IXR_Error(401, 'Sorry, Slug cannot be Empty.');


        $taxonomy = !empty($taxonomy) ? $taxonomy : 'post_tag';
        $tag = get_term( $id, $taxonomy );
        $args = array('name' => $name,'slug'=> $slug,'description' => $description);
        $is_success = wp_update_term($id, $taxonomy, $args);
        

        

        if($is_success && !is_wp_error($is_success))
            return TRUE;
            else
        return new IXR_Error(401, 'Error Updating Tags. Try Again !!!');
        
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
        $tag_name = $args[2];
        $tag_slug = $args[3];
        $tag_desc = $args[4];
        
        if (!$user = $this->login($username, $password)) 
        {
            return $this->error;
        }
        
        if(!current_user_can('manage_categories'))
            return new IXR_Error(401, 'Sorry, you cannot manage categories on the remote blog.');
            
        // wordpress' category adding function
        
        $params = array('tag-name'  => $tag_name,
            'slug'              => $tag_slug,
            'description'     => $tag_desc
            );
        
        
         //$result = wp_create_tag($params);
         
         $result = wp_insert_term($tag_name, 'post_tag', $params);
         
         $term = get_terms('post_tag', array('include' => $result['term_id'], 'hide_empty'=>FALSE));
           if($result && !is_wp_error($is_success))
                return $term;
            else
                return new IXR_Error(401, 'Error Creating Tags. Try Again !!!');
//        if ($tag_id = wp_create_term($params))
//        {
//            return get_terms();
//        }
//
//        return FALSE;
    }

    function delete($args){
        $this->_escape($args);
        $username = $args[0];
        $password = $args[1];
        $term = $args[2];
        $taxonomy = $args[3];

        if (!$user = $this->login($username, $password))
        {
            return $this->error;
        }
         if(!current_user_can('manage_categories'))
            return new IXR_Error(401, 'Sorry, you cannot manage categories on the remote blog.');

          $taxonomy = !empty($taxonomy) ? $taxonomy : 'post_tag';

          $is_success = wp_delete_term($term, $taxonomy);

           if($is_success && !is_wp_error($is_success))
            return TRUE;
            else
        return new IXR_Error(401, 'Error Deleting Tags. Try Again !!!');  



    }
}
