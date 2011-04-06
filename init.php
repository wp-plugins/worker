<?php
/* 
Plugin Name: ManageWP - Worker
Plugin URI: http://managewp.com/
Description: Manage all your blogs from one dashboard
Author: Prelovac Media
Version: 3.8.2
Author URI: http://www.prelovac.com
*/

// PHP warnings can break our XML stuffs
if ($_SERVER['REMOTE_ADDR'] != '127.0.0.1') {
    error_reporting(E_ERROR);
}

define('MMB_WORKER_VERSION', '3.8.2');

global $wpdb, $mmb_plugin_dir, $mmb_plugin_url;

$mmb_plugin_dir = WP_PLUGIN_DIR . '/' . basename(dirname(__FILE__));
$mmb_plugin_url = WP_PLUGIN_URL . '/' . basename(dirname(__FILE__));

require_once(ABSPATH . 'wp-includes/class-IXR.php');
require_once("$mmb_plugin_dir/helper.class.php");
require_once("$mmb_plugin_dir/core.class.php");
require_once("$mmb_plugin_dir/plugin.class.php");
require_once("$mmb_plugin_dir/theme.class.php");
require_once("$mmb_plugin_dir/wp.class.php");
require_once("$mmb_plugin_dir/post.class.php");
require_once("$mmb_plugin_dir/stats.class.php");
require_once("$mmb_plugin_dir/user.class.php");
require_once("$mmb_plugin_dir/backup.class.php");

$mmb_core = new MMB_Core();
add_action('init', '_mmb_parse_request');

if (function_exists('register_activation_hook'))
    register_activation_hook(__FILE__, array($mmb_core, 'install'));

if (function_exists('register_deactivation_hook'))
    register_deactivation_hook(__FILE__, array($mmb_core, 'uninstall'));

function _mmb_parse_request()
{
    if (!isset($HTTP_RAW_POST_DATA)) {
        $HTTP_RAW_POST_DATA = file_get_contents('php://input');
    }
    ob_start();
		
    global $mmb_core;
    $data = base64_decode($HTTP_RAW_POST_DATA);
    $num  = extract(unserialize($data));
    
    if ($action) {
        if (!$mmb_core->_check_if_user_exists($params['username']))
	      mmb_response('Username <b>'.$params['username'].'</b> does not have administrator capabilities. Enter the correct username in the site options.', false);
        
		if ($action == 'add_site') {
			mmb_add_site($params);
			mmb_response('You should never see this.', false);
        }
        
        $auth = $mmb_core->_authenticate_message($action . $id, $signature, $id);
		if ($auth === true) {
            $mmb_actions = array(
                'remove_site' => 'mmb_remove_site',
                'get_stats' => 'mmb_stats_get',
                'backup' => 'mmb_backup_now',
                'restore' => 'mmb_restore_now',
                'optimize_tables' => 'mmb_optimize_tables',
                'check_wp_version' => 'mmb_wp_checkversion',
                'create_post' => 'mmb_post_create',
                'upgrade_plugins' => 'mmb_upgrade_plugins',
                'wp_upgrade' => 'mmb_upgrade_wp',
                'upgrade_themes' => 'mmb_themes_upgrade',
                'upload_plugin_by_url' => 'mmb_plugin_upload_by_url',
                'upload_theme_by_url' => 'mmb_theme_upload_by_url',
                'update_worker' => 'mmb_update_worker_plugin'
            );
            if (array_key_exists($action, $mmb_actions) && function_exists($mmb_actions[$action]))
                call_user_func($mmb_actions[$action], $params);
            else
                mmb_response('Action "' . $action . '" does not exist.', false);
        } else if(array_key_exists('openssl_activated', $auth)){
			mmb_response($auth, true);
		} else {
            mmb_response($auth['error'], false);
        }
    }
  
      
    ob_end_clean();
}

/* Main response function */

function mmb_response($response = false, $success = true)
{
    $return = array();
    
    if (empty($response))
        $return['error'] = 'Empty response';
    else if ($success)
        $return['success'] = $response;
    else
        $return['error'] = $response;
    
    header('Content-Type: text/plain');
    exit(PHP_EOL.base64_encode(serialize($return)));
}

function mmb_add_site($params)
{
    global $mmb_core;
    
    $num = extract($params);
	
    if ($num) {
		if ( !get_option('_action_message_id') && !get_option('_worker_public_key')) {
			$public_key =  base64_decode($public_key) ;

				if ( function_exists('openssl_verify') ) {
					$verify = openssl_verify($action . $id, base64_decode($signature), $public_key);
					if ($verify == 1) {
						$mmb_core->_set_master_public_key($public_key);                       
						$mmb_core->_set_worker_message_id($id);

						mmb_response($mmb_core->get_stats_instance()->get_initial_stats(), true);
					} else if ($verify == 0) {
						mmb_response('Invalid message signature. Please contact us if you see this message often.', false);
					} else {
						mmb_response('Command not successful. Please try again.', false);
					}
				} else{
					if ( !get_option('_worker_nossl_key')) {
					        srand();
						$random_key = md5(base64_encode($public_key) . rand(0, getrandmax()));
						
						$mmb_core->_set_random_signature($random_key);
						$mmb_core->_set_worker_message_id($id);
						$mmb_core->_set_master_public_key($public_key);
						mmb_response($mmb_core->get_stats_instance()->get_initial_stats(), true);
					}
					else  mmb_response('Please deactivate & activate ManageWP Worker plugin on your site, then re-add the site to your dashboard.', false);
			}
        } else {
            mmb_response('Please deactivate & activate ManageWP Worker plugin on your site and re-add the site to your dashboard.', false);
        }
    } else {
        mmb_response('Invalid parameters received. Please try again.', false);
    }
}

function mmb_remove_site($params)
{	
	extract($params);
    global $mmb_core;
    $mmb_core->uninstall();
	
	include_once(ABSPATH . 'wp-admin/includes/plugin.php');
	$plugin_slug = basename(dirname(__FILE__)).'/'.basename(__FILE__);
	
	if($deactivate){
		deactivate_plugins($plugin_slug, true);
	}
	
	if(!is_plugin_active($plugin_slug))
		mmb_response(array('deactivated' => 'Site removed successfully. <br /><br />ManageWP Worker plugin successfully deactivated.'), true);
	else 
		mmb_response(array('removed_data' => 'Site removed successfully. <br /><br /><b>ManageWP Worker plugin was not deactivated.</b>'), true);
    
}


function mmb_stats_get($params)
{	
	global $mmb_core;
	mmb_response($mmb_core->get_stats_instance()->get($params), true);
}


//Plugins

function mmb_upgrade_plugins($params)
{
    global $mmb_core;
    mmb_response($mmb_core->get_plugin_instance()->upgrade_all($params), true);
}

function mmb_plugin_upload_by_url($params)
{
    global $mmb_core;
    $return = $mmb_core->get_plugin_instance()->upload_by_url($params);
   
    
    mmb_response($return['message'],$return['bool'] );
    
}

//Themes
function mmb_theme_upload_by_url($params)
{
    global $mmb_core;
    $return = $mmb_core->get_theme_instance()->upload_theme_by_url($params);
    mmb_response($return['message'],$return['bool'] );
}

function mmb_themes_upgrade($params)
{
    global $mmb_core;
    mmb_response($mmb_core->get_theme_instance()->upgrade_all($params), true);
}


//wp

function mmb_upgrade_wp($params)
{
    global $mmb_core;
	mmb_response($mmb_core->get_wp_instance()->upgrade());
}

//post
function mmb_post_create($params)
{
    global $mmb_core;
    $return = $mmb_core->get_post_instance()->create($params);
    if (is_int($return))
        mmb_response($return, true);
    else
        mmb_response($return, false);
}

//backup
function mmb_backup_now($params)
{
    global $mmb_core;
    $return = $mmb_core->get_backup_instance()->backup($params);
    
    if (is_array($return) && array_key_exists('error', $return))
        mmb_response($return['error'], false);
    else {
        $mmb_core->_log($return);
        mmb_response($return, true);
    }
    
}

function mmb_optimize_tables($params)
{
    global $mmb_core;
    $return = $mmb_core->get_backup_instance()->optimize_tables();
    if ($return)
        mmb_response($return, true);
    else
        mmb_response(false, false);
}

function mmb_restore_now($params)
{
    global $mmb_core;
    $return = $mmb_core->get_backup_instance()->restore($params);
    if (is_array($return) && array_key_exists('error', $return))
        mmb_response($return['error'], false);
    else
        mmb_response($return, true);
    
}

function mmb_update_worker_plugin($params)
{
    global $mmb_core;
    mmb_response($mmb_core->update_worker_plugin($params), true);
}
?>
