<?php

/*************************************************************
 * stats.class.php
 * Get Site Stats
 * Copyright (c) 2011 Prelovac Media
 * www.prelovac.com
 **************************************************************/
class MMB_Stats extends MMB_Core
{
    /*************************************************************
     * FACADE functions
     * (functions to be called after a remote call from Master)
     **************************************************************/

    function get_core_update($stats, $options = array())
    {
        global $wp_version, $wp_local_package;
        $locale = empty($wp_local_package) ? 'en_US' : $wp_local_package;
        $current_transient = null;
        if (isset($options['core']) && $options['core']) {
            $core = $this->mmb_get_transient('update_core');
            if (isset($core->updates) && !empty($core->updates)) {
                foreach ($core->updates as $update) {
                    if ($update->locale == get_locale() && strtolower($update->response) == "upgrade") {
                        $current_transient = $update;
                        break;
                    }
                }
                //fallback to first
                if (!$current_transient) {
                    $current_transient = $core->updates[0];
                }
                if ($current_transient->response == "development" || version_compare($wp_version, $current_transient->current, '<') || $locale !== $current_transient->locale) {
                    $current_transient->current_version = $wp_version;
                    $stats['core_updates']              = $current_transient;
                } else {
                    $stats['core_updates'] = false;
                }
            } else {
                $stats['core_updates'] = false;
            }
        }

        return $stats;
    }

    function get_hit_counter($stats, $options = array())
    {
        $mmb_user_hits = get_option('user_hit_count');
        if (is_array($mmb_user_hits)) {
            end($mmb_user_hits);
            $last_key_date = key($mmb_user_hits);
            $current_date  = date('Y-m-d');
            if ($last_key_date != $current_date) {
                $this->set_hit_count(true);
            }
        }
        $stats['hit_counter'] = get_option('user_hit_count');

        return $stats;
    }

    function get_comments($stats, $options = array())
    {
        $nposts  = isset($options['numberposts']) ? (int) $options['numberposts'] : 20;
        $trimlen = isset($options['trimcontent']) ? (int) $options['trimcontent'] : 200;

        if ($nposts) {
            $comments = get_comments('status=hold&number='.$nposts);
            if (!empty($comments)) {
                foreach ($comments as &$comment) {
                    $commented_post           = get_post($comment->comment_post_ID);
                    $comment->post_title      = $commented_post->post_title;
                    $comment->comment_content = $this->trim_content($comment->comment_content, $trimlen);
                    unset($comment->comment_author_IP);
                    unset($comment->comment_karma);
                    unset($comment->comment_agent);
                    unset($comment->comment_type);
                    unset($comment->comment_parent);
                }
                $stats['comments']['pending'] = $comments;
            }

            $comments = get_comments('status=approve&number='.$nposts);
            if (!empty($comments)) {
                foreach ($comments as &$comment) {
                    $commented_post           = get_post($comment->comment_post_ID);
                    $comment->post_title      = $commented_post->post_title;
                    $comment->comment_content = $this->trim_content($comment->comment_content, $trimlen);
                    unset($comment->comment_author_IP);
                    unset($comment->comment_karma);
                    unset($comment->comment_agent);
                    unset($comment->comment_type);
                    unset($comment->comment_parent);
                }
                $stats['comments']['approved'] = $comments;
            }
        }

        return $stats;
    }

    function get_posts($stats, $options = array())
    {
        $nposts = isset($options['numberposts']) ? (int) $options['numberposts'] : 20;
        $user_info = $this->getUsersIDs();

        if ($nposts) {
            $posts        = get_posts('post_status=publish&numberposts='.$nposts.'&orderby=post_date&order=desc');
            $recent_posts = array();
            if (!empty($posts)) {
                foreach ($posts as $id => $recent_post) {
                    $recent                 = new stdClass();
                    $recent->post_permalink = get_permalink($recent_post->ID);
                    $recent->ID             = $recent_post->ID;
                    $recent->post_date      = $recent_post->post_date;
                    $recent->post_title     = $recent_post->post_title;
                    $recent->post_type      = $recent_post->post_type;
                    $recent->comment_count  = (int) $recent_post->comment_count;
                    $recent->post_author_name    = array('author_id' => $recent_post->post_author, 'author_name' => $user_info[$recent_post->post_author]);
                    $recent_posts[]         = $recent;
                }
            }

            $posts                  = get_pages('post_status=publish&numberposts='.$nposts.'&orderby=post_date&order=desc');
            $recent_pages_published = array();
            if (!empty($posts)) {
                foreach ((array) $posts as $id => $recent_page_published) {
                    $recent                 = new stdClass();
                    $recent->post_permalink = get_permalink($recent_page_published->ID);
                    $recent->post_type      = $recent_page_published->post_type;
                    $recent->ID             = $recent_page_published->ID;
                    $recent->post_date      = $recent_page_published->post_date;
                    $recent->post_title     = $recent_page_published->post_title;
                    $recent->post_author    = array('author_id' => $recent_page_published->post_author, 'author_name' => $user_info[$recent_page_published->post_author]);

                    $recent_posts[] = $recent;
                }
            }
            if (!empty($recent_posts)) {
                usort(
                    $recent_posts,
                    array(
                        $this,
                        'cmp_posts_worker'
                    )
                );
                $stats['posts'] = array_slice($recent_posts, 0, $nposts);
            }
        }

        return $stats;
    }

    function get_drafts($stats, $options = array())
    {
        $nposts = isset($options['numberposts']) ? (int) $options['numberposts'] : 20;

        if ($nposts) {
            $drafts        = get_posts('post_status=draft&numberposts='.$nposts.'&orderby=post_date&order=desc');
            $recent_drafts = array();
            if (!empty($drafts)) {
                foreach ($drafts as $id => $recent_draft) {
                    $recent                 = new stdClass();
                    $recent->post_permalink = get_permalink($recent_draft->ID);
                    $recent->post_type      = $recent_draft->post_type;
                    $recent->ID             = $recent_draft->ID;
                    $recent->post_date      = $recent_draft->post_date;
                    $recent->post_title     = $recent_draft->post_title;

                    $recent_drafts[] = $recent;
                }
            }
            $drafts              = get_pages('post_status=draft&numberposts='.$nposts.'&orderby=post_date&order=desc');
            $recent_pages_drafts = array();
            if (!empty($drafts)) {
                foreach ((array) $drafts as $id => $recent_pages_draft) {
                    $recent                 = new stdClass();
                    $recent->post_permalink = get_permalink($recent_pages_draft->ID);
                    $recent->ID             = $recent_pages_draft->ID;
                    $recent->post_type      = $recent_pages_draft->post_type;
                    $recent->post_date      = $recent_pages_draft->post_date;
                    $recent->post_title     = $recent_pages_draft->post_title;

                    $recent_drafts[] = $recent;
                }
            }
            if (!empty($recent_drafts)) {
                usort(
                    $recent_drafts,
                    array(
                        $this,
                        'cmp_posts_worker'
                    )
                );
                $stats['drafts'] = array_slice($recent_drafts, 0, $nposts);
            }
        }

        return $stats;
    }

    function get_scheduled($stats, $options = array())
    {
        $nposts = isset($options['numberposts']) ? (int) $options['numberposts'] : 20;

        if ($nposts) {
            $scheduled       = get_posts('post_status=future&numberposts='.$nposts.'&orderby=post_date&order=desc');
            $scheduled_posts = array();
            if (!empty($scheduled)) {
                foreach ($scheduled as $id => $scheduled) {
                    $recent                 = new stdClass();
                    $recent->post_permalink = get_permalink($scheduled->ID);
                    $recent->ID             = $scheduled->ID;
                    $recent->post_date      = $scheduled->post_date;
                    $recent->post_type      = $scheduled->post_type;
                    $recent->post_title     = $scheduled->post_title;
                    $scheduled_posts[]      = $recent;
                }
            }
            $scheduled           = get_pages('post_status=future&numberposts='.$nposts.'&orderby=post_date&order=desc');
            $recent_pages_drafts = array();
            if (!empty($scheduled)) {
                foreach ((array) $scheduled as $id => $scheduled) {
                    $recent                 = new stdClass();
                    $recent->post_permalink = get_permalink($scheduled->ID);
                    $recent->ID             = $scheduled->ID;
                    $recent->post_type      = $scheduled->post_type;
                    $recent->post_date      = $scheduled->post_date;
                    $recent->post_title     = $scheduled->post_title;

                    $scheduled_posts[] = $recent;
                }
            }
            if (!empty($scheduled_posts)) {
                usort(
                    $scheduled_posts,
                    array(
                        $this,
                        'cmp_posts_worker'
                    )
                );
                $stats['scheduled'] = array_slice($scheduled_posts, 0, $nposts);
            }
        }

        return $stats;
    }

    function get_backups($stats, $options = array())
    {
        $stats['mwp_backups']      = $this->get_backup_instance()->get_backup_stats();
        $stats['mwp_next_backups'] = $this->get_backup_instance()->get_next_schedules();

        return $stats;
    }

    function get_backup_req($stats = array(), $options = array())
    {
        $stats['mwp_backups']      = $this->get_backup_instance()->get_backup_stats();
        $stats['mwp_next_backups'] = $this->get_backup_instance()->get_next_schedules();
        $stats['mwp_backup_req']   = $this->get_backup_instance()->check_backup_compat();

        return $stats;
    }

    function get_updates($stats, $options = array())
    {
        $upgrades = false;
        $premium  = array();
        if (isset($options['premium']) && $options['premium']) {
            $premium_updates = array();
            $upgrades        = apply_filters('mwp_premium_update_notification', $premium_updates);
            if (!empty($upgrades)) {
                foreach ($upgrades as $data) {
                    if (isset($data['Name'])) {
                        $premium[] = $data['Name'];
                    }
                }
                $stats['premium_updates'] = $upgrades;
                $upgrades                 = false;
            }
        }
        if (isset($options['themes']) && $options['themes']) {
            $this->get_installer_instance();
            $upgrades = $this->installer_instance->get_upgradable_themes($premium);
            if (!empty($upgrades)) {
                $stats['upgradable_themes'] = $upgrades;
                $upgrades                   = false;
            }
        }

        if (isset($options['plugins']) && $options['plugins']) {
            $this->get_installer_instance();
            $upgrades = $this->installer_instance->get_upgradable_plugins($premium);
            if (!empty($upgrades)) {
                $stats['upgradable_plugins'] = $upgrades;
                $upgrades                    = false;
            }
        }

        return $stats;
    }

    function get_errors($stats, $options = array())
    {
        $period     = isset($options['days']) ? (int) $options['days'] * 86400 : 86400;
        $maxerrors  = isset($options['max']) ? (int) $options['max'] : 100;
        $last_bytes = isset($options['last_bytes']) ? (int) $options['last_bytes'] : 20480; //20KB
        $errors     = array();
        if (isset($options['get']) && $options['get'] == true) {
            if (function_exists('ini_get')) {
                $logpath = ini_get('error_log');
                if (!empty($logpath) && file_exists($logpath)) {
                    $logfile  = @fopen($logpath, 'r');
                    $filesize = @filesize($logpath);
                    $read_start = 0;
                    if (is_resource($logfile) && $filesize > 0) {
                        if ($filesize > $last_bytes) {
                            $read_start = $filesize - $last_bytes;
                        }
                        fseek($logfile, $read_start, SEEK_SET);
                        while (!feof($logfile)) {
                            $line = fgets($logfile);
                            preg_match('/\[(.*)\]/Ui', $line, $match);
                            if (!empty($match) && (strtotime($match[1]) > ((int) time() - $period))) {
                                $key = str_replace($match[0], '', $line);
                                if (!isset($errors[$key])) {
                                    $errors[$key] = 1;
                                } else {
                                    $errors[$key] = $errors[$key] + 1;
                                }
                                if (count($errors) >= $maxerrors) {
                                    break;
                                }
                            }
                        }
                    }
                    if (is_resource($logfile)) {
                        fclose($logfile);
                    }
                    if (!empty($errors)) {
                        $stats['errors']  = $errors;
                        $stats['logpath'] = $logpath;
                        $stats['logsize'] = $filesize;
                    }
                }
            }
        }

        return $stats;
    }

    function getUserList()
    {
        $filter = array(
            'user_roles' => array(
                'administrator'
            ),
            'username'=>'',
            'username_filter'=>'',
        );
        $users = $this->get_user_instance()->get_users($filter);

        if (empty($users['users']) || !is_array($users['users'])) {
            return array();
        }

        $userList = array();
        foreach ($users['users'] as $user) {
            $userList[] = $user['user_login'];
        }

        return $userList;
    }

    function pre_init_stats($params)
    {
        global $_mmb_item_filter;

        include_once(ABSPATH.'wp-includes/update.php');
        include_once(ABSPATH.'/wp-admin/includes/update.php');

        $stats = $this->mmb_parse_action_params('pre_init_stats', $params, $this);
        $num   = extract($params);

        if (function_exists('w3tc_pgcache_flush') || function_exists('wp_cache_clear_cache')) {
            $this->mmb_delete_transient('update_core');
            $this->mmb_delete_transient('update_plugins');
            $this->mmb_delete_transient('update_themes');
            @wp_version_check();
            @wp_update_plugins();
            @wp_update_themes();
        }

        if ($refresh == 'transient') {
            $current = $this->mmb_get_transient('update_core');
            if (isset($current->last_checked) || get_option('mmb_forcerefresh')) {
                update_option('mmb_forcerefresh', false);
                if (time() - $current->last_checked > 7200) {
                    @wp_version_check();
                    @wp_update_plugins();
                    @wp_update_themes();
                }
            }
        }

        global $wpdb, $mmb_wp_version, $mmb_plugin_dir, $wp_version, $wp_local_package;

        $stats['worker_version']        = $GLOBALS['MMB_WORKER_VERSION'];
        $stats['worker_revision']       = $GLOBALS['MMB_WORKER_REVISION'];
        $stats['wordpress_version']     = $wp_version;
        $stats['wordpress_locale_pckg'] = $wp_local_package;
        $stats['php_version']           = phpversion();
        $stats['mysql_version']         = $wpdb->db_version();
        $stats['server_functionality']  = $this->get_backup_instance()->getServerInformationForStats();
        $stats['wp_multisite']          = $this->mmb_multisite;
        $stats['network_install']       = $this->network_admin_install;
        $stats['cookies']               = $this->get_stat_cookies();
        $stats['admin_usernames']       = $this->getUserList();

        if (!function_exists('get_filesystem_method')) {
            include_once(ABSPATH.'wp-admin/includes/file.php');
        }
        $mmode = get_option('mwp_maintenace_mode');

        if (!empty($mmode) && isset($mmode['active']) && $mmode['active'] == true) {
            $stats['maintenance'] = true;
        }
        $stats['writable'] = $this->is_server_writable();

        return $stats;
    }

    function get($params)
    {
        global $wpdb, $mmb_wp_version, $mmb_plugin_dir, $_mmb_item_filter;

        include_once(ABSPATH.'wp-includes/update.php');
        include_once(ABSPATH.'wp-admin/includes/update.php');

        $stats = $this->mmb_parse_action_params('get', $params, $this);

        $update_check = array();
        $num          = extract($params);
        if ($refresh == 'transient') {
            $update_check = apply_filters('mwp_premium_update_check', $update_check);
            if (!empty($update_check)) {
                foreach ($update_check as $update) {
                    if (is_array($update['callback'])) {
                        $update_result = call_user_func(
                            array(
                                $update['callback'][0],
                                $update['callback'][1]
                            )
                        );
                    } else {
                        if (is_string($update['callback'])) {
                            $update_result = call_user_func($update['callback']);
                        }
                    }
                }
            }
        }

        if ($this->mmb_multisite) {
            $stats = $this->get_multisite($stats);
        }

        update_option('mmb_stats_filter', $params['item_filter']['get_stats']);
        $stats = apply_filters('mmb_stats_filter', $stats);

        return $stats;
    }

    function get_multisite($stats = array())
    {
        global $current_user, $wpdb;
        $user_blogs    = get_blogs_of_user($current_user->ID);
        $network_blogs = $wpdb->get_results("select `blog_id`, `site_id` from `{$wpdb->blogs}`");
        $user_id = $GLOBALS['mwp_user_id'] ? $GLOBALS['mwp_user_id'] : false;

        if ($this->network_admin_install == '1' && is_super_admin($user_id)) {
            if (!empty($network_blogs)) {
                $blogs = array();
                foreach ($network_blogs as $details) {
                    if ($details->site_id == $details->blog_id) {
                        continue;
                    } else {
                        $data = get_blog_details($details->blog_id);
                        if (in_array($details->blog_id, array_keys($user_blogs))) {
                            $stats['network_blogs'][] = $data->siteurl;
                        } else {
                            $user = get_users(
                                array(
                                    'blog_id' => $details->blog_id,
                                    'number'  => 1
                                )
                            );
                            if (!empty($user)) {
                                $stats['other_blogs'][$data->siteurl] = $user[0]->user_login;
                            }
                        }
                    }
                }
            }
        }

        return $stats;
    }

    function get_comments_stats()
    {
        $num_pending_comments  = 3;
        $num_approved_comments = 3;
        $pending_comments      = get_comments('status=hold&number='.$num_pending_comments);
        foreach ($pending_comments as &$comment) {
            $commented_post      = get_post($comment->comment_post_ID);
            $comment->post_title = $commented_post->post_title;
        }
        $stats['comments']['pending'] = $pending_comments;


        $approved_comments = get_comments('status=approve&number='.$num_approved_comments);
        foreach ($approved_comments as &$comment) {
            $commented_post      = get_post($comment->comment_post_ID);
            $comment->post_title = $commented_post->post_title;
        }
        $stats['comments']['approved'] = $approved_comments;

        return $stats;
    }

    function get_auth_cookies($user_id)
    {
        $cookies = array();
        $secure  = is_ssl();
        $secure  = apply_filters('secure_auth_cookie', $secure, $user_id);

        if ($secure) {
            $auth_cookie_name = SECURE_AUTH_COOKIE;
            $scheme           = 'secure_auth';
        } else {
            $auth_cookie_name = AUTH_COOKIE;
            $scheme           = 'auth';
        }

        $expiration = time() + 2592000;

        $cookies[$auth_cookie_name] = wp_generate_auth_cookie($user_id, $expiration, $scheme);
        $cookies[LOGGED_IN_COOKIE]  = wp_generate_auth_cookie($user_id, $expiration, 'logged_in');

        if (defined('WPE_APIKEY')) {
            $cookies['wpe-auth'] = md5('wpe_auth_salty_dog|'.WPE_APIKEY);
        }

        return $cookies;
    }

    function get_stat_cookies()
    {
        global $current_user;

        $cookies = $this->get_auth_cookies($current_user->ID);

        $publicKey = $this->get_master_public_key();

        if (empty($cookies)) {
            return $cookies;
        }

        require_once dirname(__FILE__).'/../../src/PHPSecLib/Crypt/RSA.php';

        $rsa = new Crypt_RSA();
        $rsa->setEncryptionMode(CRYPT_RSA_SIGNATURE_PKCS1);
        $rsa->loadKey($publicKey);

        foreach ($cookies as &$cookieValue) {
            $cookieValue = base64_encode($rsa->encrypt($cookieValue));
        }

        return $cookies;
    }

    function get_initial_stats()
    {
        global $mmb_plugin_dir, $_mmb_item_filter, $current_user;

        $stats = array(
            'email'           => get_option('admin_email'),
            'no_openssl'      => $this->get_random_signature(),
            'content_path'    => WP_CONTENT_DIR,
            'worker_path'     => $mmb_plugin_dir,
            'worker_version'  => $GLOBALS['MMB_WORKER_VERSION'],
            'worker_revision' => $GLOBALS['MMB_WORKER_REVISION'],
            'site_title'      => get_bloginfo('name'),
            'site_tagline'    => get_bloginfo('description'),
            'db_name'         => $this->get_active_db(),
            'site_home'       => get_option('home'),
            'admin_url'       => admin_url(),
            'wp_multisite'    => $this->mmb_multisite,
            'network_install' => $this->network_admin_install,
            'cookies'         => $this->get_stat_cookies()
        );

        if ($this->mmb_multisite) {
            $details = get_blog_details($this->mmb_multisite);
            if (isset($details->site_id)) {
                $details = get_blog_details($details->site_id);
                if (isset($details->siteurl)) {
                    $stats['network_parent'] = $details->siteurl;
                }
            }
        }
        if (!function_exists('get_filesystem_method')) {
            include_once(ABSPATH.'wp-admin/includes/file.php');
        }

        $stats['writable'] = $this->is_server_writable();

        $_mmb_item_filter['pre_init_stats'] = array('core_update', 'hit_counter', 'comments', 'backups', 'posts', 'drafts', 'scheduled');
        $_mmb_item_filter['get']            = array('updates', 'errors');

        $filter = array(
            'refresh'     => 'transient',
            'item_filter' => array(
                'get_stats' => array(
                    array('updates', array('plugins' => true, 'themes' => true, 'premium' => true)),
                    array('core_update', array('core' => true)),
                    array('posts', array('numberposts' => 5)),
                    array('drafts', array('numberposts' => 5)),
                    array('scheduled', array('numberposts' => 5)),
                    array('hit_counter'),
                    array('comments', array('numberposts' => 5)),
                    array('backups'),
                    'plugins' => array(
                        'cleanup' => array(
                            'overhead'  => array(),
                            'revisions' => array('num_to_keep' => 'r_5'),
                            'spam'      => array(),
                        )
                    ),
                ),
            )
        );

        $pre_init_data = $this->pre_init_stats($filter);
        $init_data     = $this->get($filter);

        $stats['initial_stats'] = array_merge($init_data, $pre_init_data);

        return $stats;
    }

    function get_active_db()
    {
        global $wpdb;
        $sql = 'SELECT DATABASE() as db_name';

        $sqlresult = $wpdb->get_row($sql);
        $active_db = $sqlresult->db_name;

        return $active_db;

    }

    static function set_hit_count($fix_count = false)
    {
        global $mmb_core;
        $uptime_robot = array(
            "74.86.158.106",
            "74.86.179.130",
            "74.86.179.131",
            "46.137.190.132",
            "122.248.234.23",
            "74.86.158.107"
        ); //don't let uptime robot to affect visit count

        if ($fix_count || (!is_admin() && !MMB_Stats::is_bot() && !isset($_GET['doing_wp_cron']) && !in_array($_SERVER['REMOTE_ADDR'], $uptime_robot))) {

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
                if (!$fix_count) {
                    $user_hit_count[$date] = ((int) $user_hit_count[$date]) + 1;
                }

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
            if ($last_key_date != $curent_date) {
                $this->set_hit_count(true);
            }
        }

        return get_option('user_hit_count');
    }


    static function is_bot()
    {
        $agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '' ;

        if ($agent == '') {
            return false;
        }

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

        foreach ($bot_list as $bot) {
            if (strpos($agent, $bot) !== false) {
                return true;
            }
        }

        return false;
    }


    function set_notifications($params)
    {
        if (empty($params)) {
            return false;
        }

        extract($params);

        if (!isset($delete)) {
            $mwp_notifications = array(
                'plugins'          => $plugins,
                'themes'           => $themes,
                'wp'               => $wp,
                'backups'          => $backups,
                'url'              => $url,
                'notification_key' => $notification_key
            );
            update_option('mwp_notifications', $mwp_notifications);
        } else {
            delete_option('mwp_notifications');
        }

        return true;

    }

    //Cron update check for notifications
    function check_notifications()
    {
        global $wpdb, $mmb_wp_version, $mmb_plugin_dir, $wp_version, $wp_local_package;

        $mwp_notifications = get_option('mwp_notifications', true);

        $args    = array();
        $updates = array();
        $send    = 0;
        if (is_array($mwp_notifications) && $mwp_notifications != false) {
            include_once(ABSPATH.'wp-includes/update.php');
            include_once(ABSPATH.'/wp-admin/includes/update.php');
            extract($mwp_notifications);

            //Check wordpress core updates
            if ($wp) {
                @wp_version_check();
                if (function_exists('get_core_updates')) {
                    $wp_updates = get_core_updates();
                    if (!empty($wp_updates)) {
                        $current_transient = $wp_updates[0];
                        if ($current_transient->response == "development" || version_compare($wp_version, $current_transient->current, '<')) {
                            $current_transient->current_version = $wp_version;
                            $updates['core_updates']            = $current_transient;
                        } else {
                            $updates['core_updates'] = array();
                        }
                    } else {
                        $updates['core_updates'] = array();
                    }
                }
            }

            //Check plugin updates
            if ($plugins) {
                @wp_update_plugins();
                $this->get_installer_instance();
                $updates['upgradable_plugins'] = $this->installer_instance->get_upgradable_plugins();
            }

            //Check theme updates
            if ($themes) {
                @wp_update_themes();
                $this->get_installer_instance();

                $updates['upgradable_themes'] = $this->installer_instance->get_upgradable_themes();
            }

            if ($backups) {
                $this->get_backup_instance();
                $backups            = $this->backup_instance->get_backup_stats();
                $updates['backups'] = $backups;
                foreach ($backups as $task_name => $backup_results) {
                    foreach ($backup_results as $k => $backup) {
                        if (isset($backups[$task_name][$k]['server']['file_path'])) {
                            unset($backups[$task_name][$k]['server']['file_path']);
                        }
                    }
                }
                $updates['backups'] = $backups;
            }


            if (!empty($updates)) {
                $args['body']['updates']          = $updates;
                $args['body']['notification_key'] = $notification_key;
                $send                             = 1;
            }

        }


        $alert_data = get_option('mwp_pageview_alerts', true);
        if (is_array($alert_data) && $alert_data['alert']) {
            $pageviews                           = get_option('user_hit_count');
            $args['body']['alerts']['pageviews'] = $pageviews;
            $args['body']['alerts']['site_id']   = $alert_data['site_id'];
            if (!isset($url)) {
                $url = $alert_data['url'];
            }
            $send = 1;
        }

        if ($send) {
            if (!class_exists('WP_Http')) {
                include_once(ABSPATH.WPINC.'/class-http.php');
            }
            $result = wp_remote_post($url, $args);

            if (is_array($result) && $result['body'] == 'mwp_delete_alert') {
                delete_option('mwp_pageview_alerts');
            }
        }


    }


    function cmp_posts_worker($a, $b)
    {
        return ($a->post_date < $b->post_date);
    }

    function trim_content($content = '', $length = 200)
    {
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            $content = (mb_strlen($content) > ($length + 3)) ? mb_substr($content, 0, $length).'...' : $content;
        } else {
            $content = (strlen($content) > ($length + 3)) ? substr($content, 0, $length).'...' : $content;
        }

        return $content;
    }


    public static function readd_alerts($params = array())
    {
        if (empty($params) || !isset($params['alerts'])) {
            return $params;
        }

        if (!empty($params['alerts'])) {
            update_option('mwp_pageview_alerts', $params['alerts']);
            unset($params['alerts']);
        }

        return $params;
    }
}
