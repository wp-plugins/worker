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
		$num = extract($params);
		
        if ($refresh == 'transient') {
            include_once(ABSPATH . 'wp-includes/update.php');
			@wp_update_plugins();
			@wp_update_themes();
			@wp_version_check();
		}
		
        global $wpdb, $mmb_wp_version, $mmb_plugin_dir, $wp_version, $wp_local_package;
        $stats = array();
        
        //define constants
        $num_pending_comments  = 10;
        $num_approved_comments = 3;
        $num_spam_comments     = 0;
        $num_draft_comments    = 0;
        $num_trash_comments    = 0;

        include_once(ABSPATH . '/wp-admin/includes/update.php');
        
        $stats['worker_version']    = MMB_WORKER_VERSION;
        $stats['wordpress_version'] = $wp_version;
		$stats['wordpress_locale_pckg'] = $wp_local_package;
        $stats['wp_multisite'] =  $this->mmb_multisite;
        $stats['php_version'] = phpversion();
        $stats['mysql_version'] = $wpdb->db_version();
        
        if (function_exists('get_core_updates')) {
			$updates = get_core_updates();
            if (!empty($updates)) {
				$current_transient = $updates[0];
				if ($current_transient->response == "development" || version_compare($wp_version, $current_transient->current, '<')) {
					$current_transient->current_version = $wp_version;
					$stats['core_updates'] = $current_transient;
                } else
                    $stats['core_updates'] = false;
            } else
                $stats['core_updates'] = false;
		}
			
        $mmb_user_hits = get_option('user_hit_count');
        if (is_array($mmb_user_hits)) {
            end($mmb_user_hits);
            $last_key_date = key($mmb_user_hits);
            $current_date  = date('Y-m-d');
            if ($last_key_date != $curent_date)
                $this->set_hit_count(true);
        }
        $stats['hit_counter'] = get_option('user_hit_count');
        
        $this->get_installer_instance();
        $stats['upgradable_themes']  = $this->installer_instance->get_upgradable_themes();
        $stats['upgradable_plugins'] = $this->installer_instance->get_upgradable_plugins();
        
        $pending_comments = get_comments('status=hold&number=' . $num_pending_comments);
        foreach ($pending_comments as &$comment) {
            $commented_post      = get_post($comment->comment_post_ID);
            $comment->post_title = $commented_post->post_title;
        }
        $stats['comments']['pending'] = $pending_comments;
        
        
        $approved_comments = get_comments('status=approve&number=' . $num_approved_comments);
        foreach ($approved_comments as &$comment) {
            $commented_post      = get_post($comment->comment_post_ID);
            $comment->post_title = $commented_post->post_title;
        }
        $stats['comments']['approved'] = $approved_comments;
        
        
        $all_posts              = get_posts('post_status=publish&numberposts=3&orderby=modified&order=desc');
        $recent_posts           = array();
        
        foreach ($all_posts as $id => $recent_post) {
        	$recent = new stdClass();
        	$recent->post_permalink = get_permalink($recent_post->ID);
        	$recent->ID = $recent_post->ID;
            $recent->post_date = $recent_post->post_date;
            $recent->post_title = $recent_post->post_title;
            $recent->post_modified = $recent_post->post_modified;
            $recent->comment_count = $recent_post->comment_count;          
            $recent_posts[] = $recent;
        }
        
        
        $all_drafts = get_posts('post_status=draft&numberposts=20&orderby=modified&order=desc');
        $recent_drafts           = array();
        foreach ($all_drafts as $id => $recent_draft) {
        	$recent = new stdClass();
        	$recent->post_permalink = get_permalink($recent_draft->ID);
        	$recent->ID = $recent_draft->ID;
            $recent->post_date = $recent_draft->post_date;
            $recent->post_title = $recent_draft->post_title;
            $recent->post_modified = $recent_draft->post_modified;
         
            $recent_drafts[] = $recent;
        } 
		
		$all_scheduled = get_posts('post_status=future&numberposts=20&orderby=post_date&order=desc');
        $scheduled_posts           = array();
        foreach ($all_scheduled as $id => $scheduled) {
        	$recent = new stdClass();
        	$recent->post_permalink = get_permalink($scheduled->ID);
        	$recent->ID = $scheduled->ID;
            $recent->post_date = $scheduled->post_date;
            $recent->post_title = $scheduled->post_title;
            $recent->post_modified = $scheduled->post_modified;
         
            $scheduled_posts[] = $recent;
        }
        
        
        $all_pages_published = get_pages('post_status=publish&numberposts=3&orderby=modified&order=desc');
        $recent_pages_published           = array();
        foreach ((array)$all_pages_published as $id => $recent_page_published) {
        	$recent = new stdClass();
       		$recent->post_permalink = get_permalink($recent_page_published->ID);
        	
        	$recent->ID = $recent_page_published->ID;
            $recent->post_date = $recent_page_published->post_date;
            $recent->post_title = $recent_page_published->post_title;
            $recent->post_modified = $recent_page_published->post_modified;
         
            $recent_posts[] = $recent;
        }
		usort($recent_posts, 'cmp_posts_worker');
		$stats['posts'] = array_slice($recent_posts, 0, 20);
		
        $all_pages_drafts = get_pages('post_status=draft&numberposts=20&orderby=modified&order=desc');
        $recent_pages_drafts           = array();
        foreach ((array)$all_pages_drafts as $id => $recent_pages_draft) {
        	$recent = new stdClass();
        	$recent->post_permalink = get_permalink($recent_pages_draft->ID);
        	$recent->ID = $recent_pages_draft->ID;
            $recent->post_date = $recent_pages_draft->post_date;
            $recent->post_title = $recent_pages_draft->post_title;
            $recent->post_modified = $recent_pages_draft->post_modified;
         
            $recent_drafts[] = $recent;
        }
		usort($recent_drafts, 'cmp_posts_worker');
		$stats['drafts'] = array_slice($recent_drafts, 0, 20);
		
		
		$pages_scheduled = get_pages('post_status=future&numberposts=20&orderby=modified&order=desc');
        $recent_pages_drafts           = array();
        foreach ((array)$pages_scheduled as $id => $scheduled) {
        	$recent = new stdClass();
        	$recent->post_permalink = get_permalink($scheduled->ID);
        	$recent->ID = $scheduled->ID;
            $recent->post_date = $scheduled->post_date;
            $recent->post_title = $scheduled->post_title;
            $recent->post_modified = $scheduled->post_modified;
         
            $scheduled_posts[] = $recent;
        }
		usort($scheduled_posts, 'cmp_posts_worker');
		$stats['scheduled'] = array_slice($scheduled_posts, 0, 20);
		
		
        
        if (!function_exists('get_filesystem_method'))
         include_once(ABSPATH . 'wp-admin/includes/file.php');
         
        $stats['writable'] = $this->is_server_writable();
        
        $stats['backups'] = $this->get_backups();
        
        $stats = apply_filters('mmb_stats_filter', $stats);        
        return $stats;
    }
    
    function get_stats_notification($params)
    {	

        global $mmb_wp_version, $mmb_plugin_dir;
        $stats = array();
        
        //define constants
        $num_pending_comments  = 1000;

        
        require_once(ABSPATH . '/wp-admin/includes/update.php');
        
        $stats['worker_version']    = MMB_WORKER_VERSION;
        $stats['wordpress_version'] = $mmb_wp_version;
        
        $updates = $this->mmb_get_transient('update_core');
        
        if ($updates->updates[0]->response == 'development' || version_compare($mmb_wp_version, $updates->updates[0]->current, '<')) {
            $updates->updates[0]->current_version = $mmb_wp_version;
            $stats['core_updates']                = $updates->updates[0];
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

        
        $this->get_theme_instance();
        $this->get_plugin_instance();
        $stats['upgradable_themes']  = $this->theme_instance->get_upgradable_themes();
        $stats['upgradable_plugins'] = $this->plugin_instance->get_upgradable_plugins();
        
        $pending_comments = get_comments('status=hold&number=' . $num_pending_comments);

        $stats['comments_pending'] = count($pending_comments);

        return $stats;
    }
    
    
    function get_comments_stats(){
    	$num_pending_comments  = 3;
        $num_approved_comments = 3;
        $pending_comments = get_comments('status=hold&number=' . $num_pending_comments);
        foreach ($pending_comments as &$comment) {
            $commented_post      = get_post($comment->comment_post_ID);
            $comment->post_title = $commented_post->post_title;
        }
        $stats['comments']['pending'] = $pending_comments;
        
        
        $approved_comments = get_comments('status=approve&number=' . $num_approved_comments);
        foreach ($approved_comments as &$comment) {
            $commented_post      = get_post($comment->comment_post_ID);
            $comment->post_title = $commented_post->post_title;
        }
        $stats['comments']['approved'] = $approved_comments;
        
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
        $stats['site_home']      = get_option('home');
        
        
        if (!function_exists('get_filesystem_method'))
         include_once(ABSPATH . 'wp-admin/includes/file.php');
         
        $stats['writable'] = $this->is_server_writable();
       
        return $stats;
    }
    
   
    
    function set_hit_count($fix_count = false)
    {
        if ($fix_count || (!is_admin() && !MMB_Stats::detect_bots())) {
            $date           = date('Y-m-d');
            $user_hit_count = (array) get_option('user_hit_count');
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
                    $user_hit_count[$date] = ((int)$user_hit_count[$date] ) + 1;
                
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
            if ((boolean)strpos($bot, $agent)) {
                $thebot = $bot;
                break;
            }
        }
        
        if ($thebot != '') {
            return $thebot;
        } else
            return false;
    }
    
		function get_backups()
		{
		$worker_options = get_option('mmb-worker');
  	//$backup_file = $worker_options['backups'][$type]['path'];
  	return $worker_options['backups'];     
		}
    
}

function cmp_posts_worker($a, $b)
{
    return ($a->post_modified < $b->post_modified);
}


?>