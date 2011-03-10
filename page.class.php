<?php

class Mmb_Page extends Mmb_Core
{
    function __construct()
    {
        parent::__construct();
    }
    
    /*************************************************************
    * FACADE functions
    * (functions to be called after a remote XMLRPC from Master)
    **************************************************************/
    function get_edit_data($args)
    {
        $this->_escape($args);
        $username = $args[0];
        $password = $args[1];
        $page_id = $args[2];
        
        if (!$user = $this->login($username, $password)) 
        {
            return $this->error;
        }
        
        if (!current_user_can('edit_page', $page_id))
            return new IXR_Error(401, 'You are not allowed to edit this page.');
        
        $tmp_pages = get_pages("exclude=$page_id");

        
        // trim off redundant data
        $parents = array();
        foreach ($tmp_pages as $tmp_page)
        {
            $parents[] = array(
                'ID'    => $tmp_page->ID,
                'title' => $tmp_page->post_title,
            );
        }
        
        $page = get_page($page_id, OBJECT, 'edit');
//        $this->_log($page);

        $tmp_custom = get_post_custom($page_id);
        $custom = array();
        if(is_array($tmp_custom))
            foreach ($tmp_custom as $key => $value_array){
                if ('_wp_page_template' == $key)
                {
                    // the page template! important!!!
                    $page->template = $value_array[0];
                }

                if ('_' == $key[0]) continue;
                foreach ($value_array as $value)
                {
                    $custom[$key][] = base64_encode($value); // keep the new lines
                }
            }
        
        
        // encode the page content and excerpt to keep the new lines
        // as they would be trimmed off during XMLRPC
        $page->post_content = base64_encode($page->post_content);
        $page->post_excerpt = base64_encode($page->post_excerpt);
        
        // visibility
        if ('private' == $page->post_status) 
        {
            $page->post_password = '';
            $page->visibility = 'private';
        } 
        elseif (!empty($page->post_password)) 
        {
            $page->visibility = 'password';
        } 
        else 
        {
            $page->visibility = 'public';
        }
        
        $templates = get_page_templates();
        if (empty($templates)) $templates = array();
        
        return array(
            'page'          => $page,
            'parents'       => $parents,
            'custom_fields' => $custom,
            'templates'     => $templates,
        );
    }
    
    /**
    * Gets necessary local data for page creation
    * 
    * @param mixed $args
    * @return IXR_Error
    */
    function get_new_data($args)
    {
        $this->_escape($args);
        $username = $args[0];
        $password = $args[1];
        
        if (!$user = $this->login($username, $password)) 
        {
            return $this->error;
        }
        
        if (!current_user_can('edit_pages'))
            return new IXR_Error(401, 'You are not allowed to create a new page.');
        
        $parents = array();
        foreach ((array)get_pages() as $tmp_page)
        {
            $parents[] = array(
                'ID'    => $tmp_page->ID,
                'title' => $tmp_page->post_title,
            );
        }
        
        $templates = get_page_templates();
        if (empty($templates)) $templates = array();
        
        $page = get_default_page_to_edit();
        
        // some modifications to the default WordPress data
        $page->post_date = date('Y-m-d H:i:s');
        $page->post_status = 'publish';

        return array(
            'page'          => $page,
            'parents'       => $parents,
            'custom_fields' => array(),     // default is an empty array
            'templates'     => $templates,
        );
    }
    
    /** 
    * Locally updates a page
    * 
    * @param mixed $args
    */
    function update($args)
    {
        $this->_escape($args);
        $username = $args[0];
        $password = $args[1];
        $page_data = unserialize(base64_decode($args[2]));
        
        if (!$user = $this->login($username, $password)) 
        {
            return $this->error;
        }
        
        if (!current_user_can('edit_page', $page_data['post_ID']))
            return new IXR_Error(401, 'You are not allowed to edit this page.');
        
        // wp_update_post needs ID key
        $page_data['ID'] = $page_data['post_ID'];
        
        // wrap the function inside an output buffer to prevent errors from printed
        ob_start();
        $custom_fields = get_post_custom($page_data['ID']);
        foreach ($custom_fields as $key => $value)
        {
            delete_post_meta($page_data['ID'], $key);
        }
        
        $result = edit_post($page_data);
        foreach ($page_data['meta'] as $id => $meta)
        {
            add_post_meta($page_data['ID'], $meta['key'], $meta['value']);
        }
        ob_end_clean();
        
        if ($result)
        {
            return 'Success';
        }
        
        return new IXR_Error(401, 'Failed to update the page.');
    }
    
    /** 
    * Locally creates a page
    * 
    * @param mixed $args
    */
    function create($args)
    {
        $this->_escape($args);
        $username = $args[0];
        $password = $args[1];
                
        if (!$user = $this->login($username, $password)) 
        {
            return $this->error;
        }
        
        if (!current_user_can('edit_pages'))
            return new IXR_Error(401, 'You are not allowed to create a new page.');

        $page_struct = unserialize(base64_decode($args[2]));
        $page_data = $page_struct['post_data'];
        $page_meta = $page_struct['post_extras']['post_meta'];

        //create post
        $page_id = wp_insert_post($page_data);
        if($page_id){
            //get current custom fields
            $cur_custom = get_post_custom($page_id);
            //check which values doesnot exists in new custom fields
            $diff_values = array_diff_key($cur_custom, $page_meta);
            if(is_array($diff_values))
                foreach ($diff_values as $meta_key => $value) {
                    delete_post_meta($page_id, $meta_key);
                }
            
            //insert new post meta
            foreach($page_meta as $key => $value){
                if(strpos($key, '_mmb') === 0 || strpos($key, '_edit') === 0)
                    continue;
                update_post_meta($page_id, $key, $value[0]);
            }
        }
        return $page_id;



        // wrap the function inside an output buffer to prevent errors from printed
//        ob_start();
//        $_POST = $page_data;
//        $post_ID = wp_write_post(); // this function gets data from $_POST
//        if (is_wp_error($post_ID))
//        {
//            return new IXR_Error(401, 'Failed to create the page. ' . $result->get_error_message());
//        }
//
//        if (empty($post_ID))
//        {
//            return new IXR_Error(401, 'Failed to create the page: Unknown error.');
//        }
//
//        foreach ($page_data['meta'] as $id => $meta)
//        {
//            add_post_meta($post_ID, $meta['key'], $meta['value']);
//        }
//        ob_end_clean();
//
//        if ($post_ID)
//        {
//            return 'Success';
//        }
//
//        return new IXR_Error(401, 'Failed to create the page.');
    }
}
