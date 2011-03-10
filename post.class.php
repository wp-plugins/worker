<?php

class Mmb_Post extends Mmb_Core
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
        $offset = $args[2];
        $number = $args[3];
        $status = $args[4];
        $category = $args[5];
        $tag = $args[6];
        $author = $args[7];
		
        if (!$user = $this->login($username, $password)) 
        {
            return $this->error;
        }
        
        if (!current_user_can('edit_posts'))
            return new IXR_Error(401, 'You are not allowed to manage posts.');
            
        if($category)
        $category_query = "&category=$category";
        if($tag)
        $tag_query = "&tag=$tag";
        if($author)
        $author_query = "&author=$author";
        
        $posts = get_posts("offset=$offset&post_status=$status&numberposts=$number".$category_query.$tag_query.$author_query);
        
        
        foreach ($posts as &$post)
        {
		
            // trim off unnecessary data to save bandwidth and speed
            $post->post_content = null;
            $post->post_excerpt = null;

            // get categories and tags data
            $cats = array();            
            foreach (get_the_category($post->ID) as $k => $cat)
            {		
            		$cats[$k]['name'] = $cat->cat_name;
            		$cats[$k]['ID'] = $cat->cat_ID;		
            		//$post->categories .= $cat->cat_name . ', ';
            }
            
            //$post->categories = rtrim($post->categories, ', ');
            $post->categories = $cats;
            
            $tags = array();
            
            foreach ( (array)get_the_tags($post->ID) as $k => $tag)
            {	
            		$tags[$k]['name'] = $tag->name;
            		$tags[$k]['slug'] = $tag->slug;
                //$post->tags .= $tag->name . ', ';
            }
            
            $post->tags = $tags;
            //$post->tags = rtrim($post->tags, ', ');
            
            // how about author?
            $author = get_userdata($post->post_author);
            $post->author = array('author_name' => $author->nickname, 'author_id' => $author->ID);
        }
        
		
       //Get number of queried post without offset 
        $queried_posts = get_posts("offset=-1&post_status=-1&numberposts=-1".$category_query.$tag_query.$author_query);
        
        // category
        $categories = get_categories(array(
                    'number' => 0,
                    'hide_empty' => 1
                ));
                
        $data = array(
            'posts'         => $posts,
            'post_counts'   => wp_count_posts('post', 'readable'),
            'query_post_counts' => count($queried_posts),
            'categories' => $categories
        );
        
        return $data;
    }
    
    /**
    * Gets data to edit a post
    * 
    * @param mixed $args
    */
    function get_edit_data($args)
    {
        $this->_escape($args);
        $username = $args[0];
        $password = $args[1];
        $post_ID = $args[2];
        
        if (!$user = $this->login($username, $password)) 
        {
            return $this->error;
        }
        
        if (!current_user_can('edit_post', $post_ID))
            return new IXR_Error(401, 'You are not allowed to edit this post.');
        
        $post = get_post($post_ID);

        if (empty($post->ID))
            return new IXR_Error(401, 'You attempted to edit a post that doesn&#8217;t exist. Perhaps it was deleted?');

        if ('trash' == $post->post_status)
            return new IXR_Error(401, 'You can&#8217;t edit this post because it is in the Trash. Please restore it and try again.');
        
        $post = get_post_to_edit($post_ID);
        $post->post_content = base64_encode($post->post_content);
        $post->post_excerpt = base64_encode($post->post_excerpt);
        
        // wordpress don't provide information about a post's categories
        // do it our own
        foreach ((array)get_the_category($post_ID) as $cat)
        {
//            $post->categories[] = $cat->cat_ID;
            $post->categories[] = $cat->name;
        }
        
        // same goes with the tags. What was Matt doing????
        foreach ((array)get_the_tags($post_ID) as $tag)
        {
            $post->tags .= $tag->name . ', ';
        }
        
        $post->tags = rtrim($post->tags, ', ');
        
        // get the categories
        foreach ((array)get_categories() as $cat)
        {
            $categories[] = array(
                'ID'    => $cat->cat_ID,
                'name'  => $cat->cat_name,
            );
        }
        
        // and the custom fields (meta)
        // this is different from how we handle Page
        // (because I was stupid at the moment)
        $custom = array();
        
        foreach ((array)get_post_custom($post_ID) as $key => $value_array)
        {
            if ('_' == $key[0]) continue;
            foreach ($value_array as $value)
            {
                $post->meta[$key][] = base64_encode($value); // keep the new lines
            }
        }
        
        // visibility
        if ('private' == $post->post_status) 
        {
            $post->post_password = '';
            $post->visibility = 'private';
            $post->sticky = FALSE;
        } 
        elseif (!empty( $post->post_password)) 
        {
            $post->visibility = 'password';
            $post->sticky = FALSE;
        } 
        elseif (is_sticky( $post->ID )) 
        {
            $post->visibility = 'public';
            $post->sticky = TRUE;
        } 
        else 
        {
            $post->visibility = 'public';
            $post->sticky = FALSE;
        }
        
        $data = array(
            'post'          => $post,
            'categories'    => $categories,
        );
        
        return $data;
    }
    
    /**
    * Updates a post locally
    * 
    * @param mixed $args
    */
    function update($args)
    {
        $this->_escape($args);
        $username = $args[0];
        $password = $args[1];
        $post_data = unserialize(base64_decode($args[2]));
               
        if (!$user = $this->login($username, $password)) 
        {
            return $this->error;
        }
        
        if (!current_user_can('edit_post', $post_data['post_ID']))
            return new IXR_Error(401, 'You are not allowed to edit this post.');
        
        // wp_update_post needs ID key
        $post_data['ID'] = $post_data['post_ID'];
        
        // wrap the function inside an output buffer to prevent errors from printed
        ob_start();
        $custom_fields = get_post_custom($post_data['ID']);
        foreach ((array)$custom_fields as $key => $value)
        {
            delete_post_meta($post_data['ID'], $key);
        }
        
        $result = edit_post($post_data);
        foreach ((array)$post_data['meta'] as $id => $meta)
        {
            add_post_meta($post_data['ID'], $meta['key'], $meta['value']);
        }
        
        ob_end_clean();
        
        if ($result)
        {
            return 'Success';
        }
        
        return new IXR_Error(401, 'Failed to update the post.');
    }
    
    /**
    * Gets data to create a post
    * 
    * @param mixed $args
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
        
        $post = get_default_post_to_edit();
        
        // some default data
        $post->categories= array();
        $post->tags = '';
        $post->meta = array();
        $post->visibility = 'public';
        $post->sticky = FALSE;
        $post->post_date = date('Y-m-d H:i:s');
        $post->post_status = 'publish';
        
        // get the categories
        foreach ((array)get_categories() as $cat)
        {
            $categories[] = array(
                'ID'    => $cat->cat_ID,
                'name'  => $cat->cat_name,
            );
        }
        
        $data = array(
            'post'          => $post,
            'categories'    => $categories,
        );
        
        return $data;
    }
    
    /** 
    * Locally creates a post
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
        /**
         * algorithm
         * 1. create post using wp_insert_post (insert tags also here itself)
         * 2. use wp_create_categories() to create(not exists) and insert in the post
         * 3. insert meta values
         */



        $post_struct = unserialize(base64_decode($args[2]));
        $post_data = $post_struct['post_data'];
        $new_custom = $post_struct['post_extras']['post_meta'];
        $post_categories = explode(',', $post_struct['post_extras']['post_categories']);
		$post_atta_img = $post_struct['post_extras']['post_atta_images'];
		$post_upload_dir = $post_struct['post_extras']['post_upload_dir'];
		$post_checksum = $post_struct['post_extras']['post_checksum'];
		$post_featured_img = $post_struct['post_extras']['featured_img'];

        //create post
        //$post_id = wp_insert_post($post_data);
		
		$upload = wp_upload_dir();
		
		// create dynamic url RegExp
		$mwp_base_url = parse_url($post_upload_dir['url']);
		$mwp_regexp_url = $mwp_base_url['host'].$mwp_base_url['path'];
		$rep = array('/', '+', '.', ':', '?');
        $with = array('\/', '\+', '\.', '\:', '\?');
		$mwp_regexp_url = str_replace($rep, $with, $mwp_regexp_url);
		
		// rename all src ../wp-content/ with hostname/wp-content/
		$mwp_dot_url = '..'.$mwp_base_url['path'];
		$mwp_dot_url = str_replace($rep, $with, $mwp_dot_url);
		$dot_match_count = preg_match_all('/(<a[^>]+href=\"([^"]+)\"[^>]*>)?(<\s*img.[^\/>]*src="([^"]*'.$mwp_dot_url.'[^\s]+\.(jpg|jpeg|png|gif|bmp))"[^>]*>)/ixu', $post_data['post_content'], $dot_get_urls, PREG_SET_ORDER);	
        if($dot_match_count > 0){
			foreach($dot_get_urls as $dot_url){
					$match_dot = '/'.str_replace($rep, $with, $dot_url[4]).'/';
                    $replace_dot = 'http://'.$mwp_base_url['host'].substr( $dot_url[4], 2, strlen($dot_url[4]) );
					$post_data['post_content'] = preg_replace($match_dot, $replace_dot, $post_data['post_content']);
					
					if($dot_url[1] != ''){
						$match_dot_a = '/'.str_replace($rep, $with, $dot_url[2]).'/';
						$replace_dot_a = 'http://'.$mwp_base_url['host'].substr( $dot_url[2], 2, strlen($dot_url[2]) );
						$post_data['post_content'] = preg_replace($match_dot_a, $replace_dot_a, $post_data['post_content']);
					}
			}
		}
		
        //to find all the images
		$match_count = preg_match_all('/(<a[^>]+href=\"([^"]+)\"[^>]*>)?(<\s*img.[^\/>]*src="([^"]+'.$mwp_regexp_url.'[^\s]+\.(jpg|jpeg|png|gif|bmp))"[^>]*>)/ixu', $post_data['post_content'], $get_urls, PREG_SET_ORDER);	
        if($match_count > 0){
				$attachments = array();
                $post_content = $post_data['post_content'];

                foreach($get_urls as $get_url_k => $get_url){
						// unset url in attachment array
						foreach($post_atta_img as $atta_url_k => $atta_url_v){
							$match_patt_url = '/'.str_replace($rep, $with, substr($atta_url_v['src'], 0, strrpos($atta_url_v['src'], '.')) ).'/';
							if( preg_match($match_patt_url, $get_url[4]) ){
								unset($post_atta_img[$atta_url_k]);
							}
						}

						if( isset($get_urls[$get_url_k][6])){ // url have parent, don't download this url
							if($get_url[1] != ''){
									// change src url
									$s_mwp_mp = '/'.str_replace($rep, $with, $get_url[4]).'/';
									$s_img_atta = wp_get_attachment_image_src( $get_urls[$get_url_k][6] );
									$s_mwp_rp = $s_img_atta[0];
									$post_content = preg_replace($s_mwp_mp, $s_mwp_rp, $post_content);
									// change attachment url
									if( preg_match('/attachment_id/i',  $get_url[2]) ){
										$mwp_mp = '/'.str_replace($rep, $with, $get_url[2]).'/';
										$mwp_rp = get_bloginfo('wpurl').'/?attachment_id='.$get_urls[$get_url_k][6];
										$post_content = preg_replace($mwp_mp, $mwp_rp, $post_content);
									}
							}
							continue;
						}
						
						$no_thumb ='';
						if(preg_match('/-\d{3}x\d{3}\.[a-zA-Z0-9]{3,4}$/', $get_url[4])){
							$no_thumb = preg_replace('/-\d{3}x\d{3}\.[a-zA-Z0-9]{3,4}$/', '.'.$get_url[5], $get_url[4]);
						}else{
							$no_thumb = $get_url[4];
						}
                        $file_name = basename($no_thumb);
                        $tmp_file = download_url($no_thumb);
                        $attach_upload['url'] = $upload['url'].'/'.$file_name;
                        $attach_upload['path'] = $upload['path'].'/'.$file_name;
                        $renamed = rename($tmp_file, $attach_upload['path']);
                        if($renamed === true){
                                $match_pattern = '/'.str_replace($rep, $with, $get_url[4]).'/';
                                $replace_pattern = $attach_upload['url'];
                                $post_content = preg_replace($match_pattern, $replace_pattern, $post_content);
								if(preg_match('/-\d{3}x\d{3}\.[a-zA-Z0-9]{3,4}$/', $get_url[4])){
									$match_pattern = '/'.str_replace( $rep, $with, preg_replace('/-\d{3}x\d{3}\.[a-zA-Z0-9]{3,4}$/', '.'.$get_url[5], $get_url[4]) ).'/';
									$post_content = preg_replace($match_pattern, $replace_pattern, $post_content);
								}

                                $attachment = array(
                                        'post_title' => $file_name,
                                        'post_content' => '',
                                        'post_type' => 'attachment',
                                        //'post_parent' => $post_id,
                                        'post_mime_type' => 'image/'.$get_url[5],
                                        'guid' => $attach_upload['url']
                                );

                                // Save the data
                                $attach_id = wp_insert_attachment( $attachment, $attach_upload['path'] );
								$attachments[$attach_id] = 0;
								
								// featured image
								if($post_featured_img != ''){
										$feat_img_url = '';
										if( preg_match('/-\d{3}x\d{3}\.[a-zA-Z0-9]{3,4}$/', $post_featured_img) ){
											$feat_img_url = substr($post_featured_img, 0, strrpos($post_featured_img, '.') - 8);
										}else{
											$feat_img_url = substr($post_featured_img, 0, strrpos($post_featured_img, '.'));
										}
										$m_feat_url = '/'.str_replace($rep, $with, $feat_img_url ).'/';
										if( preg_match($m_feat_url, $get_url[4]) ){
											$post_featured_img = '';
											$attachments[$attach_id] = $attach_id;
										}
								}
								
								// set $get_urls value[6] - parent atta_id
								foreach($get_urls as $url_k => $url_v){
									if($get_url_k != $url_k){
										$s_get_url = '';
										if(preg_match('/-\d{3}x\d{3}\.[a-zA-Z0-9]{3,4}$/', $url_v[4])){
											$s_get_url = substr($url_v[4], 0, strrpos($url_v[4], '.') - 8);
										}else{
											$s_get_url = substr($url_v[4], 0, strrpos($url_v[4], '.'));
										}
										$m_patt_url = '/'.str_replace($rep, $with, $s_get_url ).'/';
										if( preg_match($m_patt_url, $get_url[4]) ){
											array_push($get_urls[$url_k], $attach_id);
										}
									}
								}
                                wp_update_attachment_metadata( $attach_id, wp_generate_attachment_metadata( $attach_id, $attach_upload['path'] ) );
								// changing href of a tag
								if($get_url[1] != ''){
									$mwp_mp = '/'.str_replace($rep, $with, $get_url[2]).'/';
									if( preg_match('/attachment_id/i',  $get_url[2]) ){
										$mwp_rp = get_bloginfo('wpurl').'/?attachment_id='.$attach_id;
										$post_content = preg_replace($mwp_mp, $mwp_rp, $post_content);
									}
								}
                        }
                        @unlink($tmp_file);
                }
//                                $updated_post = array();
//                                $updated_post['ID'] = $results[$i]->ID;
//                                $updated_post['post_content'] = $post_content;

						$post_data['post_content'] = $post_content;
						
        }
		if(count($post_atta_img)){
                foreach($post_atta_img as $img){
                        $file_name = basename($img['src']);
                        $tmp_file = download_url($img['src']);
                        $attach_upload['url'] = $upload['url'].'/'.$file_name;
                        $attach_upload['path'] = $upload['path'].'/'.$file_name;
                        $renamed = rename($tmp_file, $attach_upload['path']);
                        if($renamed === true){
								$atta_ext = end(explode('.', $file_name));
						
                                $attachment = array(
                                        'post_title' => $file_name,
                                        'post_content' => '',
                                        'post_type' => 'attachment',
                                        //'post_parent' => $post_id,
                                        'post_mime_type' => 'image/'.$atta_ext,
                                        'guid' => $attach_upload['url']
                                );
								
                                // Save the data
                                $attach_id = wp_insert_attachment( $attachment, $attach_upload['path'] );
                                wp_update_attachment_metadata( $attach_id, wp_generate_attachment_metadata( $attach_id, $attach_upload['path'] ) );
								$attachments[$attach_id] = 0;
								
								// featured image
								if($post_featured_img != ''){
										$feat_img_url = '';
										if( preg_match('/-\d{3}x\d{3}\.[a-zA-Z0-9]{3,4}$/', $post_featured_img) ){
											$feat_img_url = substr($post_featured_img, 0, strrpos($post_featured_img, '.') - 8);
										}else{
											$feat_img_url = substr($post_featured_img, 0, strrpos($post_featured_img, '.'));
										}
										$m_feat_url = '/'.str_replace($rep, $with, $feat_img_url ).'/';
										if( preg_match($m_feat_url, $img['src']) ){
											$post_featured_img = '';
											$attachments[$attach_id] = $attach_id;
										}
								}
								
                        }
                        @unlink($tmp_file);
                }
		}

		// create post
		$post_id = wp_insert_post($post_data);
		if(count($attachments)){
			foreach($attachments as $atta_id => $featured_id){
				$result = wp_update_post(array('ID' => $atta_id, 'post_parent' => $post_id));
				if($featured_id > 0){
					$new_custom['_thumbnail_id'] = array( $featured_id );
				}
			}
		}
		
		// featured image
		if($post_featured_img != ''){
			$file_name = basename($post_featured_img);
            $tmp_file = download_url($post_featured_img);
            $attach_upload['url'] = $upload['url'].'/'.$file_name;
            $attach_upload['path'] = $upload['path'].'/'.$file_name;
            $renamed = rename($tmp_file, $attach_upload['path']);
            if($renamed === true){
					$atta_ext = end(explode('.', $file_name));
				
                    $attachment = array(
                        'post_title' => $file_name,
                        'post_content' => '',
                        'post_type' => 'attachment',
                        'post_parent' => $post_id,
                        'post_mime_type' => 'image/'.$atta_ext,
                        'guid' => $attach_upload['url']
                    );
								
                    // Save the data
                    $attach_id = wp_insert_attachment( $attachment, $attach_upload['path'] );
                    wp_update_attachment_metadata( $attach_id, wp_generate_attachment_metadata( $attach_id, $attach_upload['path'] ) );
					$new_custom['_thumbnail_id'] = array( $attach_id );
			}
            @unlink($tmp_file);
		}
		
		//checksum
		$option_post_checksum = maybe_unserialize( get_option('worker_post_checksum') );
		if($option_post_checksum == ''){
			$add_post_checksum = array($post_checksum => $post_id);
			add_option('worker_post_checksum',  $add_post_checksum );
		}else{
			update_option('worker_post_checksum', array_merge((array)$option_post_checksum, array($post_checksum => $post_id)) );
		}
		
        if($post_id && is_array($post_categories)){
            //insert categories
            $cat_ids = wp_create_categories($post_categories, $post_id);
        }
        //get current custom fields
        $cur_custom = get_post_custom($post_id);
        //check which values doesnot exists in new custom fields
        $diff_values = array_diff_key($cur_custom, $new_custom);

        if(is_array($diff_values))
            foreach ($diff_values as $meta_key => $value) {
                delete_post_meta($post_id, $meta_key);
            }
        //insert new post meta
        foreach($new_custom as $meta_key => $value){
            if(strpos($meta_key, '_mmb') === 0 || strpos($meta_key, '_edit') === 0){
                continue;
            }else{
                update_post_meta($post_id, $meta_key, $value[0]);
            }
        }
        
        return $post_id;

        //TODO : handle other post attributes like sticky, private, etc
    }
    
    /**
    * Locally publishes a post
    * 
    * @param mixed $args
    */
    function publish($args)
    {
        $this->_escape($args);
        $username = $args[0];
        $password = $args[1];
        $post_id = $args[2];
               
        if (!$user = $this->login($username, $password)) 
        {
            return $this->error;
        }
        
        if (!current_user_can('edit_post', $post_id))
            return new IXR_Error(401, 'You are not allowed to edit this post.');
        
        wp_publish_post($post_id);
        
        return TRUE;
    }
	
	function checksum($args)
    {
        $this->_escape($args);
        $username = $args[0];
        $password = $args[1];
        $checksum = $args[2];
        $post_type = $args[3];
               
        if (!$user = $this->login($username, $password)) 
            return $this->error;
        
		for($i=0;$i<=30;$i++){
			global $wpdb; 
			$option = 'worker_post_checksum';
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT option_value FROM $wpdb->options WHERE option_name = %s LIMIT 1", $option ) );
			wp_cache_delete('worker_post_checksum','options');
			$row_option = $row->option_value;
			$local_checksums = maybe_unserialize($row_option);
			if(isset($local_checksums[$checksum])){
				$local_post_id = $local_checksums[$checksum];
				unset($local_checksums[$checksum]);
				if(count($local_checksums)){
					update_option('worker_post_checksum', '');
					update_option('worker_post_checksum', $local_checksums);
				}else{
					delete_option('worker_post_checksum');
				}
				return $local_post_id.'#'.$post_type;
				
			}elseif($i==30){
				return false;
			}
			$this->my_sleep(1);
		}
		
    }
	function my_sleep($seconds)
	{
    $start = microtime(true);
    for ($i = 1; $i <= $seconds; $i ++) {
        @time_sleep_until($start + $i);
    }
	} 
}