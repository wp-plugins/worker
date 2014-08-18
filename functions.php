<?php

function mwp_autoload($class)
{
    if (substr($class, 0, 8) === 'Dropbox_'
        || substr($class, 0, 8) === 'Symfony_'
        || substr($class, 0, 8) === 'Monolog_'
        || substr($class, 0, 5) === 'Gelf_'
        || substr($class, 0, 4) === 'MWP_'
        || substr($class, 0, 4) === 'MMB_'
        || substr($class, 0, 3) === 'S3_'
    ) {
        $file = dirname(__FILE__).'/src/'.str_replace('_', '/', $class).'.php';
        if (file_exists($file)) {
            include_once $file;
        }
    }
}

function mwp_register_autoload_google()
{
    static $registered;

    if ($registered) {
        return;
    } else {
        $registered = true;
    }

    if (version_compare(PHP_VERSION, '5.3', '<')) {
        spl_autoload_register('mwp_autoload_google');
    } else {
        spl_autoload_register('mwp_autoload_google', true, true);
    }
}

function mwp_autoload_google($class)
{
    if (substr($class, 0, 7) === 'Google_') {
        $file = dirname(__FILE__).'/src/'.str_replace('_', '/', $class).'.php';
        if (file_exists($file)) {
            include_once $file;
        }
    }
}

function mwp_container()
{
    static $container;

    if ($container === null) {
        $parameters = get_option('mwp_container_parameters', array());
        $container  = new MMB_Container($parameters);
    }

    return $container;
}

/**
 * @return Monolog_Psr_LoggerInterface
 */
function mwp_logger()
{
    static $mwp_logger;
    if (!get_option('mwp_debug_enable', false)) {
        if ($mwp_logger === null) {
            $mwp_logger = new Monolog_Logger('worker', array(new Monolog_Handler_NullHandler()));
        }

        return $mwp_logger;
    }
    if ($mwp_logger instanceof Monolog_Logger) {
        return $mwp_logger;
    }
    if ($mwp_logger === null) {
        $mwp_logger = true;
        $logger     = mwp_container()->getLogger();
        Monolog_Registry::addLogger($logger, 'worker');

        $errorHandler = new Monolog_ErrorHandler($logger);
        $errorHandler->registerErrorHandler();
        $errorHandler->registerExceptionHandler();
        $errorHandler->registerFatalHandler(null, 1024);
    }

    return Monolog_Registry::getInstance('worker');
}

/**
 * @param $appKey
 * @param $appSecret
 * @param $token
 * @param $tokenSecret
 *
 * @return Dropbox_Client
 */
function mwp_dropbox_oauth1_factory($appKey, $appSecret, $token, $tokenSecret)
{
    $oauthToken ='OAuth oauth_version="1.0", oauth_signature_method="PLAINTEXT", oauth_consumer_key="'.$appKey.'", oauth_token="'.$token.'", oauth_signature="'.$appSecret.'&'.$tokenSecret.'"';
    $client         = new Dropbox_Client($oauthToken, $token);

    return $client;
}

function mwp_format_memory_limit($limit)
{
    if ((string) (int) $limit === (string) $limit) {
        // The number is numeric.
        return mwp_format_bytes($limit);
    }

    $units = strtolower(substr($limit, -1));

    if (!in_array($units, array('b', 'k', 'm', 'g'))) {
        // Invalid size unit.
        return $limit;
    }

    $number = substr($limit, 0, -1);

    if ((string) (int) $number !== $number) {
        // The number isn't numeric.
        return $number;
    }

    switch ($units) {
        case 'g':
            return $number.' GB';
        case 'm':
            return $number.' MB';
        case 'k':
            return $number.' KB';
        case 'b':
        default:
            return $number.' B';
    }
}


function mwp_format_bytes($bytes)
{
    $bytes = (int) $bytes;

    if ($bytes > 1024 * 1024 * 1024) {
        return round($bytes / 1024 / 1024 / 1024, 2).' GB';
    } elseif ($bytes > 1024 * 1024) {
        return round($bytes / 1024 / 1024, 2).' MB';
    } elseif ($bytes > 1024) {
        return round($bytes / 1024, 2).' KB';
    }

    return $bytes.' B';
}

function mwp_log_warnings()
{
    // If mbstring.func_overload is set, it changes the behavior of the standard string functions in
    // ways that makes external libraries like Dropbox break.
    $mbstring_func_overload = ini_get("mbstring.func_overload");
    if ($mbstring_func_overload & 2 == 2) {
        mwp_logger()->warning('"mbstring.func_overload" changes the behavior of the standard string functions in ways that makes external libraries like Dropbox break');
    }

    if (strlen((string) PHP_INT_MAX) < 19) {
        // Looks like we're running on a 32-bit build of PHP.  This could cause problems because some of the numbers
        // we use (file sizes, quota, etc) can be larger than 32-bit ints can handle.
        mwp_logger()->warning("Some external libraries rely on 64-bit integers, but it looks like we're running on a version of PHP that doesn't support 64-bit integers (PHP_INT_MAX=".((string) PHP_INT_MAX).").");
    }
}

function search_posts_by_term($params = false)
{

    global $wpdb, $current_user;

    $search_type = trim($params['search_type']);
    $search_term = strtolower(trim($params['search_term']));
    switch ($search_type) {
        case 'page_post':
            $num_posts        = 10;
            $num_content_char = 30;

            $term_orig = trim($params['search_term']);

            $term_base = addslashes(trim($params['search_term']));

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

            foreach ($posts_array as $post) {
                //highlight searched term

                if (substr_count(strtolower($post->post_title), strtolower($term_orig))) {
                    $str_position_start = strpos(strtolower($post->post_title), strtolower($term_orig));

                    $post->post_title = substr($post->post_title, 0, $str_position_start).'<b>'.
                        substr($post->post_title, $str_position_start, strlen($term_orig)).'</b>'.
                        substr($post->post_title, $str_position_start + strlen($term_orig));

                }
                $post->post_content = html_entity_decode($post->post_content);

                $post->post_content = strip_tags($post->post_content);


                if (substr_count(strtolower($post->post_content), strtolower($term_orig))) {
                    $str_position_start = strpos(strtolower($post->post_content), strtolower($term_orig));

                    $start     = $str_position_start > $num_content_char ? $str_position_start - $num_content_char : 0;
                    $first_len = $str_position_start > $num_content_char ? $num_content_char : $str_position_start;

                    $start_substring    = $start > 0 ? '...' : '';
                    $post->post_content = $start_substring.substr($post->post_content, $start, $first_len).'<b>'.
                        substr($post->post_content, $str_position_start, strlen($term_orig)).'</b>'.
                        substr($post->post_content, $str_position_start + strlen($term_orig), $num_content_char).'...';


                } else {
                    $post->post_content = substr($post->post_content, 0, 50).'...';
                }

                $ret_posts[] = array(
                    'ID'             => $post->ID,
                    'post_permalink' => get_permalink($post->ID),
                    'post_date'      => $post->post_date,
                    'post_title'     => $post->post_title,
                    'post_content'   => $post->post_content,
                    'post_modified'  => $post->post_modified,
                    'comment_count'  => $post->comment_count,
                );
            }
            mmb_response($ret_posts, true);
            break;

        case 'plugin':
            $plugins = get_option('active_plugins');

            if (!function_exists('get_plugin_data')) {
                include_once(ABSPATH.'/wp-admin/includes/plugin.php');
            }

            $have_plugin = array();
            foreach ($plugins as $plugin) {
                $pl          = WP_PLUGIN_DIR.'/'.$plugin;
                $pl_extended = get_plugin_data($pl);
                $pl_name     = $pl_extended['Name'];
                if (strpos(strtolower($pl_name), $search_term) > -1) {

                    $have_plugin[] = $pl_name;
                }
            }
            if ($have_plugin) {
                mmb_response($have_plugin, true);
            } else {
                mmb_response('Not found', false);
            }
            break;
        case 'theme':
            $theme       = strtolower(get_option('stylesheet'));
            $tm          = ABSPATH.'wp-content/themes/'.$theme.'/style.css';
            $tm_extended = get_theme_data($tm);
            $tm_name     = $tm_extended['Name'];
            $have_theme  = array();
            if (strpos(strtolower($tm_name), $search_term) > -1) {
                $have_theme[] = $tm_name;
                mmb_response($have_theme, true);
            } else {
                mmb_response('Not found', false);
            }
            break;
        default:
            mmb_response('Not found', false);
    }
}

function mmb_add_action($action = false, $callback = false)
{
    if (!$action || !$callback) {
        return;
    }

    global $mmb_actions;

    if (!is_callable($callback)) {
        wp_die('The provided argument is not a valid callback');
    }

    if (isset($mmb_actions[$action])) {
        wp_die('Cannot redeclare ManageWP action "'.$action.'".');
    }

    $mmb_actions[$action] = $callback;
}

function mmb_get_extended_info($stats)
{
    $params                 = get_option('mmb_stats_filter');
    $filter                 = isset($params['plugins']['cleanup']) ? $params['plugins']['cleanup'] : array();
    $stats['num_revisions'] = mmb_num_revisions($filter['revisions']);
    //$stats['num_revisions'] = 5;
    $stats['overhead']          = mmb_handle_overhead(false);
    $stats['num_spam_comments'] = mmb_num_spam_comments();

    return $stats;
}

/* Revisions */
function cleanup_delete_worker($params = array())
{
    $revision_params = get_option('mmb_stats_filter');
    $revision_filter = isset($revision_params['plugins']['cleanup']) ? $revision_params['plugins']['cleanup'] : array();

    $params_array = explode('_', $params['actions']);
    $return_array = array();

    foreach ($params_array as $param) {
        switch ($param) {
            case 'revision':
                if (mmb_delete_all_revisions($revision_filter['revisions'])) {
                    $return_array['revision'] = 'OK';
                } else {
                    $return_array['revision_error'] = 'OK, nothing to do';
                }
                break;
            case 'overhead':
                if (mmb_handle_overhead(true)) {
                    $return_array['overhead'] = 'OK';
                } else {
                    $return_array['overhead_error'] = 'OK, nothing to do';
                }
                break;
            case 'comment':
                if (mmb_delete_spam_comments()) {
                    $return_array['comment'] = 'OK';
                } else {
                    $return_array['comment_error'] = 'OK, nothing to do';
                }
                break;
            default:
                break;
        }

    }

    unset($params);

    mmb_response($return_array, true);
}

function mmb_num_revisions($filter)
{
    global $wpdb;

    $allRevisions = $wpdb->get_results("SELECT ID, post_name FROM {$wpdb->posts} WHERE post_type = 'revision'", ARRAY_A);

    $revisionsToDelete    = 0;
    $revisionsToKeepCount = array();

    if (isset($filter['num_to_keep']) && !empty($filter['num_to_keep'])) {
        $num_rev = str_replace("r_", "", $filter['num_to_keep']);

        foreach ($allRevisions as $revision) {
            $revisionsToKeepCount[$revision['post_name']] = isset($revisionsToKeepCount[$revision['post_name']])
                ? $revisionsToKeepCount[$revision['post_name']] + 1
                : 1;

            if ($revisionsToKeepCount[$revision['post_name']] > $num_rev) {
                ++$revisionsToDelete;
            }
        }
    } else {
        $revisionsToDelete = count($allRevisions);
    }

    return $revisionsToDelete;
}

function mmb_select_all_revisions()
{
    global $wpdb;
    $sql       = "SELECT * FROM $wpdb->posts WHERE post_type = 'revision'";
    $revisions = $wpdb->get_results($sql);

    return $revisions;
}

function mmb_delete_all_revisions($filter)
{
    global $wpdb;
    $where = '';
    $keep = isset($filter['num_to_keep']) ? $filter['num_to_keep'] : false;
    if ($keep) {
        $num_rev          = str_replace("r_", "", $keep);
        $allRevisions = $wpdb->get_results("SELECT ID, post_name FROM {$wpdb->posts} WHERE post_type = 'revision' ORDER BY post_date DESC", ARRAY_A);
        $revisionsToKeep = array(0 => 0);
        $revisionsToKeepCount = array();

        foreach ($allRevisions as $revision) {
            $revisionsToKeepCount[$revision['post_name']] = isset($revisionsToKeepCount[$revision['post_name']])
                ? $revisionsToKeepCount[$revision['post_name']] + 1
                : 1;

            if ($revisionsToKeepCount[$revision['post_name']] <= $num_rev) {
                $revisionsToKeep[] = $revision['ID'];
            }
        }

        $notInQuery = join(', ', $revisionsToKeep);

        $where = "AND a.ID NOT IN ({$notInQuery})";
    }

    $sql = "DELETE a,b,c FROM $wpdb->posts a LEFT JOIN $wpdb->term_relationships b ON (a.ID = b.object_id) LEFT JOIN $wpdb->postmeta c ON (a.ID = c.post_id) WHERE a.post_type = 'revision' {$where}";

    $revisions = $wpdb->query($sql);

    return $revisions;
}

function mmb_handle_overhead($clear = false)
{
    /** @var wpdb $wpdb */
    global $wpdb;
    $query        = 'SHOW TABLE STATUS';
    $tables       = $wpdb->get_results($query, ARRAY_A);
    $total_gain   = 0;
    $table_string = '';
    foreach ($tables as $table) {
        if (isset($table['Engine']) && $table['Engine'] === 'MyISAM') {
            if ($wpdb->base_prefix != $wpdb->prefix) {
                if (preg_match('/^'.$wpdb->prefix.'*/Ui', $table['Name'])) {
                    if ($table['Data_free'] > 0) {
                        $total_gain += $table['Data_free'] / 1024;
                        $table_string .= $table['Name'].",";
                    }
                }
            } else {
                if (preg_match('/^'.$wpdb->prefix.'[0-9]{1,20}_*/Ui', $table['Name'])) {
                    continue;
                } else {
                    if ($table['Data_free'] > 0) {
                        $total_gain += $table['Data_free'] / 1024;
                        $table_string .= $table['Name'].",";
                    }
                }
            }
            // @todo check if the cleanup was successful, if not, set a flag always skip innodb cleanup
            //} elseif (isset($table['Engine']) && $table['Engine'] == 'InnoDB') {
            //    $innodb_file_per_table = $wpdb->get_results("SHOW VARIABLES LIKE 'innodb_file_per_table'");
            //    if (isset($innodb_file_per_table[0]->Value) && $innodb_file_per_table[0]->Value === "ON") {
            //        if ($table['Data_free'] > 0) {
            //            $total_gain += $table['Data_free'] / 1024;
            //            $table_string .= $table['Name'].",";
            //        }
            //    }
        }
    }

    if ($clear) {
        $table_string = substr($table_string, 0, strlen($table_string) - 1); //remove last ,
        $table_string = rtrim($table_string);
        $query = "OPTIMIZE TABLE $table_string";
        $optimize = $wpdb->query($query);

        return (bool)$optimize;
    } else {
        return round($total_gain, 3);
    }
}


/* Spam Comments */
function mmb_num_spam_comments()
{
    global $wpdb;
    $sql       = "SELECT COUNT(*) FROM $wpdb->comments WHERE comment_approved = 'spam'";
    $num_spams = $wpdb->get_var($sql);

    return $num_spams;
}

function mmb_delete_spam_comments()
{
    global $wpdb;
    $spam = 1;
    $total = 0;
    while (!empty($spam)) {
        $getCommentIds = "SELECT comment_ID FROM $wpdb->comments WHERE comment_approved = 'spam' LIMIT 200";
        $spam = $wpdb->get_results($getCommentIds);
        foreach ($spam as $comment) {
            wp_delete_comment($comment->comment_ID, true);
        }
        $total += count($spam);
        if (!empty($spam)) {
            usleep(100000);
        }
    }

    return $total;
}

function mmb_get_spam_comments()
{
    global $wpdb;
    $sql   = "SELECT * FROM $wpdb->comments as a LEFT JOIN $wpdb->commentmeta as b WHERE a.comment_ID = b.comment_id AND a.comment_approved = 'spam'";
    $spams = $wpdb->get_results($sql);

    return $spams;
}

function mwp_is_nio_shell_available()
{
    static $check;
    if(isset($check)){
        return $check;
    }
    try {
        $process = new Symfony_Process_Process("cd .", dirname(__FILE__), array(), null, 1);
        $process->run();
        $check = $process->isSuccessful();
    } catch (Exception $e) {
        $check = false;
    }
    return $check;
}

function mwp_is_shell_available()
{
    if (mwp_is_safe_mode()) {
        return false;
    }
    if (!function_exists('proc_open') || !function_exists('escapeshellarg')) {
        return false;
    }

    if (extension_loaded('suhosin') && $suhosin = ini_get('suhosin.executor.func.blacklist')) {
        $suhosin   = explode(',', $suhosin);
        $blacklist = array_map('trim', $suhosin);
        $blacklist = array_map('strtolower', $blacklist);
        if (in_array('proc_open', $blacklist)) {
            return false;
        }
    }

    if (!mwp_is_nio_shell_available()) {
        return false;
    }

    return true;
}

function mwp_is_safe_mode()
{
    $value = ini_get("safe_mode");
    if ((int) $value === 0 || strtolower($value) === "off") {
        return false;
    }

    return true;
}
