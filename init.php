<?php
/* 
Plugin Name: ManageWP - Worker
Plugin URI: http://managewp.com/
Description: Manage all your blogs from one dashboard. Visit <a href="http://managewp.com">ManageWP.com</a> to sign up.
Author: Prelovac Media
Version: 3.9.4
Author URI: http://www.prelovac.com
*/

/*************************************************************
 * 
 * init.php
 * 
 * Initialize the communication with master
 * 
 * 
 * Copyright (c) 2011 Prelovac Media
 * www.prelovac.com
 **************************************************************/


define('MMB_WORKER_VERSION', '3.9.4');

global $wpdb, $mmb_plugin_dir, $mmb_plugin_url;

if (version_compare(PHP_VERSION, '5.0.0', '<')) // min version 5 supported
    exit("<p>ManageWP Worker plugin requires PHP 5 or higher.</p>");


	
global $wp_version;
				
$mmb_wp_version = $wp_version;
$mmb_plugin_dir = WP_PLUGIN_DIR . '/' . basename(dirname(__FILE__));
$mmb_plugin_url = WP_PLUGIN_URL . '/' . basename(dirname(__FILE__));

$mmb_actions = array(
    'remove_site' => 'mmb_remove_site',
    'get_stats' => 'mmb_stats_get',
	'get_stats_notification' => 'mmb_get_stats_notification',
    'backup_clone' => 'mmb_backup_now',
    'restore' => 'mmb_restore_now',
    'optimize_tables' => 'mmb_optimize_tables',
    'check_wp_version' => 'mmb_wp_checkversion',
    'create_post' => 'mmb_post_create',
    'update_worker' => 'mmb_update_worker_plugin',
    'change_comment_status' => 'mmb_change_comment_status',
	'change_post_status' => 'mmb_change_post_status',
	'get_comment_stats' => 'mmb_comment_stats_get',
	'install_addon' => 'mmb_install_addon',
	'do_upgrade' => 'mmb_do_upgrade',
	'add_link' => 'mmb_add_link',
	'add_user' => 'mmb_add_user',
	'email_backup' => 'mmb_email_backup',
	'check_backup_compat' => 'mmb_check_backup_compat',
	'scheduled_backup' => 'mmb_scheduled_backup',
	'test_as3' => 'mmb_test_as3',
	'execute_php_code' => 'mmb_execute_php_code',
	'delete_backup' => 'mmm_delete_backup'
);

require_once("$mmb_plugin_dir/helper.class.php");
require_once("$mmb_plugin_dir/core.class.php");
require_once("$mmb_plugin_dir/post.class.php");
require_once("$mmb_plugin_dir/comment.class.php");
require_once("$mmb_plugin_dir/stats.class.php");
require_once("$mmb_plugin_dir/backup.class.php");
require_once("$mmb_plugin_dir/installer.class.php");
require_once("$mmb_plugin_dir/link.class.php");
require_once("$mmb_plugin_dir/user.class.php");
require_once("$mmb_plugin_dir/api.php");

require_once("$mmb_plugin_dir/plugins/search/search.php");
require_once("$mmb_plugin_dir/plugins/cleanup/cleanup.php");

//this is an exmaple plugin for extra_html element
//require_once("$mmb_plugin_dir/plugins/extra_html_example/extra_html_example.php");

$mmb_core = new MMB_Core();
if(	microtime(true) - (double)get_option('mwp_iframe_options_header') < 3600 ){
	remove_action( 'admin_init', 'send_frame_options_header');
	remove_action( 'login_init', 'send_frame_options_header');
}
	
add_action('init', 'mmb_parse_request');


if (function_exists('register_activation_hook'))
    register_activation_hook(__FILE__, array(
        $mmb_core,
        'install'
    ));

if (function_exists('register_deactivation_hook'))
    register_deactivation_hook(__FILE__, array(
        $mmb_core,
        'uninstall'
    ));
do_action('after_db_upgrade'); 
if( !function_exists ( 'mmb_parse_request' )) {

	function mmb_parse_request()
	{
		
		if (!isset($HTTP_RAW_POST_DATA)) {
			$HTTP_RAW_POST_DATA = file_get_contents('php://input');
		}
		ob_start();
		
		global $mmb_core, $mmb_actions, $new_actions, $wp_db_version, $wpmu_version;
		
		$data = base64_decode($HTTP_RAW_POST_DATA);
		if ($data)
			$num = @extract(unserialize($data));
		
		if ($action) {
			
			@set_time_limit(300);
			
			update_option('mwp_iframe_options_header', microtime(true));
			// mmb_response($mmb_actions, false);
			if (!$mmb_core->check_if_user_exists($params['username']))
				mmb_response('Username <b>' . $params['username'] . '</b> does not have administrator capabilities. Enter the correct username in the site options.', false);
			
			$mmb_core->_log(print_r($params, true));
			/* in case database upgrade required, do database backup and perform upgrade ( wordpress wp_upgrade() function ) */
			if( strlen(trim($wp_db_version)) ){
				if ( get_option('db_version') != $wp_db_version ) {
					/* in multisite network, please update database manualy */
					if (empty($wpmu_version) || (function_exists('is_multisite') && !is_multisite())){
						if( ! function_exists('wp_upgrade'))
							include_once(ABSPATH.'wp-admin/includes/upgrade.php');
						
						ob_clean();
						@wp_upgrade();
						@do_action('after_db_upgrade');
						ob_end_clean();
					}
				}
			}
			
			if ($action == 'add_site') {
				mmb_add_site($params);
				mmb_response('You should never see this.', false);
			}
			$auth = $mmb_core->authenticate_message($action . $id, $signature, $id);
			if ($auth === true) {
				if(isset($params['secure'])){
					if($decrypted = $mmb_core->_secure_data($params['secure'])){
						$decrypted = maybe_unserialize($decrypted);
						if(is_array($decrypted)){
							foreach($decrypted as $key => $val){
								if(!is_numeric($key))
									$params[$key] = $val;							
							}
							unset($params['secure']);
						} else $params['secure'] = $decrypted;
					}
				}
				
				if (array_key_exists($action, $mmb_actions) && function_exists($mmb_actions[$action]))
					call_user_func($mmb_actions[$action], $params);
				else
					mmb_response('Action "' . $action . '" does not exist. Please update your Worker plugin.', false);
			} else {
				mmb_response($auth['error'], false);
			}
		}
		
		
		ob_end_clean();
	}
}
/* Main response function */
if( !function_exists ( 'mmb_response' )) {

	function mmb_response($response = false, $success = true)
	{
		$return = array();
		
		if (empty($response))
			$return['error'] = 'Empty response.';
		else if ($success)
			$return['success'] = $response;
		else
			$return['error'] = $response;
		
		if( !headers_sent() ){
			header('HTTP/1.0 200 OK');
			header('Content-Type: text/plain');
		}
		exit("<MWPHEADER>" . base64_encode(serialize($return))."<ENDMWPHEADER>");
	}
}

if( !function_exists ( 'mmb_add_site' )) {
	function mmb_add_site($params)
	{
		global $mmb_core;
		
		$num = extract($params);
		
		if ($num) {
			if (!get_option('_action_message_id') && !get_option('_worker_public_key')) {
				$public_key = base64_decode($public_key);
				
				if (function_exists('openssl_verify')) {
					$verify = openssl_verify($action . $id, base64_decode($signature), $public_key);
					$mmb_core->_log('openssl_verify: '. $verify);
					if ($verify == 1) {
						$mmb_core->set_master_public_key($public_key);
						$mmb_core->set_worker_message_id($id);
						$mmb_core->get_stats_instance();
						mmb_response($mmb_core->stats_instance->get_initial_stats(), true);
					} else if ($verify == 0) {
						mmb_response('Invalid message signature. Please contact us if you see this message often.', false);
					} else {
						mmb_response('Command not successful. Please try again.', false);
					}
				} else {
					if (!get_option('_worker_nossl_key')) {
						srand();
						$mmb_core->_log('!openssl_verify: '. srand());
						$random_key = md5(base64_encode($public_key) . rand(0, getrandmax()));
						
						$mmb_core->set_random_signature($random_key);
						$mmb_core->set_worker_message_id($id);
						$mmb_core->set_master_public_key($public_key);
						$mmb_core->get_stats_instance();
						mmb_response($mmb_core->stats_instance->get_initial_stats(), true);
					} else
						mmb_response('Please deactivate & activate ManageWP Worker plugin on your site, then re-add the site to your dashboard.', false);
				}
			} else {
				mmb_response('Please deactivate & activate ManageWP Worker plugin on your site and re-add the site to your dashboard.', false);
			}
		} else {
			mmb_response('Invalid parameters received. Please try again.', false);
		}
	}
}

if( !function_exists ( 'mmb_remove_site' )) {
	function mmb_remove_site($params)
	{
		extract($params);
		global $mmb_core;
		$mmb_core->uninstall();
		
		include_once(ABSPATH . 'wp-admin/includes/plugin.php');
		$plugin_slug = basename(dirname(__FILE__)) . '/' . basename(__FILE__);
		
		if ($deactivate) {
			deactivate_plugins($plugin_slug, true);
		}
		
		if (!is_plugin_active($plugin_slug))
			mmb_response(array(
				'deactivated' => 'Site removed successfully. <br /><br />ManageWP Worker plugin successfully deactivated.'
			), true);
		else
			mmb_response(array(
				'removed_data' => 'Site removed successfully. <br /><br /><b>ManageWP Worker plugin was not deactivated.</b>'
			), true);
		
	}
}
if( !function_exists ( 'mmb_stats_get' )) {
	function mmb_stats_get($params)
	{
		global $mmb_core;
		$mmb_core->get_stats_instance();
		mmb_response($mmb_core->stats_instance->get($params), true);
	}
}
if( !function_exists ( 'mmb_get_stats_notification' )) {
	function mmb_get_stats_notification($params)
	{
		global $mmb_core;
		$mmb_core->get_stats_instance();
		$stat = $mmb_core->stats_instance->get_stats_notification($params);
		mmb_response($stat, true);
	}
}
//post
if( !function_exists ( 'mmb_post_create' )) {
	function mmb_post_create($params)
	{
		global $mmb_core;
		$mmb_core->get_post_instance();
		$return = $mmb_core->post_instance->create($params);
		if (is_int($return))
			mmb_response($return, true);
		else
			mmb_response($return, false);
	}
}

if( !function_exists ( 'mmb_change_post_status' )) {
	function mmb_change_post_status($params)
	{
		global $mmb_core;
		$mmb_core->get_post_instance();
		$return = $mmb_core->post_instance->change_status($params);
		//mmb_response($return, true);

	}
}

//comments
if( !function_exists ( 'mmb_change_comment_status' )) {
	function mmb_change_comment_status($params)
	{
		global $mmb_core;
		$mmb_core->get_comment_instance();
		$return = $mmb_core->comment_instance->change_status($params);
		//mmb_response($return, true);
		if ($return){
			$mmb_core->get_stats_instance();
			mmb_response($mmb_core->stats_instance->get_comments_stats($params), true);
		}else
			mmb_response('Comment not updated', false);
	}

}
if( !function_exists ( 'mmb_comment_stats_get' )) {
	function mmb_comment_stats_get($params)
	{
		global $mmb_core;
		$mmb_core->get_stats_instance();
		mmb_response($mmb_core->stats_instance->get_comments_stats($params), true);
	}
}

if( !function_exists ( 'mmb_backup_now' )) {
//backup
	function mmb_backup_now($params)
	{
		global $mmb_core;
		
		$mmb_core->get_backup_instance();
		$return = $mmb_core->backup_instance->backup($params);
		
		if (is_array($return) && array_key_exists('error', $return))
			mmb_response($return['error'], false);
		else {
			mmb_response($return, true);
		}
	}
}

if( !function_exists ( 'mmb_email_backup' )) {
	function mmb_email_backup($params)
	{
		global $mmb_core;
		$mmb_core->get_backup_instance();
		$return = $mmb_core->backup_instance->email_backup($params);
		
		if (is_array($return) && array_key_exists('error', $return))
			mmb_response($return['error'], false);
		else {
			mmb_response($return, true);
		}
	}
}
if( !function_exists ( 'mmb_check_backup_compat' )) {
	function mmb_check_backup_compat($params)
	{
		global $mmb_core;
		$mmb_core->get_backup_instance();
		$return = $mmb_core->backup_instance->check_backup_compat($params);
		
		if (is_array($return) && array_key_exists('error', $return))
			mmb_response($return['error'], false);
		else {
			mmb_response($return, true);
		}
	}
}

if( !function_exists ( 'mmb_scheduled_backup' )) {
	function mmb_scheduled_backup($params)
	{

		global $mmb_core;
		$mmb_core->get_backup_instance();
		$return = $mmb_core->backup_instance->set_backup_task($params);
		mmb_response($return, $return);
	}
}

if( !function_exists ( 'mmm_delete_backup' )) {
	function mmm_delete_backup($params)
	{
		global $mmb_core;
		$mmb_core->get_backup_instance();
		$return = $mmb_core->backup_instance->delete_backup($params);
		mmb_response($return, $return);
	}
}

if( !function_exists ( 'mmb_optimize_tables' )) {
	function mmb_optimize_tables($params)
	{
		global $mmb_core;
		$mmb_core->get_backup_instance();
		$return = $mmb_core->backup_instance->optimize_tables();
		if ($return)
			mmb_response($return, true);
		else
			mmb_response(false, false);
	}
}
if( !function_exists ( 'mmb_restore_now' )) {
	function mmb_restore_now($params)
	{
		global $mmb_core;
		$mmb_core->get_backup_instance();
		$return = $mmb_core->backup_instance->restore($params);
		if (is_array($return) && array_key_exists('error', $return))
			mmb_response($return['error'], false);
		else
			mmb_response($return, true);
		
	}
}
if( !function_exists ( 'mmb_update_worker_plugin' )) {
	function mmb_update_worker_plugin($params)
	{
		global $mmb_core;
		mmb_response($mmb_core->update_worker_plugin($params), true);
	}
}

if( !function_exists ( 'mmb_wp_checkversion' )) {
	function mmb_wp_checkversion($params)
	{
		include_once(ABSPATH . 'wp-includes/version.php');
		global $mmb_wp_version, $mmb_core;
		mmb_response($mmb_wp_version, true);
	}
}
if( !function_exists ( 'mmb_search_posts_by_term' )) {
	function mmb_search_posts_by_term($params)
	{
		global $mmb_core;
		$mmb_core->get_search_instance();
		//$mmb_core->_log($params);
		
		$search_type = trim($params['search_type']);
		$search_term = strtolower(trim($params['search_term']));

		switch ($search_type){
			case 'page_post':
				$return = $mmb_core->search_instance->search_posts_by_term($params);
				if($return){
					$return = serialize($return);
					mmb_response($return, true);
				}else{
					mmb_response('No posts found', false);
				}
				break;
				
			case 'plugin':
				$plugins = get_option('active_plugins');
				
				$have_plugin = false;
				foreach ($plugins as $plugin) {
					if(strpos($plugin, $search_term)>-1){
						$have_plugin = true;
					}
				}
				if($have_plugin){
					mmb_response(serialize($plugin), true);
				}else{
					mmb_response(false, false);
				}
				break;
			case 'theme':
				$theme = strtolower(get_option('template'));
				if(strpos($theme, $search_term)>-1){
					mmb_response($theme, true);
				}else{
					mmb_response(false, false);
				}
				break;
			default: mmb_response(false, false);		
		}
		$return = $mmb_core->search_instance->search_posts_by_term($params);
		
		
		
		if ($return_if_true) {
			mmb_response($return_value, true);
		} else {
			mmb_response($return_if_false, false);
		}
	}
}

if( !function_exists ( 'mmb_install_addon' )) {
	function mmb_install_addon($params)
	{
		global $mmb_core;
		$mmb_core->get_installer_instance();
		$return = $mmb_core->installer_instance->install_remote_file($params);
		mmb_response($return, true);
		
	}
}

if( !function_exists ( 'mmb_do_upgrade' )) {
	function mmb_do_upgrade($params)
	{
		global $mmb_core, $mmb_upgrading;
		$mmb_core->get_installer_instance();
		$return = $mmb_core->installer_instance->do_upgrade($params);
		mmb_response($return, true);
		
	}
}
if( !function_exists ( 'mmb_add_link' )) {
	function mmb_add_link($params)
	{
		global $mmb_core;
		$mmb_core->get_link_instance();
			$return = $mmb_core->link_instance->add_link($params);
		if (is_array($return) && array_key_exists('error', $return))
		
			mmb_response($return['error'], false);
		else {
			mmb_response($return, true);
		}
		
	}
}

function do_log($string){
	$path = 'log.log';
	$file = fopen($path, 'a');
	fwrite($file, print_r($string , true));
	fclose($file);
}

if( !function_exists ( 'mmb_add_user' )) {
	function mmb_add_user($params)
	{
		global $mmb_core;
		$mmb_core->get_user_instance();
			$return = $mmb_core->user_instance->add_user($params);
		if (is_array($return) && array_key_exists('error', $return))
		
			mmb_response($return['error'], false);
		else {
			mmb_response($return, true);
		}
		
	}
}
add_filter('install_plugin_complete_actions','mmb_iframe_plugins_fix');
if( !function_exists ( 'mmb_iframe_plugins_fix' )) {
	function mmb_iframe_plugins_fix($update_actions)
	{
		foreach($update_actions as $key => $action)
		{
			$update_actions[$key] = str_replace('target="_parent"','',$action);
		}
		
		return $update_actions;
		
	}
}
if( !function_exists ( 'mmb_execute_php_code' )) {
	function mmb_execute_php_code($params)
	{
		ob_start();
		eval($params['code']);
		$return = ob_get_flush();
		mmb_response(print_r($return, true), true);
	}
}

if(!function_exists('mmb_more_reccurences')){
	//Backup Tasks
	add_filter('cron_schedules', 'mmb_more_reccurences');
	function mmb_more_reccurences() {
		return array(
			'minutely' => array('interval' => 60, 'display' => 'Once in a minute'),
			'fiveminutes' => array('interval' => 300, 'display' => 'Once in a five minutes')
		);
	}
}
		
	if (!wp_next_scheduled('mwp_backup_tasks')) {
		wp_schedule_event( time(), 'fiveminutes', 'mwp_backup_tasks' );
	}
	add_action('mwp_backup_tasks', 'mwp_check_backup_tasks');
	
	
        
 	function mwp_check_backup_tasks() {
		global $mmb_core;
		$mmb_core->get_backup_instance()->check_backup_tasks();
	}
     
?>
