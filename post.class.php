<?php
/*************************************************************
 * 
 * post.class.php
 * 
 * Create remote post
 * 
 * 
 * Copyright (c) 2011 Prelovac Media
 * www.prelovac.com
 **************************************************************/

class MMB_Post extends MMB_Core
{
    function __construct()
    {
        parent::__construct();
    }
    
    function create($args)
    {
        /**
         * algorithm
         * 1. create post using wp_insert_post (insert tags also here itself)
         * 2. use wp_create_categories() to create(not exists) and insert in the post
         * 3. insert meta values
         */
        
        include_once ABSPATH . 'wp-admin/includes/taxonomy.php';
        include_once ABSPATH . 'wp-admin/includes/image.php';
        
        $post_struct = $args['post_data'];
        
        $post_data         = $post_struct['post_data'];
        $new_custom        = $post_struct['post_extras']['post_meta'];
        $post_categories   = explode(',', $post_struct['post_extras']['post_categories']);
        $post_atta_img     = $post_struct['post_extras']['post_atta_images'];
        $post_upload_dir   = $post_struct['post_extras']['post_upload_dir'];
        $post_checksum     = $post_struct['post_extras']['post_checksum'];
        $post_featured_img = $post_struct['post_extras']['featured_img'];
        
        $upload = wp_upload_dir();
        
        // create dynamic url RegExp
        $mwp_base_url   = parse_url($post_upload_dir['url']);
        $mwp_regexp_url = $mwp_base_url['host'] . $mwp_base_url['path'];
        $rep            = array(
            '/',
            '+',
            '.',
            ':',
            '?'
        );
        $with           = array(
            '\/',
            '\+',
            '\.',
            '\:',
            '\?'
        );
        $mwp_regexp_url = str_replace($rep, $with, $mwp_regexp_url);
        
        // rename all src ../wp-content/ with hostname/wp-content/
        $mmb_dot_url     = '..' . $mmb_base_url['path'];
        $mmb_dot_url     = str_replace($rep, $with, $mmb_dot_url);
        $dot_match_count = preg_match_all('/(<a[^>]+href=\"([^"]+)\"[^>]*>)?(<\s*img.[^\/>]*src="([^"]*' . $mmb_dot_url . '[^\s]+\.(jpg|jpeg|png|gif|bmp))"[^>]*>)/ixu', $post_data['post_content'], $dot_get_urls, PREG_SET_ORDER);
        if ($dot_match_count > 0) {
            foreach ($dot_get_urls as $dot_url) {
                $match_dot                 = '/' . str_replace($rep, $with, $dot_url[4]) . '/';
                $replace_dot               = 'http://' . $mmb_base_url['host'] . substr($dot_url[4], 2, strlen($dot_url[4]));
                $post_data['post_content'] = preg_replace($match_dot, $replace_dot, $post_data['post_content']);
                
                if ($dot_url[1] != '') {
                    $match_dot_a               = '/' . str_replace($rep, $with, $dot_url[2]) . '/';
                    $replace_dot_a             = 'http://' . $mmb_base_url['host'] . substr($dot_url[2], 2, strlen($dot_url[2]));
                    $post_data['post_content'] = preg_replace($match_dot_a, $replace_dot_a, $post_data['post_content']);
                }
            }
        }
        
        
        
        //to find all the images
        $match_count = preg_match_all('/(<a[^>]+href=\"([^"]+)\"[^>]*>)?(<\s*img.[^\/>]*src="([^"]+' . $mmb_regexp_url . '[^\s]+\.(jpg|jpeg|png|gif|bmp))"[^>]*>)/ixu', $post_data['post_content'], $get_urls, PREG_SET_ORDER);
        if ($match_count > 0) {
            $attachments  = array();
            $post_content = $post_data['post_content'];
            
            foreach ($get_urls as $get_url_k => $get_url) {
                // unset url in attachment array
                foreach ($post_atta_img as $atta_url_k => $atta_url_v) {
                    $match_patt_url = '/' . str_replace($rep, $with, substr($atta_url_v['src'], 0, strrpos($atta_url_v['src'], '.'))) . '/';
                    if (preg_match($match_patt_url, $get_url[4])) {
                        unset($post_atta_img[$atta_url_k]);
                    }
                }
                
                if (isset($get_urls[$get_url_k][6])) { // url have parent, don't download this url
                    if ($get_url[1] != '') {
                        // change src url
                        $s_mmb_mp = '/' . str_replace($rep, $with, $get_url[4]) . '/';
                        
                        $s_img_atta   = wp_get_attachment_image_src($get_urls[$get_url_k][6]);
                        $s_mmb_rp     = $s_img_atta[0];
                        $post_content = preg_replace($s_mmb_mp, $s_mmb_rp, $post_content);
                        // change attachment url
                        if (preg_match('/attachment_id/i', $get_url[2])) {
                            $mmb_mp       = '/' . str_replace($rep, $with, $get_url[2]) . '/';
                            $mmb_rp       = get_bloginfo('wpurl') . '/?attachment_id=' . $get_urls[$get_url_k][6];
                            $post_content = preg_replace($mmb_mp, $mmb_rp, $post_content);
                        }
                    }
                    continue;
                }
                
                $no_thumb = '';
                if (preg_match('/-\d{3}x\d{3}\.[a-zA-Z0-9]{3,4}$/', $get_url[4])) {
                    $no_thumb = preg_replace('/-\d{3}x\d{3}\.[a-zA-Z0-9]{3,4}$/', '.' . $get_url[5], $get_url[4]);
                } else {
                    $no_thumb = $get_url[4];
                }
                $file_name = basename($no_thumb);
                
                //$tmp_file = $upload['path'].'/tempfile.tmp';
                $tmp_file = $this->mmb_download_url($no_thumb, $upload['path'] . '/tempfile' . md5(time()) . '.tmp');
                //$tmp_file = download_url($no_thumb);
                
                $attach_upload['url']  = $upload['url'] . '/' . $file_name;
                $attach_upload['path'] = $upload['path'] . '/' . $file_name;
                $renamed               = @rename($tmp_file, $attach_upload['path']);
                if ($renamed === true) {
                    $match_pattern   = '/' . str_replace($rep, $with, $get_url[4]) . '/';
                    $replace_pattern = $attach_upload['url'];
                    $post_content    = preg_replace($match_pattern, $replace_pattern, $post_content);
                    if (preg_match('/-\d{3}x\d{3}\.[a-zA-Z0-9]{3,4}$/', $get_url[4])) {
                        $match_pattern = '/' . str_replace($rep, $with, preg_replace('/-\d{3}x\d{3}\.[a-zA-Z0-9]{3,4}$/', '.' . $get_url[5], $get_url[4])) . '/';
                        $post_content  = preg_replace($match_pattern, $replace_pattern, $post_content);
                    }
                    
                    $attachment = array(
                        'post_title' => $file_name,
                        'post_content' => '',
                        'post_type' => 'attachment',
                        //'post_parent' => $post_id,
                        'post_mime_type' => 'image/' . $get_url[5],
                        'guid' => $attach_upload['url']
                    );
                    
                    // Save the data
                    
                    $attach_id = wp_insert_attachment($attachment, $attach_upload['path']);
                    
                    $attachments[$attach_id] = 0;
                    
                    // featured image
                    if ($post_featured_img != '') {
                        $feat_img_url = '';
                        if (preg_match('/-\d{3}x\d{3}\.[a-zA-Z0-9]{3,4}$/', $post_featured_img)) {
                            $feat_img_url = substr($post_featured_img, 0, strrpos($post_featured_img, '.') - 8);
                        } else {
                            $feat_img_url = substr($post_featured_img, 0, strrpos($post_featured_img, '.'));
                        }
                        $m_feat_url = '/' . str_replace($rep, $with, $feat_img_url) . '/';
                        if (preg_match($m_feat_url, $get_url[4])) {
                            $post_featured_img       = '';
                            $attachments[$attach_id] = $attach_id;
                        }
                    }
                    
                    // set $get_urls value[6] - parent atta_id
                    foreach ($get_urls as $url_k => $url_v) {
                        if ($get_url_k != $url_k) {
                            $s_get_url = '';
                            if (preg_match('/-\d{3}x\d{3}\.[a-zA-Z0-9]{3,4}$/', $url_v[4])) {
                                $s_get_url = substr($url_v[4], 0, strrpos($url_v[4], '.') - 8);
                            } else {
                                $s_get_url = substr($url_v[4], 0, strrpos($url_v[4], '.'));
                            }
                            $m_patt_url = '/' . str_replace($rep, $with, $s_get_url) . '/';
                            if (preg_match($m_patt_url, $get_url[4])) {
                                array_push($get_urls[$url_k], $attach_id);
                            }
                        }
                    }
                    
                    
                    $some_data = wp_generate_attachment_metadata($attach_id, $attach_upload['path']);
                    wp_update_attachment_metadata($attach_id, $some_data);
                    
                    
                    // changing href of a tag
                    if ($get_url[1] != '') {
                        $mmb_mp = '/' . str_replace($rep, $with, $get_url[2]) . '/';
                        if (preg_match('/attachment_id/i', $get_url[2])) {
                            $mmb_rp       = get_bloginfo('wpurl') . '/?attachment_id=' . $attach_id;
                            $post_content = preg_replace($mmb_mp, $mmb_rp, $post_content);
                        }
                    }
                }
                @unlink($tmp_file);
            }
            
            
            $post_data['post_content'] = $post_content;
            
        }
        if (count($post_atta_img)) {
            foreach ($post_atta_img as $img) {
                $file_name = basename($img['src']);
                
                
                $tmp_file = $this->mmb_download_url($img['src'], $upload['path'] . '/tempfile.tmp');
                
                //$tmp_file = download_url($img['src']);
                
                $attach_upload['url']  = $upload['url'] . '/' . $file_name;
                $attach_upload['path'] = $upload['path'] . '/' . $file_name;
                $renamed               = @rename($tmp_file, $attach_upload['path']);
                if ($renamed === true) {
                    $atta_ext = end(explode('.', $file_name));
                    
                    $attachment = array(
                        'post_title' => $file_name,
                        'post_content' => '',
                        'post_type' => 'attachment',
                        //'post_parent' => $post_id,
                        'post_mime_type' => 'image/' . $atta_ext,
                        'guid' => $attach_upload['url']
                    );
                    
                    // Save the data
                    $attach_id = wp_insert_attachment($attachment, $attach_upload['path']);
                    wp_update_attachment_metadata($attach_id, wp_generate_attachment_metadata($attach_id, $attach_upload['path']));
                    $attachments[$attach_id] = 0;
                    
                    // featured image
                    if ($post_featured_img != '') {
                        $feat_img_url = '';
                        if (preg_match('/-\d{3}x\d{3}\.[a-zA-Z0-9]{3,4}$/', $post_featured_img)) {
                            $feat_img_url = substr($post_featured_img, 0, strrpos($post_featured_img, '.') - 8);
                        } else {
                            $feat_img_url = substr($post_featured_img, 0, strrpos($post_featured_img, '.'));
                        }
                        $m_feat_url = '/' . str_replace($rep, $with, $feat_img_url) . '/';
                        if (preg_match($m_feat_url, $img['src'])) {
                            $post_featured_img       = '';
                            $attachments[$attach_id] = $attach_id;
                        }
                    }
                    
                }
                @unlink($tmp_file);
            }
        }
        
        
        // create post
        $post_id = wp_insert_post($post_data);
        
        if (count($attachments)) {
            foreach ($attachments as $atta_id => $featured_id) {
                $result = wp_update_post(array(
                    'ID' => $atta_id,
                    'post_parent' => $post_id
                ));
                if ($featured_id > 0) {
                    $new_custom['_thumbnail_id'] = array(
                        $featured_id
                    );
                }
            }
        }
        
        // featured image
        if ($post_featured_img != '') {
            $file_name             = basename($post_featured_img);
            //$tmp_file = download_url($post_featured_img);
            $tmp_file              = $this->mmb_download_url($no_thumb, $upload['path'] . '/tempfile_feat.tmp');
            $attach_upload['url']  = $upload['url'] . '/' . $file_name;
            $attach_upload['path'] = $upload['path'] . '/' . $file_name;
            $renamed               = @rename($tmp_file, $attach_upload['path']);
            if ($renamed === true) {
                $atta_ext = end(explode('.', $file_name));
                
                $attachment = array(
                    'post_title' => $file_name,
                    'post_content' => '',
                    'post_type' => 'attachment',
                    'post_parent' => $post_id,
                    'post_mime_type' => 'image/' . $atta_ext,
                    'guid' => $attach_upload['url']
                );
                
                // Save the data
                $attach_id = wp_insert_attachment($attachment, $attach_upload['path']);
                wp_update_attachment_metadata($attach_id, wp_generate_attachment_metadata($attach_id, $attach_upload['path']));
                $new_custom['_thumbnail_id'] = array(
                    $attach_id
                );
            }
            @unlink($tmp_file);
        }
        
        if ($post_id && is_array($post_categories)) {
            //insert categories
            
            $cat_ids = wp_create_categories($post_categories, $post_id);
        }
        
        
        //get current custom fields
        $cur_custom  = get_post_custom($post_id);
        //check which values doesnot exists in new custom fields
        $diff_values = array_diff_key($cur_custom, $new_custom);
        
        if (is_array($diff_values))
            foreach ($diff_values as $meta_key => $value) {
                delete_post_meta($post_id, $meta_key);
            }
        //insert new post meta
        foreach ($new_custom as $meta_key => $value) {
            if (strpos($meta_key, '_mmb') === 0 || strpos($meta_key, '_edit') === 0) {
                continue;
            } else {
                update_post_meta($post_id, $meta_key, $value[0]);
            }
        }
          return $post_id;  
    }
    
    /**
     * Aleternate function for WordPress download_url()
     */
    function mmb_download_url($url, $file_name)
    {
        $destination = fopen($file_name, 'wb');
        $source      = @fopen($url, "r");
        while ($a = fread($source, 1024)) {
            $ret = fwrite($destination, $a);
        }
        fclose($source);
        fclose($destination);
        return $file_name;
    }
}
?>