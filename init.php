<?php
/*
Plugin Name: ManageWP - Worker
Plugin URI: https://managewp.com
Description: ManageWP Worker plugin allows you to manage your WordPress sites from one dashboard. Visit <a href="https://managewp.com">ManageWP.com</a> for more information.
Version: 3.9.30
Author: ManageWP
Author URI: https://managewp.com
License: GPL2
*/

/*************************************************************
 * init.php
 * Initialize the communication with master
 * Copyright (c) 2011 Prelovac Media
 * www.prelovac.com
 **************************************************************/
if (!defined('ABSPATH')) {
    exit;
}

if (!defined('MMB_WORKER_VERSION')) {
    define('MMB_WORKER_VERSION', '3.9.30');
}

$GLOBALS['MMB_WORKER_VERSION'] = '3.9.30';
$GLOBALS['MMB_WORKER_REVISION'] = '2014-09-08 00:00:00';

/**
 * Reserved memory for fatal error handling execution context.
 */
$GLOBALS['mwp_reserved_memory'] = str_repeat(' ', 1024 * 20);
/**
 * If we ever get only partially upgraded due to a server error or misconfiguration,
 * attempt to disable the plugin and notify the site's administrator via email.
 */
function mwp_fail_safe()
{
    $GLOBALS['mwp_reserved_memory'] = null;

    $lastError = error_get_last();

    if (!$lastError || $lastError['type'] !== E_ERROR) {
        return;
    }

    $activePlugins = get_option('active_plugins');
    $workerIndex   = array_search(plugin_basename(__FILE__), $activePlugins);
    if ($workerIndex === false) {
        // Plugin is not yet enabled, possibly in activation context.
        return;
    }

    $errorSource = realpath($lastError['file']);
    // We might be in eval() context.
    if (!$errorSource) {
        return;
    }

    // The only fatal error that we would get would be a 'Class 'X' not found in ...', so look out only for those messages.
    if (!preg_match('/^Class \'[^\']+\' not found$/', $lastError['message'])) {
        return;
    }

    // Only look for files that belong to this plugin.
    $pluginBase = realpath(dirname(__FILE__));
    if (stripos($errorSource, $pluginBase) !== 0) {
        return;
    }

    unset($activePlugins[$workerIndex]);
    // Reset indexes.
    $activePlugins = array_values($activePlugins);
    update_option('active_plugins', $activePlugins);

    // We probably won't have access to the wp_mail function.
    $mailFn  = function_exists('wp_mail') ? 'wp_mail' : 'mail';
    $siteUrl = get_option('siteurl');
    $title = sprintf("ManageWP Worker deactivated on %s", $siteUrl);
    $to = get_option('admin_email');
    $brand = get_option('mwp_worker_brand');
    if (!empty($brand['admin_email'])) {
        $to = $brand['admin_email'];
    }

    $fullError = print_r($lastError, 1);
    $workerSettings = get_option('wrksettings');
    $userID = 0;
    if (!empty($workerSettings['dataown'])) {
        $userID = (int) $workerSettings['dataown'];
    }
    $body = sprintf('Worker deactivation due to an error. The site that was deactivated - %s. User email - %s (UserID: %s). The error that caused this: %s', $siteUrl, $to, $userID, $fullError);
    $mailFn('support@managewp.com', $title, $body);

    // If we're inside a cron scope, don't attempt to hide this error.
    if (defined('DOING_CRON') && DOING_CRON) {
        return;
    }

    // If we're inside a normal request scope, we apologize. Retry the request so user doesn't have to see an ugly error page.
    if (!empty($_SERVER['REQUEST_URI'])) {
        $siteUrl .= $_SERVER['REQUEST_URI'];
    }
    if (headers_sent()) {
        // The headers are probably sent if the PHP configuration has the 'display_errors' directive enabled. In that case try a meta redirect.
        echo sprintf('<meta http-equiv="refresh" content="0; url=%s">', htmlspecialchars($siteUrl, ENT_QUOTES));
    } else {
        header('Location: '.htmlspecialchars($siteUrl, ENT_QUOTES));
    }
    exit;
}

register_shutdown_function('mwp_fail_safe');

require_once dirname(__FILE__).'/functions.php';

if (!defined('MMB_XFRAME_COOKIE')) {
    $siteurl = function_exists('get_site_option') ? get_site_option('siteurl') : get_option('siteurl');
    define('MMB_XFRAME_COOKIE', $xframe = 'wordpress_'.md5($siteurl).'_xframe');
}

global $wpdb, $mmb_plugin_dir, $mmb_plugin_url, $wp_version, $mmb_filters, $_mmb_item_filter;
if (version_compare(PHP_VERSION, '5.2.0', '<')) // min version 5 supported
{
    exit("<p>ManageWP Worker plugin requires PHP 5.2 or higher.</p>");
}

if (version_compare(PHP_VERSION, '5.3', '<')) {
    spl_autoload_register('mwp_autoload');
} else {
    // The prepend parameter was added in PHP 5.3.0
    spl_autoload_register('mwp_autoload', true, true);
}

// Will register the logger as the error handler.
mwp_logger();

$mmb_wp_version = $wp_version;
$mmb_plugin_dir = WP_PLUGIN_DIR.'/'.basename(dirname(__FILE__));
$mmb_plugin_url = WP_PLUGIN_URL.'/'.basename(dirname(__FILE__));

define('MWP_SHOW_LOG', false);
// <stats.class.php>
add_filter('mwp_website_add', 'MMB_Stats::readd_alerts');
// <backup.class.php>
define('MWP_BACKUP_DIR', WP_CONTENT_DIR.'/managewp/backups');
define('MWP_DB_DIR', MWP_BACKUP_DIR.'/mwp_db');

mmb_add_action('search_posts_by_term', 'search_posts_by_term');
add_filter('mmb_stats_filter', 'mmb_get_extended_info');
mmb_add_action('cleanup_delete', 'cleanup_delete_worker');

// <widget.class.php>
$mwp_worker_brand = get_option("mwp_worker_brand");
$worker_brand     = 0;
if (is_array($mwp_worker_brand)) {
    if ($mwp_worker_brand['name'] || $mwp_worker_brand['desc'] || $mwp_worker_brand['author'] || $mwp_worker_brand['author_url']) {
        $worker_brand = 1;
    }
}
if (!$worker_brand) {
    add_action('widgets_init', create_function('', 'return register_widget("MMB_Widget");'));
}

if (!function_exists('mmb_parse_data')) {
    function mmb_parse_data($data = array())
    {
        if (empty($data)) {
            return $data;
        }

        $data = (array) $data;
        if (isset($data['params'])) {
            $data['params'] = mmb_filter_params($data['params']);
        }

        $postkeys = array('action', 'params', 'id', 'signature', 'setting', 'add_site_signature_id', 'add_site_signature');

        if (!empty($data)) {
            foreach ($data as $key => $items) {
                if (!in_array($key, $postkeys)) {
                    unset($data[$key]);
                }
            }
        }

        return $data;
    }
}

if (!function_exists('mmb_filter_params')) {
    function mmb_filter_params($array = array())
    {

        $filter = array('current_user', 'wpdb');
        $return = array();
        foreach ($array as $key => $val) {
            if (!is_int($key) && in_array($key, $filter)) {
                continue;
            }

            if (is_array($val)) {
                $return[$key] = mmb_filter_params($val);
            } else {
                $return[$key] = $val;
            }
        }

        return $return;
    }
}
if (!function_exists('mmb_authenticate')) {
    function mmb_authenticate()
    {
        global $_mwp_data, $_mwp_auth, $mmb_core, $HTTP_RAW_POST_DATA;
        if (!isset($HTTP_RAW_POST_DATA)) {
            $HTTP_RAW_POST_DATA = file_get_contents('php://input');
        }
        $compat       = false;
        $compatActive = false;
        $contentType  = empty($_SERVER['CONTENT_TYPE']) ? false : $_SERVER['CONTENT_TYPE'];

        if ($compat && empty($_SERVER['HTTP_MWP_ACTION']) && $contentType === 'application/json') {
            $compatActive = true;
        }

        if (empty($_SERVER['HTTP_MWP_ACTION']) && !$compatActive) {
            return;
        }
        $_mwp_data = json_decode($HTTP_RAW_POST_DATA, true);

        if (!$_mwp_data) {
            return;
        }
        $_mwp_data = mmb_parse_data($_mwp_data);

        if ($compatActive) {
            if (empty($_mwp_data['action'])) {
                return;
            }
            $_mwp_data['signature'] = base64_decode($_mwp_data['signature']);
        } else {
            $_mwp_data['action']    = $_SERVER['HTTP_MWP_ACTION'];
            $_mwp_data['id']        = isset($_SERVER['HTTP_MWP_MESSAGE_ID']) ? $_SERVER['HTTP_MWP_MESSAGE_ID'] : "";
            $_mwp_data['signature'] = isset($_SERVER['HTTP_MWP_SIGNATURE']) ? base64_decode($_SERVER['HTTP_MWP_SIGNATURE']) : '';
        }

        $usernameUsed = array_key_exists('username', $_mwp_data['params']) ? $_mwp_data['params']['username'] : null;
        if (empty($_mwp_data['params']['username']) || !$mmb_core->check_if_user_exists($_mwp_data['params']['username'])) {
            $filter = array(
                'user_roles' => array(
                    'administrator'
                ),
                'username'=>'',
				'username_filter'=>'',
            );
            $users = $mmb_core->get_user_instance()->get_users($filter);

            if (empty($users['users'])) {
                mmb_response('We could not find an administrator user to use. Please contact support.', false);
            }

            $_mwp_data['params']['username'] = $users['users'][0]['user_login'];
        }

        if (isset($_mwp_data['params']['username']) && !is_user_logged_in()) {
            $user = function_exists('get_user_by') ? get_user_by('login', $_mwp_data['params']['username']) : get_user_by('login', $_mwp_data['params']['username']);
        }

        if ($_mwp_data['action'] === 'add_site') {
            $_mwp_auth = mwp_add_site_verify_signature($_mwp_data, $usernameUsed);
            if(isset($user)){
                $GLOBALS['mwp_user_id'] = $user->ID;
            }

            return;
        } else {
            $_mwp_auth = $mmb_core->authenticate_message($_mwp_data['action'].$_mwp_data['id'], $_mwp_data['signature'], $_mwp_data['id']);
        }

        if ($_mwp_auth !== true) {
            mmb_response($_mwp_auth['error'], false);
        }


				//$this->w3tc_flush();
				
        if (isset($user)) {
            wp_set_current_user($user->ID);
            if (@getenv('IS_WPE')) {
                wp_set_auth_cookie($user->ID);
            }
        }
        
        
        /*if (!defined('WP_ADMIN')) {
            define('WP_ADMIN', true);
        }*/

        if(defined('ALTERNATE_WP_CRON') && !defined('DOING_AJAX') && ALTERNATE_WP_CRON === true ){
            define('DOING_AJAX', true);
        }
    }
}

if (!function_exists("mwp_add_site_verify_signature")) {
    function mwp_add_site_verify_signature($_mwp_data, $posted_username = null)
    {
        global $mmb_plugin_dir;

        $nonce = new MWP_Security_HashNonce();
        $nonce->setValue($_mwp_data['id']);
        if (!$nonce->verify()) {
            $_mwp_auth = array(
                'error' => 'Invalid nonce used. Please contact support'
            );
            mmb_response($_mwp_auth['error'], false);
        } else {

            if (!empty($_mwp_data['add_site_signature']) && !empty($_mwp_data['add_site_signature_id'])) {
                $signature            = base64_decode($_mwp_data['add_site_signature']);
                $signature_id         = $_mwp_data['add_site_signature_id'];
                $plaintext            = array();
                $plaintext['setting'] = $_mwp_data['setting'];
                $plaintext['params']  = $_mwp_data['params'];
                if (isset($posted_username)) {
                    $plaintext['params']['username'] = $posted_username;
                }
                if (file_exists($mmb_plugin_dir.'/publickeys/'.$signature_id.'.pub')) {
                    $plaintext = json_encode($plaintext);
                    require_once dirname(__FILE__).'/src/PHPSecLib/Crypt/RSA.php';
                    $rsa = new Crypt_RSA();
                    $rsa->setSignatureMode(CRYPT_RSA_SIGNATURE_PKCS1);
                    $rsa->loadKey(file_get_contents($mmb_plugin_dir.'/publickeys/'.$signature_id.'.pub')); // public key
                    $_mwp_auth = $rsa->verify($plaintext, $signature);
                } else {
                    $_mwp_auth = false; // we don't have key
                }
            } else {
                $_mwp_auth = false;
            }

            if ($_mwp_auth !== true) {
                $_mwp_auth = array(
                    'error' => 'Invalid message signature. Deactivate and activate the ManageWP Worker plugin on this site, then re-add it to your ManageWP account.'
                );
                mmb_response($_mwp_auth['error'], false);
            }
        }

        return $_mwp_auth;
    }
}

if (!function_exists('mmb_parse_request')) {
    function mmb_parse_request()
    {
        global $mmb_core, $wp_db_version, $wpmu_version, $_wp_using_ext_object_cache, $_mwp_data, $_mwp_auth;
        if (empty($_mwp_auth)) {
            MMB_Stats::set_hit_count();

            return;
        }
        ob_start();
        $_wp_using_ext_object_cache = false;
        @set_time_limit(1200);

        if (isset($_mwp_data['setting'])) {
            if(array_key_exists("dataown",$_mwp_data['setting'])){
                $oldconfiguration = array("dataown" => $_mwp_data['setting']['dataown']);
                $mmb_core->save_options($oldconfiguration);
                unset($_mwp_data['setting']['dataown']);
            }

            $configurationService = new MWP_Configuration_Service();
            $configuration        = new MWP_Configuration_Conf($_mwp_data['setting']);
            $configurationService->saveConfiguration($configuration);
        }

        if ($_mwp_data['action'] === 'add_site') {
            mmb_add_site($_mwp_data['params']);
            mmb_response('You should never see this.', false);
        }

        /* in case database upgrade required, do database backup and perform upgrade ( wordpress wp_upgrade() function ) */
        if (strlen(trim($wp_db_version)) && !defined('ACX_PLUGIN_DIR')) {
            if (get_option('db_version') != $wp_db_version) {
                /* in multisite network, please update database manualy */
                if (empty($wpmu_version) || (function_exists('is_multisite') && !is_multisite())) {
                    if (!function_exists('wp_upgrade')) {
                        include_once(ABSPATH.'wp-admin/includes/upgrade.php');
                    }

                    ob_clean();
                    @wp_upgrade();
                    @do_action('after_db_upgrade');
                    ob_end_clean();
                }
            }
        }

        if (isset($_mwp_data['params']['secure'])) {
            if (is_array($_mwp_data['params']['secure'])) {
                $secureParams = $_mwp_data['params']['secure'];
                foreach ($secureParams as $key => $value) {
                    $secureParams[$key] = base64_decode($value);
                }
                $_mwp_data['params']['secure'] = $secureParams;
            } else {
                $_mwp_data['params']['secure'] = base64_decode($_mwp_data['params']['secure']);
            }
            if ($decrypted = $mmb_core->_secure_data($_mwp_data['params']['secure'])) {
                $decrypted = maybe_unserialize($decrypted);
                if (is_array($decrypted)) {
                    foreach ($decrypted as $key => $val) {
                        if (!is_numeric($key)) {
                            $_mwp_data['params'][$key] = $val;
                        }
                    }
                    unset($_mwp_data['params']['secure']);
                } else {
                    $_mwp_data['params']['secure'] = $decrypted;
                }
            }

            if (!$decrypted && $mmb_core->get_random_signature() !== false) {
                require_once dirname(__FILE__).'/src/PHPSecLib/Crypt/AES.php';
                $cipher = new Crypt_AES(CRYPT_AES_MODE_ECB);
                $cipher->setKey($mmb_core->get_random_signature());
                $decrypted                           = $cipher->decrypt($_mwp_data['params']['secure']);
                $_mwp_data['params']['account_info'] = json_decode($decrypted, true);
            }

        }

        $logData = array(
            'action'            => $_mwp_data['action'],
            'action_parameters' => $_mwp_data['params'],
            'action_settings'   => $_mwp_data['setting'],
        );

        if (!empty( $_mwp_data['setting'])) {
            $logData['settings'] = $_mwp_data['setting'];
        }

        mwp_logger()->debug('Master request: "{action}"', $logData);

        if (!$mmb_core->register_action_params($_mwp_data['action'], $_mwp_data['params'])) {
            global $_mmb_plugin_actions;
            $_mmb_plugin_actions[$_mwp_data['action']] = $_mwp_data['params'];
        }

        ob_end_clean();
    }
}
/* Main response function */
if (!function_exists('mmb_response')) {

    function mmb_response($response = false, $success = true)
    {
        $return = array();

        if ((is_array($response) && empty($response)) || (!is_array($response) && strlen($response) == 0)) {
            $return['error'] = 'Empty response.';
        } else {
            if ($success) {
                $return['success'] = $response;
            } else {
                $return['error'] = $response;
            }
        }

        if (!headers_sent()) {
            header('HTTP/1.0 200 OK');
            header('Content-Type: text/plain');
        }

        mwp_logger()->debug('Master response: {action_response_status}', array(
            'action_response_status' => $success ? 'success' : 'error',
            'action_response'        => $return,
            'headers_sent'           => headers_sent(),
        ));

        exit("<MWPHEADER>".base64_encode(serialize($return))."<ENDMWPHEADER>");
    }
}


if (!function_exists('mmb_add_site')) {
    function mmb_add_site($params)
    {
        global $mmb_core;
        $num = extract($params);

        if ($num) {
            if (!get_option('_worker_public_key')) {
                $public_key = base64_decode($public_key);

                if (function_exists('openssl_verify')) {
                    $verify = openssl_verify($action.$id, base64_decode($signature), $public_key);
                    if ($verify == 1) {
                        $mmb_core->set_master_public_key($public_key);
                        //$mmb_core->set_worker_message_id($id);


                        $mmb_core->get_stats_instance();
                        if (isset($notifications) && is_array($notifications) && !empty($notifications)) {
                            $mmb_core->stats_instance->set_notifications($notifications);
                        }
                        if (isset($brand) && is_array($brand) && !empty($brand)) {
                            update_option('mwp_worker_brand', $brand);
                        }

                        if (isset($add_settigns) && is_array($add_settigns) && !empty($add_settigns)) {
                            apply_filters('mwp_website_add', $add_settigns);
                        }

                        mmb_response($mmb_core->stats_instance->get_initial_stats(), true);
                    } else {
                        if ($verify == 0) {

                            //mmb_response('Site could not be added. OpenSSL verification error: "'.openssl_error_string().'". Contact your hosting support to check the OpenSSL configuration.', false);

                        } else {
                            mmb_response('Command not successful. Please try again.', false);
                        }
                    }
                }

                if (!get_option('_worker_nossl_key')) {
                    srand();

                    $random_key = md5(base64_encode($public_key).rand(0, getrandmax()));

                    $mmb_core->set_random_signature($random_key);
                    //$mmb_core->set_worker_message_id($id);
                    $mmb_core->set_master_public_key($public_key);


                    $mmb_core->get_stats_instance();
                    if (is_array($notifications) && !empty($notifications)) {
                        $mmb_core->stats_instance->set_notifications($notifications);
                    }

                    if (is_array($brand) && !empty($brand)) {
                        update_option('mwp_worker_brand', $brand);
                    }

                    mmb_response($mmb_core->stats_instance->get_initial_stats(), true);
                } else {
                    mmb_response('Sorry, we were unable to communicate with your website. Please deactivate, then activate ManageWP Worker plugin on your website and try again or contact our support.', false);
                }

            } else {
                mmb_response('Sorry, we were unable to communicate with your website. Please deactivate, then activate ManageWP Worker plugin on your website and try again or contact our support.', false);
            }
        } else {
            mmb_response('Invalid parameters received. Please try again.', false);
        }
    }
}

if (!function_exists('mmb_remove_site')) {
    function mmb_remove_site($params)
    {
        extract($params);
        global $mmb_core;
        $mmb_core->uninstall($deactivate);

        include_once(ABSPATH.'wp-admin/includes/plugin.php');
        $plugin_slug = basename(dirname(__FILE__)).'/'.basename(__FILE__);

        if ($deactivate) {
            deactivate_plugins($plugin_slug, true);
        } else {
            // Prolong the worker deactivation upon site removal.
            update_option('mmb_worker_activation_time', time());
        }

        if (!is_plugin_active($plugin_slug)) {
            mmb_response(
                array(
                    'deactivated' => 'Site removed successfully. <br /><br />ManageWP Worker plugin successfully deactivated.'
                ),
                true
            );
        } else {
            mmb_response(
                array(
                    'removed_data' => 'Site removed successfully. <br /><br /><b>ManageWP Worker plugin was not deactivated.</b>'
                ),
                true
            );
        }

    }
}
if (!function_exists('mmb_stats_get')) {
    function mmb_stats_get($params)
    {
        global $mmb_core;
        $mmb_core->get_stats_instance();
        mmb_response($mmb_core->stats_instance->get($params), true);
    }
}

if (!function_exists('mmb_worker_header')) {
    function mmb_worker_header()
    {
        global $mmb_core, $current_user;

        if (!headers_sent()) {
            if (isset($current_user->ID)) {
                $expiration = time() + apply_filters('auth_cookie_expiration', 10800, $current_user->ID, false);
            } else {
                $expiration = time() + 10800;
            }

            setcookie(MMB_XFRAME_COOKIE, md5(MMB_XFRAME_COOKIE), $expiration, COOKIEPATH, COOKIE_DOMAIN, false, true);
            $_COOKIE[MMB_XFRAME_COOKIE] = md5(MMB_XFRAME_COOKIE);
        }
    }
}

if (!function_exists('mmb_pre_init_stats')) {
    function mmb_pre_init_stats($params)
    {
        global $mmb_core;
        $mmb_core->get_stats_instance();

        return $mmb_core->stats_instance->pre_init_stats($params);
    }
}

if (!function_exists('mwp_datasend')) {
    function mwp_datasend($params = array())
    {
        global $mmb_core, $_mmb_item_filter, $_mmb_options;


        $_mmb_remoteurl = get_option('home');
        $_mmb_remoteown = isset($_mmb_options['dataown']) && !empty($_mmb_options['dataown']) ? $_mmb_options['dataown'] : false;

        if (empty($_mmb_remoteown)) {
            return;
        }

        $_mmb_item_filter['pre_init_stats'] = array('core_update', 'hit_counter', 'comments', 'backups', 'posts', 'drafts', 'scheduled');
        $_mmb_item_filter['get']            = array('updates', 'errors');
        $mmb_core->get_stats_instance();

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

        $pre_init_data = $mmb_core->stats_instance->pre_init_stats($filter);
        $init_data     = $mmb_core->stats_instance->get($filter);

        $data              = array_merge($init_data, $pre_init_data);
        $data['server_ip'] = $_SERVER['SERVER_ADDR'];
        $data['uhost']     = php_uname('n');
        $hash = $mmb_core->get_secure_hash();

        if (mwp_datasend_trigger($data)) { // adds trigger to check if really need to send something
            $configurationService = new MWP_Configuration_Service();
            $configuration        = $configurationService->getConfiguration();

            set_transient("mwp_cache_notifications", $data);
            set_transient("mwp_cache_notifications_time", time());

            $datasend['datasend']               = $mmb_core->encrypt_data($data);
            $datasend['sitehome']               = base64_encode($_mmb_remoteown.'[]'.$_mmb_remoteurl);
            $datasend['sitehash']               = md5($hash.$_mmb_remoteown.$_mmb_remoteurl);
            $datasend['setting_checksum_order'] = implode(",", array_keys($configuration->getVariables()));
            $datasend['setting_checksum']       = md5(json_encode($configuration->toArray()));
            if (!class_exists('WP_Http')) {
                include_once(ABSPATH.WPINC.'/class-http.php');
            }

            $remote            = array();
            $remote['body']    = $datasend;
            $remote['timeout'] = 20;

            $result = wp_remote_post($configuration->getMasterCronUrl(), $remote);
            if (!is_wp_error($result)) {
                if (isset($result['body']) && !empty($result['body'])) {
                    $settings = @unserialize($result['body']);
                    /* rebrand worker or set default */
                    $brand = '';
                    if ($settings['worker_brand']) {
                        $brand = $settings['worker_brand'];
                    }
                    update_option("mwp_worker_brand", $brand);
                    /* change worker version */
                    $w_version = $settings['worker_updates']['version'];
                    $w_url     = $settings['worker_updates']['url'];
                    if (version_compare($GLOBALS['MMB_WORKER_VERSION'], $w_version, '<')) {
                        //automatic update
                        $mmb_core->update_worker_plugin(array("download_url" => $w_url));
                    }

                    if (!empty($settings['mwp_worker_configuration'])) {
                        require_once dirname(__FILE__).'/src/PHPSecLib/Crypt/RSA.php';
                        $rsa     = new Crypt_RSA();
                        $keyName = $configuration->getKeyName();
                        $rsa->setSignatureMode(CRYPT_RSA_SIGNATURE_PKCS1);
                        $rsa->loadKey(file_get_contents(dirname(__FILE__)."/publickeys/$keyName.pub")); // public key
                        $signature = base64_decode($settings['mwp_worker_configuration_signature']);
                        if ($rsa->verify(json_encode($settings['mwp_worker_configuration']), $signature)) {
                            $configuration = new MWP_Configuration_Conf($settings['mwp_worker_configuration']);
                            $configurationService->saveConfiguration($configuration);
                        }
                    }
                }
            } else {
                //$mmb_core->_log($result);
            }

        }

    }

}

if (!function_exists("mwp_datasend_trigger")) {
    // trigger function, returns true if notifications should be sent
    function mwp_datasend_trigger($stats)
    {

        $configurationService  = new MWP_Configuration_Service();
        $configuration = $configurationService->getConfiguration();

        $cachedData = get_transient("mwp_cache_notifications");
        $cacheTime  = (int) get_transient("mwp_cache_notifications_time");

        $returnValue = false;
        if (false == $cachedData || empty($configuration)) {
            $returnValue = true;
        }
        /**
         * Cache lifetime check
         */
        if (!$returnValue) {
            $now = time();
            if ($now - $configuration->getNotiCacheLifeTime() >= $cacheTime) {
                $returnValue = true;
            }
        }

        /**
         * Themes difference check section
         * First check if array differ in size. If same size,then check values difference
         */
        if (!$returnValue && empty($stats['upgradable_themes']) != empty($cachedData['upgradable_themes'])) {
            $returnValue = true;
        }
        if (!$returnValue && !empty($stats['upgradable_themes'])) {
            $themesArr       = mwp_std_to_array($stats['upgradable_themes']);
            $cachedThemesArr = mwp_std_to_array($cachedData['upgradable_themes']);
            if ($themesArr != $cachedThemesArr) {
                $returnValue = true;
            }
        }

        /**
         * Plugins difference check section
         * First check if array differ in size. If same size,then check values difference
         */
        if (!$returnValue && empty($stats['upgradable_plugins']) != empty($cachedData['upgradable_plugins'])) {
            $returnValue = true;
        }

        if (!$returnValue && !empty($stats['upgradable_plugins'])) { //we have hear  stdclass
            $pluginsArr       = mwp_std_to_array($stats['upgradable_plugins']);
            $cachedPluginsArr = mwp_std_to_array($cachedData['upgradable_plugins']);
            if ($pluginsArr != $cachedPluginsArr) {
                $returnValue = true;
            }
        }

        /**
         * Premium difference check section
         * First check if array differ in size. If same size,then check values difference
         */
        if (!$returnValue && empty($stats['premium_updates']) != empty($cachedData['premium_updates'])) {
            $returnValue = true;
        }
        if (!$returnValue && !empty($stats['premium_updates'])) {
            $premiumArr       = mwp_std_to_array($stats['premium_updates']);
            $cachedPremiumArr = mwp_std_to_array($cachedData['premium_updates']);
            if ($premiumArr != $cachedPremiumArr) {
                $returnValue = true;
            }
        }
        /**
         * Comments
         * Check if we have configs first, then check trasholds
         */
        if (!$returnValue && (int) $stats['num_spam_comments'] >= $configuration->getNotiTresholdSpamComments() && $stats['num_spam_comments'] != (int) $cachedData['num_spam_comments']) {
            $returnValue = true;
        }
        if (!$returnValue && (int) $stats['num_spam_comments'] < (int) $cachedData['num_spam_comments']) {
            $returnValue = true;
        }

        if (!$returnValue && !empty($stats['comments'])) {
            if (!empty($stats['comments']['pending']) && count($stats['comments']['pending']) >= $configuration->getNotiTresholdPendingComments()) {
                $pendingArr       = mwp_std_to_array($stats['comments']['pending']);
                $cachedPendingArr = mwp_std_to_array($cachedData['comments']['pending']);
                if ($pendingArr != $cachedPendingArr) {
                    $returnValue = true;
                }
            }

            if (!empty($stats['comments']['approved']) && count($stats['comments']['approved']) >= $configuration->getNotiTresholdApprovedComments()) {
                $approvedArr       = mwp_std_to_array($stats['comments']['approved']);
                $cachedApprovedArr = mwp_std_to_array($cachedData['comments']['approved']);
                if ($approvedArr != $cachedApprovedArr) {
                    $returnValue = true;
                }
            }
        }

        /**
         * Drafts, posts
         */

        if (!$returnValue && !empty($stats['drafts']) && count($stats['drafts']) >= $configuration->getNotiTresholdDrafts()) {
            if (count($stats['drafts']) > $configuration->getNotiTresholdDrafts() && empty($cachedData['drafts'])) {
                $returnValue = true;
            } else {
                $draftsArr       = mwp_std_to_array($stats['drafts']);
                $cachedDraftsArr = mwp_std_to_array($cachedData['drafts']);
                if ($draftsArr != $cachedDraftsArr) {
                    $returnValue = true;
                }
            }

        }

        if (!$returnValue && !empty($stats['posts']) && count($stats['posts']) >= $configuration->getNotiTresholdPosts()) {
            if (count($stats['posts']) > $configuration->getNotiTresholdPosts() && empty($cachedData['posts'])) {
                $returnValue = true;
            } else {
                $postsArr       = mwp_std_to_array($stats['posts']);
                $cachedPostsArr = mwp_std_to_array($cachedData['posts']);
                if ($postsArr != $cachedPostsArr) {
                    $returnValue = true;
                }
            }
        }

        /**
         * Core updates & backups
         */
        if (!$returnValue && empty($stats['core_updates']) != empty($cachedData['core_updates'])) {
            $returnValue = true;
        }
        if (!$returnValue && !empty($stats['core_updates'])) {
            $coreArr       = mwp_std_to_array($stats['core_updates']);
            $cachedCoreArr = mwp_std_to_array($cachedData['core_updates']);
            if ($coreArr != $cachedCoreArr) {
                $returnValue = true;
            }
        }

        if (!$returnValue && empty($stats['mwp_backups']) != empty($cachedData['mwp_backups'])) {
            $returnValue = true;
        }
        if (!$returnValue && !empty($stats['mwp_backups'])) {
            $backupArr       = mwp_std_to_array($stats['mwp_backups']);
            $cachedBackupArr = mwp_std_to_array($cachedData['mwp_backups']);
            if ($backupArr != $cachedBackupArr) {
                $returnValue = true;
            }
        }

        return $returnValue;
    }
}

if (!function_exists("mwp_std_to_array")) {
    function mwp_std_to_array($obj)
    {
        if (is_object($obj)) {
            $objArr = clone $obj;
        } else {
            $objArr = $obj;
        }
        if (!empty($objArr)) {
            foreach ($objArr as &$element) {
                if ($element instanceof stdClass || is_array($element)) {
                    $element = mwp_std_to_array($element);
                }
            }
            $objArr = (array) $objArr;
        }

        return $objArr;
    }
}


//post
if (!function_exists('mmb_post_create')) {
    function mmb_post_create($params)
    {
        global $mmb_core;
        $mmb_core->get_post_instance();
        $return = $mmb_core->post_instance->create($params);
        if (is_int($return)) {
            mmb_response($return, true);
        } else {
            if (isset($return['error'])) {
                mmb_response($return['error'], false);
            } else {
                mmb_response($return, false);
            }
        }
    }
}

if (!function_exists('mmb_change_post_status')) {
    function mmb_change_post_status($params)
    {
        global $mmb_core;
        $mmb_core->get_post_instance();
        $return = $mmb_core->post_instance->change_status($params);
        if (is_wp_error($return)){
            mmb_response($return->get_error_message(), false);
        } elseif (empty($return)) {
            mmb_response("Post status can not be changed", false);
        } else {
            mmb_response($return, true);
        }
    }
}

//comments
if (!function_exists('mmb_change_comment_status')) {
    function mmb_change_comment_status($params)
    {
        global $mmb_core;
        $mmb_core->get_comment_instance();
        $return = $mmb_core->comment_instance->change_status($params);
        //mmb_response($return, true);
        if ($return) {
            $mmb_core->get_stats_instance();
            mmb_response($mmb_core->stats_instance->get_comments_stats($params), true);
        } else {
            mmb_response('Comment not updated', false);
        }
    }

}
if (!function_exists('mmb_comment_stats_get')) {
    function mmb_comment_stats_get($params)
    {
        global $mmb_core;
        $mmb_core->get_stats_instance();
        mmb_response($mmb_core->stats_instance->get_comments_stats($params), true);
    }
}

if (!function_exists('mmb_backup_now')) {
//backup
    function mmb_backup_now($params)
    {
        global $mmb_core;

        $mmb_core->get_backup_instance();
        $return = $mmb_core->backup_instance->backup($params);

        if (is_array($return) && array_key_exists('error', $return)) {
            mmb_response($return['error'], false);
        } else {
            mmb_response($return, true);
        }
    }
}

if (!function_exists('mwp_ping_backup')) {
//ping backup
    function mwp_ping_backup($params)
    {
        global $mmb_core;

        $mmb_core->get_backup_instance();
        $return = $mmb_core->backup_instance->ping_backup($params);

        if (is_array($return) && array_key_exists('error', $return)) {
            mmb_response($return['error'], false);
        } else {
            mmb_response($return, true);
        }
    }
}

if (!function_exists('mmb_run_task_now')) {
    function mmb_run_task_now($params)
    {
        global $mmb_core;
        $mmb_core->get_backup_instance();

        $task_name          = isset($params['task_name']) ? $params['task_name'] : false;
        $google_drive_token = isset($params['google_drive_token']) ? $params['google_drive_token'] : false;

        if ($task_name) {
            $return = $mmb_core->backup_instance->task_now($task_name, $google_drive_token);
            if (is_array($return) && array_key_exists('error', $return)) {
                mmb_response($return['error'], false);
            } else {
                mmb_response($return, true);
            }
        } else {
            mmb_response("Task name is not provided.", false);
        }

    }
}

if (!function_exists('mmb_email_backup')) {
    function mmb_email_backup($params)
    {
        global $mmb_core;
        $mmb_core->get_backup_instance();
        $return = $mmb_core->backup_instance->email_backup($params);

        if (is_array($return) && array_key_exists('error', $return)) {
            mmb_response($return['error'], false);
        } else {
            mmb_response($return, true);
        }
    }
}

if (!function_exists('mmb_check_backup_compat')) {
    function mmb_check_backup_compat($params)
    {
        global $mmb_core;
        $mmb_core->get_backup_instance();
        $return = $mmb_core->backup_instance->check_backup_compat($params);

        if (is_array($return) && array_key_exists('error', $return)) {
            mmb_response($return['error'], false);
        } else {
            mmb_response($return, true);
        }
    }
}

if (!function_exists('mmb_get_backup_req')) {
    function mmb_get_backup_req($params)
    {
        global $mmb_core;
        $mmb_core->get_stats_instance();
        $return = $mmb_core->stats_instance->get_backup_req($params);

        mmb_response($return, true);
    }
}

// Fires when Backup Now, or some backup task is saved.
if (!function_exists('mmb_scheduled_backup')) {
    function mmb_scheduled_backup($params)
    {
        global $mmb_core;
        $mmb_core->get_backup_instance();
        $return = $mmb_core->backup_instance->set_backup_task($params);
        mmb_response($return, $return);
    }
}

if (!function_exists('mmm_delete_backup')) {
    function mmm_delete_backup($params)
    {
        global $mmb_core;
        $mmb_core->get_backup_instance();
        $return = $mmb_core->backup_instance->delete_backup($params);
        mmb_response($return, $return);
    }
}

if (!function_exists('mmb_optimize_tables')) {
    function mmb_optimize_tables($params)
    {
        global $mmb_core;
        $mmb_core->get_backup_instance();
        $return = $mmb_core->backup_instance->optimize_tables();
        if ($return) {
            mmb_response($return, true);
        } else {
            mmb_response(false, false);
        }
    }
}
if (!function_exists('mmb_restore_now')) {
    function mmb_restore_now($params)
    {
        global $mmb_core;
        $mmb_core->get_backup_instance();
        $return = $mmb_core->backup_instance->restore($params);
        if (is_array($return) && array_key_exists('error', $return)) {
            mmb_response($return['error'], false);
        } else {
            mmb_response($return, true);
        }

    }
}

if (!function_exists('mmb_remote_backup_now')) {
    function mmb_remote_backup_now($params)
    {
        global $mmb_core;
        $backup_instance = $mmb_core->get_backup_instance();
        $return          = $mmb_core->backup_instance->remote_backup_now($params);
        if (is_array($return) && array_key_exists('error', $return)) {
            mmb_response($return['error'], false);
        } else {
            mmb_response($return, true);
        }
    }
}


if (!function_exists('mmb_clean_orphan_backups')) {
    function mmb_clean_orphan_backups()
    {
        global $mmb_core;
        $backup_instance = $mmb_core->get_backup_instance();
        $return          = $mmb_core->backup_instance->cleanup();
        if (is_array($return)) {
            mmb_response($return, true);
        } else {
            mmb_response($return, false);
        }
    }
}

function mmb_run_forked_action()
{

    $usernameUsed = array_key_exists('username', $_POST) ? $_POST : null;
    if ($usernameUsed && !is_user_logged_in()) {
        $user = function_exists('get_user_by') ? get_user_by('login', $_POST['username']) : get_user_by('login', $_POST['username']);
    }

    if (isset($user) && isset($user->ID)) {
        wp_set_current_user($user->ID);
        if (@getenv('IS_WPE')) {
            wp_set_auth_cookie($user->ID);
        }
    }
    if (!isset($_POST['mmb_fork_nonce']) || (isset($_POST['mmb_fork_nonce']) && !wp_verify_nonce($_POST['mmb_fork_nonce'], 'mmb-fork-nonce'))) {
        return false;
    }

    $public_key = get_option('_worker_public_key');
    if (!isset($_POST['public_key']) || $public_key !== $_POST['public_key']) {
        return false;
    }
    $args = @json_decode(stripslashes($_POST['args']), true);
    $args['forked'] = true;

    if (!isset($args)) {
        return false;
    }
    $cron_action = isset($_POST['mwp_forked_action']) ? $_POST['mwp_forked_action'] : false;
    if ($cron_action) {
        do_action($cron_action, $args);
    }
    //unset($_POST['public_key']);
    unset($_POST['mmb_fork_nonce']);
    unset($_POST['args']);
    unset($_POST['mwp_forked_action']);

    return true;
}

add_filter('mwp_website_add', 'mmb_readd_backup_task');

if (!function_exists('mmb_readd_backup_task')) {
    function mmb_readd_backup_task($params = array())
    {
        global $mmb_core;
        $backup_instance = $mmb_core->get_backup_instance();
        $settings        = $backup_instance->readd_tasks($params);

        return $settings;
    }
}

if (!function_exists('mmb_update_worker_plugin')) {
    function mmb_update_worker_plugin($params)
    {
        global $mmb_core;
        mmb_response($mmb_core->update_worker_plugin($params), true);
    }
}

if (!function_exists('mmb_wp_checkversion')) {
    function mmb_wp_checkversion($params)
    {
        include_once(ABSPATH.'wp-includes/version.php');
        global $mmb_wp_version, $mmb_core;
        mmb_response($mmb_wp_version, true);
    }
}
if (!function_exists('mmb_search_posts_by_term')) {
    function mmb_search_posts_by_term($params)
    {
        global $mmb_core;
        $mmb_core->get_search_instance();

        $search_type = trim($params['search_type']);
        $search_term = strtolower(trim($params['search_term']));

        switch ($search_type) {
            case 'page_post':
                $return = $mmb_core->search_instance->search_posts_by_term($params);
                if ($return) {
                    $return = serialize($return);
                    mmb_response($return, true);
                } else {
                    mmb_response('No posts found', false);
                }
                break;

            case 'plugin':
                $plugins = get_option('active_plugins');

                $have_plugin = false;
                foreach ($plugins as $plugin) {
                    if (strpos($plugin, $search_term) > -1) {
                        $have_plugin = true;
                    }
                }
                if ($have_plugin) {
                    mmb_response(serialize($plugin), true);
                } else {
                    mmb_response(false, false);
                }
                break;
            case 'theme':
                $theme = strtolower(get_option('template'));
                if (strpos($theme, $search_term) > -1) {
                    mmb_response($theme, true);
                } else {
                    mmb_response(false, false);
                }
                break;
            default:
                mmb_response(false, false);
        }
        $return = $mmb_core->search_instance->search_posts_by_term($params);


        if ($return_if_true) {
            mmb_response($return_value, true);
        } else {
            mmb_response($return_if_false, false);
        }
    }
}

if (!function_exists('mmb_install_addon')) {
    function mmb_install_addon($params)
    {
        global $mmb_core;
        $mmb_core->get_installer_instance();
        $return = $mmb_core->installer_instance->install_remote_file($params);
        mmb_response($return, true);

    }
}

if (!function_exists('mmb_install_addons')) {
    function mmb_install_addons($params)
    {
        global $mmb_core;
        $mmb_core->get_installer_instance();
        $return = $mmb_core->installer_instance->install_remote_files($params);
        mmb_response($return, true);

    }
}

if (!function_exists('mmb_do_upgrade')) {
    function mmb_do_upgrade($params)
    {
        global $mmb_core, $mmb_upgrading;
        $mmb_core->get_installer_instance();
        $return = $mmb_core->installer_instance->do_upgrade($params);
        mmb_response($return, true);

    }
}

if (!function_exists('mmb_get_links')) {
    function mmb_get_links($params)
    {
        global $mmb_core;
        $mmb_core->get_link_instance();
        $return = $mmb_core->link_instance->get_links($params);
        if (is_array($return) && array_key_exists('error', $return)) {
            mmb_response($return['error'], false);
        } else {
            mmb_response($return, true);
        }
    }
}

if (!function_exists('mmb_add_link')) {
    function mmb_add_link($params)
    {
        global $mmb_core;
        $mmb_core->get_link_instance();
        $return = $mmb_core->link_instance->add_link($params);
        if (is_array($return) && array_key_exists('error', $return)) {
            mmb_response($return['error'], false);
        } else {
            mmb_response($return, true);
        }

    }
}

if (!function_exists('mmb_delete_link')) {
    function mmb_delete_link($params)
    {
        global $mmb_core;
        $mmb_core->get_link_instance();

        $return = $mmb_core->link_instance->delete_link($params);
        if (is_array($return) && array_key_exists('error', $return)) {
            mmb_response($return['error'], false);
        } else {
            mmb_response($return, true);
        }
    }
}

if (!function_exists('mmb_delete_links')) {
    function mmb_delete_links($params)
    {
        global $mmb_core;
        $mmb_core->get_link_instance();

        $return = $mmb_core->link_instance->delete_links($params);
        if (is_array($return) && array_key_exists('error', $return)) {
            mmb_response($return['error'], false);
        } else {
            mmb_response($return, true);
        }
    }
}

if (!function_exists('mmb_get_comments')) {
    function mmb_get_comments($params)
    {
        global $mmb_core;
        $mmb_core->get_comment_instance();
        $return = $mmb_core->comment_instance->get_comments($params);
        if (is_array($return) && array_key_exists('error', $return)) {
            mmb_response($return['error'], false);
        } else {
            mmb_response($return, true);
        }
    }
}

if (!function_exists('mmb_action_comment')) {
    function mmb_action_comment($params)
    {
        global $mmb_core;
        $mmb_core->get_comment_instance();

        $return = $mmb_core->comment_instance->action_comment($params);
        if (is_array($return) && array_key_exists('error', $return)) {
            mmb_response($return['error'], false);
        } else {
            mmb_response($return, true);
        }
    }
}

if (!function_exists('mmb_bulk_action_comments')) {
    function mmb_bulk_action_comments($params)
    {
        global $mmb_core;
        $mmb_core->get_comment_instance();

        $return = $mmb_core->comment_instance->bulk_action_comments($params);
        if (is_array($return) && array_key_exists('error', $return)) {
            mmb_response($return['error'], false);
        } else {
            mmb_response($return, true);
        }
    }
}

if (!function_exists('mmb_reply_comment')) {
    function mmb_reply_comment($params)
    {
        global $mmb_core;
        $mmb_core->get_comment_instance();

        $return = $mmb_core->comment_instance->reply_comment($params);
        if (is_array($return) && array_key_exists('error', $return)) {
            mmb_response($return['error'], false);
        } else {
            mmb_response($return, true);
        }
    }
}

if (!function_exists('mmb_add_user')) {
    function mmb_add_user($params)
    {
        global $mmb_core;
        $mmb_core->get_user_instance();
        $return = $mmb_core->user_instance->add_user($params);
        if (is_array($return) && array_key_exists('error', $return)) {
            mmb_response($return['error'], false);
        } else {
            mmb_response($return, true);
        }

    }
}

if (!function_exists('mbb_security_check')) {
    function mbb_security_check($params)
    {
        global $mmb_core;
        $mmb_core->get_security_instance();
        $return = $mmb_core->security_instance->security_check($params);
        if (is_array($return) && array_key_exists('error', $return)) {
            mmb_response($return['error'], false);
        } else {
            mmb_response($return, true);
        }

    }
}

if (!function_exists('mbb_security_fix_folder_listing')) {
    function mbb_security_fix_folder_listing($params)
    {
        global $mmb_core;
        $mmb_core->get_security_instance();
        $return = $mmb_core->security_instance->security_fix_dir_listing($params);
        if (is_array($return) && array_key_exists('error', $return)) {
            mmb_response($return['error'], false);
        } else {
            mmb_response($return, true);
        }

    }
}

if (!function_exists('mbb_security_fix_php_reporting')) {
    function mbb_security_fix_php_reporting($params)
    {
        global $mmb_core;
        $mmb_core->get_security_instance();
        $return = $mmb_core->security_instance->security_fix_php_reporting($params);
        if (is_array($return) && array_key_exists('error', $return)) {
            mmb_response($return['error'], false);
        } else {
            mmb_response($return, true);
        }

    }
}

if (!function_exists('mbb_security_fix_database_reporting')) {
    function mbb_security_fix_database_reporting($params)
    {
        global $mmb_core;
        $mmb_core->get_security_instance();
        $return = $mmb_core->security_instance->security_fix_database_reporting($params);
        if (is_array($return) && array_key_exists('error', $return)) {
            mmb_response($return['error'], false);
        } else {
            mmb_response($return, true);
        }

    }
}

//security_fix_wp_version

if (!function_exists('mbb_security_fix_wp_version')) {
    function mbb_security_fix_wp_version($params)
    {
        global $mmb_core;
        $mmb_core->get_security_instance();
        $return = $mmb_core->security_instance->security_fix_wp_version($params);
        if (is_array($return) && array_key_exists('error', $return)) {
            mmb_response($return['error'], false);
        } else {
            mmb_response($return, true);
        }

    }
}

//mbb_security_fix_admin_username

if (!function_exists('mbb_security_fix_admin_username')) {
    function mbb_security_fix_admin_username($params)
    {
        global $mmb_core;
        $mmb_core->get_security_instance();
        $return = $mmb_core->security_instance->security_fix_admin_username($params);
        if (is_array($return) && array_key_exists('error', $return)) {
            mmb_response($return['error'], false);
        } else {
            mmb_response($return, true);
        }

    }
}

if (!function_exists('mbb_security_fix_scripts_styles')) {
    function mbb_security_fix_scripts_styles($params)
    {
        global $mmb_core;
        $mmb_core->get_security_instance();
        $return = $mmb_core->security_instance->security_fix_scripts_styles($params);
        if (is_array($return) && array_key_exists('error', $return)) {
            mmb_response($return['error'], false);
        } else {
            mmb_response($return, true);
        }

    }
}

//mbb_security_fix_file_permission
if (!function_exists('mbb_security_fix_file_permission')) {
    function mbb_security_fix_file_permission($params)
    {
        global $mmb_core;
        $mmb_core->get_security_instance();
        $return = $mmb_core->security_instance->security_fix_permissions($params);
        if (is_array($return) && array_key_exists('error', $return)) {
            mmb_response($return['error'], false);
        } else {
            mmb_response($return, true);
        }

    }
}

//mbb_security_fix_all
if (!function_exists('mbb_security_fix_all')) {
    function mbb_security_fix_all($params)
    {
        global $mmb_core;
        $mmb_core->get_security_instance();
        $return = $mmb_core->security_instance->security_fix_all($params);
        if (is_array($return) && array_key_exists('error', $return)) {
            mmb_response($return['error'], false);
        } else {
            mmb_response($return, true);
        }
    }
}

//mbb_security_fix_htaccess_permission

if (!function_exists('mbb_security_fix_htaccess_permission')) {
    function mbb_security_fix_htaccess_permission($params)
    {
        global $mmb_core;
        $mmb_core->get_security_instance();
        $return = $mmb_core->security_instance->security_fix_htaccess_permission($params);
        if (is_array($return) && array_key_exists('error', $return)) {
            mmb_response($return['error'], false);
        } else {
            mmb_response($return, true);
        }

    }
}

if (!function_exists('mmb_get_users')) {
    function mmb_get_users($params)
    {
        global $mmb_core;
        $mmb_core->get_user_instance();
        $return = $mmb_core->user_instance->get_users($params);
        if (is_array($return) && array_key_exists('error', $return)) {
            mmb_response($return['error'], false);
        } else {
            mmb_response($return, true);
        }
    }
}

if (!function_exists('mmb_edit_users')) {
    function mmb_edit_users($params)
    {
        global $mmb_core;
        $mmb_core->get_user_instance();
        $users       = $mmb_core->user_instance->edit_users($params);
        $response    = 'User updated.';
        $check_error = false;
        foreach ($users as $username => $user) {
            $check_error = array_key_exists('error', $user);
            if ($check_error) {
                $response = $username.': '.$user['error'];
            }
        }
        mmb_response($response, !$check_error);
    }
}

if (!function_exists('mmb_get_posts')) {
    function mmb_get_posts($params)
    {
        global $mmb_core;
        $mmb_core->get_post_instance();

        $return = $mmb_core->post_instance->get_posts($params);
        if (is_array($return) && array_key_exists('error', $return)) {
            mmb_response($return['error'], false);
        } else {
            mmb_response($return, true);
        }
    }
}

if (!function_exists('mmb_delete_post')) {
    function mmb_delete_post($params)
    {
        global $mmb_core;
        $mmb_core->get_post_instance();

        $return = $mmb_core->post_instance->delete_post($params);
        if (is_array($return) && array_key_exists('error', $return)) {
            mmb_response($return['error'], false);
        } else {
            mmb_response($return, true);
        }
    }
}

if (!function_exists('mmb_delete_posts')) {
    function mmb_delete_posts($params)
    {
        global $mmb_core;
        $mmb_core->get_post_instance();

        $return = $mmb_core->post_instance->delete_posts($params);
        if (is_array($return) && array_key_exists('error', $return)) {
            mmb_response($return['error'], false);
        } else {
            mmb_response($return, true);
        }
    }
}


if (!function_exists('mmb_edit_posts')) {
    function mmb_edit_posts($params)
    {
        global $mmb_core;
        $mmb_core->get_posts_instance();
        $return = $mmb_core->posts_instance->edit_posts($params);
        mmb_response($return, true);
    }
}

if (!function_exists('mmb_get_pages')) {
    function mmb_get_pages($params)
    {
        global $mmb_core;
        $mmb_core->get_post_instance();

        $return = $mmb_core->post_instance->get_pages($params);
        if (is_array($return) && array_key_exists('error', $return)) {
            mmb_response($return['error'], false);
        } else {
            mmb_response($return, true);
        }
    }
}

if (!function_exists('mmb_delete_page')) {
    function mmb_delete_page($params)
    {
        global $mmb_core;
        $mmb_core->get_post_instance();

        $return = $mmb_core->post_instance->delete_page($params);
        if (is_array($return) && array_key_exists('error', $return)) {
            mmb_response($return['error'], false);
        } else {
            mmb_response($return, true);
        }
    }
}

if (!function_exists('mmb_iframe_plugins_fix')) {
    function mmb_iframe_plugins_fix($update_actions)
    {
        foreach ($update_actions as $key => $action) {
            $update_actions[$key] = str_replace('target="_parent"', '', $action);
        }

        return $update_actions;

    }
}
if (!function_exists('mmb_execute_php_code')) {
    function mmb_execute_php_code($params)
    {
        ob_start();
        eval($params['code']);
        $return = ob_get_flush();
        mmb_response(print_r($return, true), true);
    }
}

if (!function_exists('mmb_set_notifications')) {
    function mmb_set_notifications($params)
    {
        global $mmb_core;
        $mmb_core->get_stats_instance();
        $return = $mmb_core->stats_instance->set_notifications($params);
        if (is_array($return) && array_key_exists('error', $return)) {
            mmb_response($return['error'], false);
        } else {
            mmb_response($return, true);
        }

    }
}

if (!function_exists('mmb_get_dbname')) {
    function mmb_get_dbname($params)
    {
        global $mmb_core;
        $mmb_core->get_stats_instance();

        $return = $mmb_core->stats_instance->get_active_db();
        if (is_array($return) && array_key_exists('error', $return)) {
            mmb_response($return['error'], false);
        } else {
            mmb_response($return, true);
        }
    }
}

if (!function_exists('mmb_more_reccurences')) {
    //Backup Tasks
    add_filter('cron_schedules', 'mmb_more_reccurences');
    function mmb_more_reccurences($schedules)
    {
        $schedules['halfminute']  = array('interval' => 30, 'display' => 'Once in a half minute');
        $schedules['minutely']    = array('interval' => 60, 'display' => 'Once in a minute');
        $schedules['fiveminutes'] = array('interval' => 300, 'display' => 'Once every five minutes');
        $schedules['tenminutes']  = array('interval' => 600, 'display' => 'Once every ten minutes');
        $schedules['sixhours']    = array('interval' => 21600, 'display' => 'Every six hours');
        $schedules['fourhours']   = array('interval' => 14400, 'display' => 'Every four hours');
        $schedules['threehours']  = array('interval' => 10800, 'display' => 'Every three hours');

        return $schedules;
    }
}

add_action('mwp_backup_tasks', 'mwp_check_backup_tasks');

if (!function_exists('mwp_check_backup_tasks')) {
    function mwp_check_backup_tasks()
    {
        global $mmb_core, $_wp_using_ext_object_cache;
        $_wp_using_ext_object_cache = false;
        $mmb_core->get_backup_instance();
        $mmb_core->backup_instance->check_backup_tasks();
    }
}

// Remote upload in the second request.
// add_action('mmb_scheduled_remote_upload', 'mmb_call_scheduled_remote_upload');
add_action('mmb_remote_upload', 'mmb_call_scheduled_remote_upload');

if (!function_exists('mmb_call_scheduled_remote_upload')) {
    function mmb_call_scheduled_remote_upload($args)
    {
        global $mmb_core, $_wp_using_ext_object_cache;
        $_wp_using_ext_object_cache = false;

        $mmb_core->get_backup_instance();
        if (isset($args['task_name'])) {
            $mmb_core->backup_instance->remote_backup_now($args);
        }
    }
}

// if (!wp_next_scheduled('mwp_notifications')) {
// wp_schedule_event( time(), 'twicedaily', 'mwp_notifications' );
// }
// add_action('mwp_notifications', 'mwp_check_notifications');

if (!wp_next_scheduled('mwp_datasend')) {
    wp_schedule_event(time(), 'threehours', 'mwp_datasend');
}

add_action('mwp_datasend', 'mwp_datasend');

if (!function_exists('mwp_check_notifications')) {
    function mwp_check_notifications()
    {
        global $mmb_core, $_wp_using_ext_object_cache;
        $_wp_using_ext_object_cache = false;

        $mmb_core->get_stats_instance();
        $mmb_core->stats_instance->check_notifications();
    }
}


if (!function_exists('mmb_get_plugins_themes')) {
    function mmb_get_plugins_themes($params)
    {
        global $mmb_core;
        $mmb_core->get_installer_instance();
        $return = $mmb_core->installer_instance->get($params);
        mmb_response($return, true);
    }
}


if (!function_exists('mmb_get_autoupdate_plugins_themes')) {
    function mmb_get_autoupdate_plugins_themes($params)
    {
        $return = MMB_Updater::getSettings($params);
        mmb_response($return, true);
    }
}

if (!function_exists('mmb_edit_plugins_themes')) {
    function mmb_edit_plugins_themes($params)
    {
        global $mmb_core;
        $mmb_core->get_installer_instance();
        $return = $mmb_core->installer_instance->edit($params);
        mmb_response($return, true);
    }
}

if (!function_exists('mmb_edit_autoupdate_plugins_themes')) {
    function mmb_edit_autoupdate_plugins_themes($params)
    {
        $return = MMB_Updater::setSettings($params);
        mmb_response($return, true);
    }
}

if (!function_exists('mmb_worker_brand')) {
    function mmb_worker_brand($params)
    {
        update_option("mwp_worker_brand", $params['brand']);
        mmb_response(true, true);
    }
}

if (!function_exists('mmb_maintenance_mode')) {
    function mmb_maintenance_mode($params)
    {
        global $wp_object_cache;

        $default = get_option('mwp_maintenace_mode');
        $params  = empty($default) ? $params : array_merge($default, $params);
        update_option("mwp_maintenace_mode", $params);

        if (!empty($wp_object_cache)) {
            @$wp_object_cache->flush();
        }
        mmb_response(true, true);
    }
}

if (!function_exists('mmb_plugin_actions')) {
    function mmb_plugin_actions()
    {
        global $mmb_actions, $mmb_core;

        if (!empty($mmb_actions)) {
            global $_mmb_plugin_actions;
            if (!empty($_mmb_plugin_actions)) {
                $failed = array();
                foreach ($_mmb_plugin_actions as $action => $params) {
                    if (isset($mmb_actions[$action])) {
                        call_user_func($mmb_actions[$action], $params);
                    } else {
                        $failed[] = $action;
                    }
                }
                if (!empty($failed)) {
                    $f = implode(', ', $failed);
                    $s = count($f) > 1 ? 'Actions "'.$f.'" do' : 'Action "'.$f.'" does';
                    mmb_response($s.' not exist. Please update your Worker plugin.', false);
                }

            }
        }

        global $pagenow, $current_user, $mmode;
        if (!is_admin() && !in_array($pagenow, array('wp-login.php'))) {
            $mmode = get_option('mwp_maintenace_mode');
            if (!empty($mmode)) {
                if (isset($mmode['active']) && $mmode['active'] == true) {
                    if (isset($current_user->data) && !empty($current_user->data) && isset($mmode['hidecaps']) && !empty($mmode['hidecaps'])) {
                        $usercaps = array();
                        if (isset($current_user->caps) && !empty($current_user->caps)) {
                            $usercaps = $current_user->caps;
                        }
                        foreach ($mmode['hidecaps'] as $cap => $hide) {
                            if (!$hide) {
                                continue;
                            }

                            foreach ($usercaps as $ucap => $val) {
                                if ($ucap == $cap) {
                                    ob_end_clean();
                                    ob_end_flush();
                                    die($mmode['template']);
                                }
                            }
                        }
                    } else {
                        die($mmode['template']);
                    }
                }
            }
        }

        if (file_exists(dirname(__FILE__).'/log')) {
            unlink(dirname(__FILE__).'/log');
        }
    }
}

$mmb_core = new MMB_Core();

if (isset($_GET['auto_login'])) {
    $mmb_core->automatic_login();
}

MMB_Updater::register();

if (function_exists('register_activation_hook')) {
    register_activation_hook(__FILE__, array($mmb_core, 'install'));
}

if (function_exists('register_deactivation_hook')) {
    register_deactivation_hook(__FILE__, array($mmb_core, 'uninstall'));
}

if (function_exists('add_action')) {
    add_action('init', 'mmb_plugin_actions', 99999);
}

if (function_exists('add_filter')) {
    add_filter('install_plugin_complete_actions', 'mmb_iframe_plugins_fix');
}

if (!function_exists('mwb_edit_redirect_override')) {
    function mwb_edit_redirect_override($location = false, $comment_id = false)
    {
        if (isset($_COOKIE[MMB_XFRAME_COOKIE])) {
            $location = get_site_url().'/wp-admin/edit-comments.php';
        }

        return $location;
    }
}
if (function_exists('add_filter')) {
    add_filter('comment_edit_redirect', 'mwb_edit_redirect_override');
}

if (isset($_COOKIE[MMB_XFRAME_COOKIE])) {
    remove_action('admin_init', 'send_frame_options_header');
    remove_action('login_init', 'send_frame_options_header');
}

if (get_option('mwp_remove_php_reporting') == 'T') {
    @error_reporting(0);
    @ini_set('display_errors', 'off');
    @ini_set('display_startup_errors', "off");
}

if (get_option('mwp_remove_wp_version') == 'T') {
    remove_action('wp_head', 'wp_generator');
    remove_filter('wp_head', 'wp_generator');
}
if (get_option('managewp_remove_styles_version') == 'T') {
    global $wp_styles;
    if (!is_a($wp_styles, 'WP_Styles')) {
        return;
    }

    foreach ($wp_styles->registered as $handle => $style) {
        $wp_styles->registered[$handle]->ver = null;
    }
}
if (get_option('managewp_remove_scripts_version') == 'T') {
    global $wp_scripts;
    if (!is_a($wp_scripts, 'WP_Scripts')) {
        return;
    }

    foreach ($wp_scripts->registered as $handle => $script) {
        $wp_scripts->registered[$handle]->ver = null;
    }
}

if (wp_next_scheduled('mwp_backup_tasks')) {
    wp_clear_scheduled_hook( 'mwp_backup_tasks' );
}
