<?php
/*************************************************************
 * 
 * stats.class.php
 * 
 * Get Site Stats
 * 
 * 
 * Copyright (c) 2011 Prelovac Media
 * www.prelovac.com
 **************************************************************/


class MMB_Stats extends MMB_Core
{
    function __construct()
    {
        parent::__construct();
    }
    
    /*************************************************************
     * FACADE functions
     * (functions to be called after a remote call from Master)
     **************************************************************/
    
    function get($params)
    {
        global $wp_version, $mmb_plugin_dir;
        $stats = array();
        
        //define constants
        $num_pending_comments  = 3;
        $num_approved_comments = 3;
        $num_spam_comments     = 0;
        $num_draft_comments    = 0;
        $num_trash_comments    = 0;
        
        require_once(ABSPATH . '/wp-admin/includes/update.php');
        
        $stats['worker_version'] = MMB_WORKER_VERSION;
        $stats['wordpress_version'] = $wp_version;
		
        $updates = $this->mmb_get_transient('update_core');
        
        if ($updates->updates[0]->response == 'development' || version_compare($wp_version, $updates->updates[0]->current, '<')) {
            $updates->updates[0]->current_version = $wp_version;
            $stats['core_updates']                 = $updates->updates[0];
        } else
            $stats['core_updates'] = false;
        
        $mmb_user_hits = get_option('user_hit_count');
        if (is_array($mmb_user_hits)) {
            end($mmb_user_hits);
            $last_key_date = key($mmb_user_hits);
            $current_date  = date('Y-m-d');
            if ($last_key_date != $curent_date)
                $this->set_hit_count(true);
        }
        $stats['hit_counter'] = get_option('user_hit_count');
        
        
        $stats['upgradable_themes']  = $this->get_theme_instance()->get_upgradable_themes();
        $stats['upgradable_plugins'] = $this->get_plugin_instance()->get_upgradable_plugins();
        
        $pending_comments = get_comments('status=hold&number=' . $num_pending_comments);
        foreach ($pending_comments as &$comment) {
            $commented_post      = get_post($comment->comment_post_ID);
            $comment->post_title = $commented_post->post_title;
        }
		$stats['comments']['pending']  = $pending_comments;
		 
		 
        $approved_comments = get_comments('status=approve&number=' . $num_approved_comments);
        foreach ($approved_comments as &$comment) {
            $commented_post      = get_post($comment->comment_post_ID);
            $comment->post_title = $commented_post->post_title;
        }
        $stats['comments']['approved'] = $approved_comments;
        
        
        $all_posts              = get_posts('post_status=publish&numberposts=3&orderby=modified&order=desc');
        $stats['publish_count'] = count($all_posts);
        $recent_posts           = array();
        
        foreach ($all_posts as $id => $recent) {
            $recent->post_permalink = get_permalink($recent->ID);
            unset($recent->post_content);
            unset($recent->post_author);
            unset($recent->post_category);
            unset($recent->post_date_gmt);
            unset($recent->post_excerpt);
            unset($recent->post_status);
            unset($recent->comment_status);
            unset($recent->ping_status);
            unset($recent->post_password);
            unset($recent->post_name);
            unset($recent->to_ping);
            unset($recent->pinged);
            unset($recent->post_modified_gmt);
            unset($recent->post_content_filtered);
            unset($recent->post_parent);
            unset($recent->guid);
            unset($recent->menu_order);
            unset($recent->post_type);
            unset($recent->post_mime_type);
            unset($recent->filter);
            unset($recent->featured);
            $recent_posts[] = $recent;
        }
        $stats['posts'] = $recent_posts;
        
        $drafts = get_posts('post_status=draft&numberposts=3');
        foreach ($drafts as $draft) {
            $props = get_object_vars($draft);
            foreach ($props as $name => $value) {
                if ($name != 'post_title' && $name != 'ID' && $name != 'post_modified') {
                    unset($draft->$name);
                } else {
                    $draft->post_title     = get_the_title($draft->ID);
                    $draft->post_permalink = get_permalink($draft->ID);
                }
            }
        }
        $stats['draft_count'] = count($drafts);
        $stats['drafts']      = $drafts;
        
        
        if ((!defined('FTP_HOST') || !defined('FTP_USER') || !defined('FTP_PASS')) && !is_writable(WP_CONTENT_DIR)) {
            $stats['writable'] = false;
        } else
            $stats['writable'] = true;

		$stats = apply_filters('mmb_stats_filter', $stats);
		
		return $stats;
    }
    
    function get_initial_stats()
    {
        global $mmb_plugin_dir;
        
        $stats = array();
        
        $stats['email']          = get_option('admin_email');
        $stats['no_openssl']     = $this->get_random_signature();
        $stats['content_path']   = WP_CONTENT_DIR;
        $stats['worker_path']    = $mmb_plugin_dir;
        $stats['worker_version'] = MMB_WORKER_VERSION;
        $stats['site_title']     = get_bloginfo('name');
        $stats['site_tagline']   = get_bloginfo('description');
        $stats['site_url']   = get_bloginfo('home');
        
        
        if ((!defined('FTP_HOST') || !defined('FTP_USER') || !defined('FTP_PASS')) && !is_writable(WP_CONTENT_DIR)) {
            $stats['writable'] = false;
        } else
            $stats['writable'] = true;
        
        return $stats;
    }
    
    
    function set_hit_count($fix_count = false)
    {
        if ($fix_count || (!is_admin() && !MMB_Stats::detect_bots())) {
            $date           = date('Y-m-d');
            $user_hit_count = get_option('user_hit_count');
            if (!$user_hit_count) {
                $user_hit_count[$date] = 1;
                update_option('user_hit_count', $user_hit_count);
            } else {
                $dated_keys      = array_keys($user_hit_count);
                $last_visit_date = $dated_keys[count($dated_keys) - 1];
                
                $days = intval((strtotime($date) - strtotime($last_visit_date)) / 60 / 60 / 24);
                
                if ($days > 1) {
                    $date_to_add = date('Y-m-d', strtotime($last_visit_date));
                    
                    for ($i = 1; $i < $days; $i++) {
                        if (count($user_hit_count) > 14) {
                            $shifted = @array_shift($user_hit_count);
                        }
                        
                        $next_key = strtotime('+1 day', strtotime($date_to_add));
                        if ($next_key == $date) {
                            break;
                        } else {
                            $user_hit_count[$next_key] = 0;
                        }
                    }
                    
                }
                
                if (!isset($user_hit_count[$date])) {
                    $user_hit_count[$date] = 0;
                }
                if (!$fix_count)
                    $user_hit_count[$date] += 1;
                
                if (count($user_hit_count) > 14) {
                    $shifted = @array_shift($user_hit_count);
                }
                
                update_option('user_hit_count', $user_hit_count);
                
            }
        }
    }
    
    function get_hit_count()
    {
        // Check if there are no hits on last key date
        $mmb_user_hits = get_option('user_hit_count');
        if (is_array($mmb_user_hits)) {
            end($mmb_user_hits);
            $last_key_date = key($mmb_user_hits);
            $current_date  = date('Y-m-d');
            if ($last_key_date != $curent_date)
                $this->set_hit_count(true);
        }
        
        return get_option('user_hit_count');
    }
    
    function detect_bots()
    {
        $agent = $_SERVER['HTTP_USER_AGENT'];
        
        if ($agent == '')
            return false;
        
        $bot_list = array(
            "Teoma",
            "alexa",
            "froogle",
            "Gigabot",
            "inktomi",
            "looksmart",
            "URL_Spider_SQL",
            "Firefly",
            "NationalDirectory",
            "Ask Jeeves",
            "TECNOSEEK",
            "InfoSeek",
            "WebFindBot",
            "girafabot",
            "crawler",
            "www.galaxy.com",
            "Googlebot",
            "Scooter",
            "Slurp",
            "msnbot",
            "appie",
            "FAST",
            "WebBug",
            "Spade",
            "ZyBorg",
            "rabaz",
            "Baiduspider",
            "Feedfetcher-Google",
            "TechnoratiSnoop",
            "Rankivabot",
            "Mediapartners-Google",
            "Sogou web spider",
            "WebAlta Crawler",
            "aolserver"
        );
        
        $thebot = '';
        foreach ($bot_list as $bot) {
            if (ereg($bot, $agent)) {
                $thebot = $bot;
                break;
            }
        }
        
        if ($thebot != '') {
            return $thebot;
        } else
            return false;
    }
    
}
?>