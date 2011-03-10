<?php
class Mmb_Stats extends Mmb_Core
{
    function __construct()
    {
        parent::__construct();
    }
    
    /*************************************************************
    * FACADE functions
    * (functions to be called after a remote XMLRPC from Master)
    **************************************************************/
        
    function get($args)
    {
        $this->_escape($args);
        
        $username = $args[0];
        $password = $args[1]; 
//        $this->_log($username);
//        $this->_log($password);
        
        if (!$user = $this->login($username, $password)) 
        {
            return $this->error;
        }
        
        // Things to get:
        // pending comments
        // drafts
        // available plugin upgrades
        // available wordpress upgrade
        // version of worker plugin
        // and???
        
        $stats = array();
        
		$mmb_user_hits = get_option('user_hit_count');
        end($mmb_user_hits);
        $last_key_date = key($mmb_user_hits);
        $current_date = date('Y-m-d');
        if($last_key_date != $curent_date)
           $this->set_hit_count(true);
        
        $stats['hit_counter'] = get_option('user_hit_count');
		
		
        if (current_user_can('moderate_comments'))
        {
            // pending comments
            $pending_comments = get_comments('status=hold&number=5');
            // trim off unnecessary data
            foreach ($pending_comments as &$comment)
            {
                $commented_post = get_post($comment->comment_post_ID);
                $comment->post_title = $commented_post->post_title;
            }
            
            $stats['pending_comments'] = $pending_comments;
        }
        
        // drafts
        $drafts = get_posts('post_status=draft&numberposts=0');
        // trim off unnecessary data
        foreach ($drafts as $draft)
        {
            if (!current_user_can('edit_post', $draft->ID)) continue;
            
            $props = get_object_vars($draft);
            foreach ($props as $name => $value) 
            {
                if ($name != 'post_title' && $name != 'ID' && $name != 'post_modified')
                {
                    unset($draft->$name);
                }
            }
        }
        
        if (!empty($drafts))
        {
            $stats['drafts'] = $drafts;
        }
        
        if (current_user_can('activate_plugins'))
        {
            // available plugin upgrades
            $stats['upgradable_plugins'] = $this->get_plugin_instance()->get_upgradable_plugins();
        }

        if (current_user_can('update_plugins'))
        {
            // core upgrade
            $new_version = $this->get_wp_instance()->check_version(NULL, FALSE);
            if (!is_a($new_version, 'IXR_Error'))
            {
                $stats['new_version'] = $new_version;
            }

            //@lk worker version
            //we can either store the version string in a file or a string or both
            global $mmb_plugin_dir;
//            $worker_version = file_get_contents($mmb_plugin_dir.'/version');
            $stats['worker_version'] = MMB_WORKER_VERSION;
        }
		if (current_user_can('install_themes')){
            $stats['upgradable_themes'] = $this->get_theme_instance()->get_upgradable_themes();
        }
		$stats['server_ftp'] = 0;
		if((!defined('FTP_HOST') || !defined('FTP_USER') || !defined('FTP_PASS')) && !is_writable(WP_CONTENT_DIR)){
			$stats['server_ftp'] = 1;
		}
        
        return $stats;
    }

    function get_server_stats($args) {
        $this->_escape($args);

        $username = $args[0];
        $password = $args[1];
//        $this->_log($username);
//        $this->_log($password);

        if (!$user = $this->login($username, $password)){
            return $this->error;
        }
        
        $stats = array();

        if(!current_user_can('administrator')){
            return array('add_error'=>'You are not an administrator on %s. Please use an account with administrator privilege.');
        }

        if (current_user_can('upload_files')){
            // check if wp-content is writable
//            $this->_log(is_writable(WP_CONTENT_DIR));
//            if(is_writable(WP_CONTENT_DIR) && is_writable(WP_CONTENT_DIR.'/plugins') && is_writable(WP_CONTENT_DIR.'/themes') && is_writable(WP_CONTENT_DIR.'/uploads') && is_writable(WP_CONTENT_DIR.'/upgrade')){
            if(is_writable(WP_CONTENT_DIR) && is_writable(WP_CONTENT_DIR.'/plugins') && is_writable(WP_CONTENT_DIR.'/themes')){
                $stats['writable'] = TRUE;
            }else{
                $stats['writable'] = FALSE;
            }
            global $mmb_plugin_dir;
            $stats['worker_path'] = $mmb_plugin_dir;
            $stats['content_path'] = WP_CONTENT_DIR;
        }
        return $stats;
        
    }

    function set_hit_count($fix_count = false) {
//        TODO : IP based checking for hit count
//
//        In activation hook
//        if(!get_option('user_hit_count')){
//              $user_hit_count = array();
//              update_option('user_hit_count', $user_hit_count);
//        }
//
//         Save a transient to the database
//        $transient = $_SERVER['REMOTE_ADDR'];
//        $expiration = somethig; // equal to 8 hrs
//
//        if(!(get_transient($transient)))
//              set_transient($transient, $transient, $expiration);
//
//         Fetch a saved transient
//        $current_user_ip = get_transient($transient);
//        if(!(get_transient($transient))) then increment the hit count

        if(is_single () || $fix_count){
           $date = date('Y-m-d');
            $user_hit_count = get_option('user_hit_count');
            if(!$user_hit_count){
                $user_hit_count[$date] = 1;
                update_option('user_hit_count', $user_hit_count);
             }else{
                $dated_keys = array_keys($user_hit_count);
                $last_visit_date = $dated_keys[count($dated_keys)-1];

//                $diff = strtotime($date) - strtotime($last_visit_date);
//                $sec   = $diff % 60;
//                $diff  = intval($diff / 60);
//                $min   = $diff % 60;
//                $diff  = intval($diff / 60);
//                $hours = $diff % 24;
//                $days  = intval($diff / 24);

                 $days = intval ( ( strtotime($date) - strtotime($last_visit_date) ) / 60 / 60 / 24 );

                if($days>1){
//                    $date_elems = getdate(strtotime($last_visit_date));
//                    $yr = $date_elems['year'];
//                    $mn = $date_elems['mon'];
//                    $dt = $date_elems['mday'];

                    $date_to_add = date('Y-m-d', strtotime($last_visit_date));

                    for($i = 1; $i<$days ; $i++){
                        if(count($user_hit_count) > 7)
                        {
                          $shifted = @array_shift($user_hit_count);
                        }
                        //$next_day = ($dt + $i);
                        //$next_key = $yr.'-'.$mn.'-'.$next_day;
                        $next_key = strtotime('+1 day', strtotime($date_to_add));
                        if($next_key == $date){
                            break;
                        }else{
                                $user_hit_count[$next_key] = 0;
                        }
                    }
                    
//                    if($next_key == $date)
//                        $user_hit_count[$next_key] = 0;
                }

                if(!isset($user_hit_count[$date])){
                        $user_hit_count[$date] = 0;
                }
                if(!$fix_count)
                        $user_hit_count[$date] += 1;

                if(count($user_hit_count) > 7)
                {
                  $shifted = @array_shift($user_hit_count);
                }

                update_option('user_hit_count', $user_hit_count);
//                $this->_log($user_hit_count);
          }
        }
    }
    
    function get_hit_count() {
        // Check if there are no hits on last key date
        $mmb_user_hits = get_option('user_hit_count');
        end($mmb_user_hits);
        $last_key_date = key($mmb_user_hits);
        $current_date = date('Y-m-d');
        if($last_key_date != $curent_date)
           $this->set_hit_count(true);
        
        return get_option('user_hit_count');
    }

}