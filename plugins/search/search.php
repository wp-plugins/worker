<?php
/*************************************************************
 * 
 * stats.class.php
 * 
 * Various searches on worker
 * 
 * 
 * Copyright (c) 2011 Prelovac Media
 * www.prelovac.com
 **************************************************************/

	mmb_add_action('search_posts_by_term', 'search_posts_by_term');
    
    function search_posts_by_term($params = false){
	
    	global $wpdb, $current_user;
	

		$num_posts = 10;
		$num_content_char = 30;
		
    	$term_orig = trim($params['search_term']);
    	$term_base= addslashes(trim($params['search_term']));
    	
    	$query = "SELECT *
    			  FROM $wpdb->posts 
    			  WHERE $wpdb->posts.post_status  = 'publish'
    			  AND ($wpdb->posts.post_title LIKE '%$term_base%'
    			  		OR $wpdb->posts.post_content LIKE '%$term_base%')
    			  ORDER BY $wpdb->posts.post_modified DESC
    			  LIMIT 0, $num_posts
    			 ";
    	
    	$posts_array = $wpdb->get_results($query);
    	
    	$ret_posts = array();
    		
    	foreach($posts_array as $post){
			//highlight searched term
			
    		if (substr_count(strtolower($post->post_title), strtolower($term_orig))){
    			$str_position_start = strpos(strtolower($post->post_title), strtolower($term_orig));
    			
    			$post->post_title = substr($post->post_title, 0, $str_position_start).'<b>'.
    										substr($post->post_title, $str_position_start, strlen($term_orig)).'</b>'.
    										substr($post->post_title, $str_position_start + strlen($term_orig)); 
    			
    		}
			$post->post_content = html_entity_decode($post->post_content);
			
			$post->post_content = strip_tags($post->post_content);
			
			
			
    	    if (substr_count(strtolower($post->post_content), strtolower($term_orig))){
    			$str_position_start = strpos(strtolower($post->post_content), strtolower($term_orig));
    			
    			$start = $str_position_start > $num_content_char ? $str_position_start - $num_content_char: 0;
    			$first_len = $str_position_start > $num_content_char? $num_content_char : $str_position_start;

    			$start_substring = $start>0 ? '...' : '';
    			$post->post_content =   $start_substring . substr($post->post_content, $start, $first_len).'<b>'.
    										substr($post->post_content, $str_position_start, strlen($term_orig)).'</b>'.
    										substr($post->post_content, $str_position_start + strlen($term_orig), $num_content_char) . '...'; 
    			
    		    			
    		}else{
    			$post->post_content = substr($post->post_content,0, 50). '...';
    		}
    			
    		$ret_posts[] = array(
    							'ID' => $post->ID
    							,'post_permalink' => get_permalink($post->ID)
                        		,'post_date' => $post->post_date
                        		,'post_title' => $post->post_title
                        		,'post_content' => $post->post_content
                        		,'post_modified' => $post->post_modified
                        		,'comment_count' => $post->comment_count


    						);
    	}
		mmb_response($ret_posts, true);
    	
    }

?>