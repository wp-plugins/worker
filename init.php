<?php
/* 
Plugin Name: ManageWP - Worker
Plugin URI: http://managewp.com/
Description: Manage all your blogs from one dashboard. Visit <a href="http://managewp.com">ManageWP.com</a> to sign up.
Author: ManageWP
Version: 3.9.24
Author URI: http://managewp.com
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
if(basename($_SERVER['SCRIPT_FILENAME']) == "init.php"):
    exit;
endif;
if(!defined('MMB_WORKER_VERSION'))
	define('MMB_WORKER_VERSION', '3.9.24');

if ( !defined('MMB_XFRAME_COOKIE')){
	$siteurl = function_exists( 'get_site_option' ) ? get_site_option( 'siteurl' ) : get_option( 'siteurl' );
	define('MMB_XFRAME_COOKIE', $xframe = 'wordpress_'.md5($siteurl).'_xframe');
}
global $wpdb, $mmb_plugin_dir, $mmb_plugin_url, $wp_version, $mmb_filters, $_mmb_item_filter;
if (version_compare(PHP_VERSION, '5.0.0', '<')) // min version 5 supported
    exit("<p>ManageWP Worker plugin requires PHP 5 or higher.</p>");


$mmb_wp_version = $wp_version;
$mmb_plugin_dir = WP_PLUGIN_DIR . '/' . basename(dirname(__FILE__));
$mmb_plugin_url = WP_PLUGIN_URL . '/' . basename(dirname(__FILE__));

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

require_once("$mmb_plugin_dir/widget.class.php");

if(!function_exists( 'json_decode' ) && !function_exists( 'json_encode' )){
	global $mmb_plugin_dir;
	require_once ($mmb_plugin_dir.'/lib/json/JSON.php' );
	
	function json_decode($json_object,  $assoc = false) {
		$json = $assoc == true ? new Services_JSON(SERVICES_JSON_LOOSE_TYPE) : new Services_JSON();
		return $json->decode($json_object);
	}
	
	function json_encode($object_data) {
		$json = new Services_JSON();
		return $json->encode($object_data);
	}
}

if( !function_exists ( 'mmb_parse_data' )) {
	function mmb_parse_data( $data = array() ){
		if( empty($data) )
			return $data;
		
		$data = (array) $data;
		if( isset($data['params']) )
			$data['params'] = mmb_filter_params( $data['params'] );
		
		$postkeys = array('action', 'params', 'id', 'signature', 'setting' );
		
		if( !empty($data) ){
			foreach($data as $key => $items){
				if( !in_array($key, $postkeys) )
					unset($data[$key]);
			}
		}
		return $data;
	}
}

if( !function_exists ( 'mmb_filter_params' )) {
	function mmb_filter_params( $array = array() ){
		
		$filter = array( 'current_user', 'wpdb' );
		$return = array();
		foreach ($array as $key => $val) { 
			if( !is_int($key) && in_array($key, $filter) )
				continue;
				
			if( is_array( $val ) ) { 
				$return[$key] = mmb_filter_params( $val );
			} else {
				$return[$key] = $val;
			}
		} 
		
		return $return;
	}
}
if( !function_exists ( 'hex2bin' )) {
	function hex2bin($h){
  		if (!is_string($h)) return null;
  		$r='';
  		for ($a=0; $a<strlen($h); $a+=2) { $r.=chr(hexdec($h{$a}.$h{($a+1)})); }
  		return $r;
  	}
}
if( !function_exists ( 'mmb_parse_request' )) {
	function mmb_parse_request(){
		
		if (!isset($HTTP_RAW_POST_DATA)) {
			$HTTP_RAW_POST_DATA = file_get_contents('php://input');
		}
		ob_start();
		
		global $current_user, $mmb_core, $new_actions, $wp_db_version, $wpmu_version, $_wp_using_ext_object_cache, $_mmb_options;
		/*$data = array();
		if(substr($HTTP_RAW_POST_DATA, 0, 15) == "mwp_a=managewp&"){
			$HTTP_RAW_POST_DATA = str_replace("mwp_a=managewp&", "", $HTTP_RAW_POST_DATA);
			parse_str($HTTP_RAW_POST_DATA, $data);
		}*/
		if(substr($HTTP_RAW_POST_DATA, 0, 7) == "action="){
			$HTTP_RAW_POST_DATA = str_replace("action=", "", $HTTP_RAW_POST_DATA);
		}
		$data = base64_decode($HTTP_RAW_POST_DATA);
		if ($data){
			$data = mmb_parse_data(  @unserialize($data)  );
			$num = @extract( $data );
			//$signature = base64_decode($signature);
		}
		
		if (isset($action)) {
			$_wp_using_ext_object_cache = false;
			@set_time_limit(600);
			
			if (!$mmb_core->check_if_user_exists($params['username']))
				mmb_response('Username <b>' . $params['username'] . '</b> does not have administrator capabilities. Enter the correct username in the site options.', false);
			
			if ($action == 'add_site') {
				mmb_add_site($params);
				mmb_response('You should never see this.', false);
			}

			$auth = $mmb_core->authenticate_message($action . $id, $signature, $id);
			if ($auth === true) {
				
				if(isset($params['username']) && !is_user_logged_in()){
					$user = function_exists('get_user_by') ? get_user_by('login', $params['username']) : get_userdatabylogin( $params['username'] );
					wp_set_current_user($user->ID);
				}
				
				/* in case database upgrade required, do database backup and perform upgrade ( wordpress wp_upgrade() function ) */
				if( strlen(trim($wp_db_version)) && !defined('ACX_PLUGIN_DIR') ){
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
				
				if( isset($data['setting']) ){
					$mmb_core->save_options( $data['setting'] );
				}
				
				if( !$mmb_core->register_action_params( $action, $params ) ){
					global $_mmb_plugin_actions;					
					$_mmb_plugin_actions[$action] = $params;
				}
				
					
			} else {
				mmb_response($auth['error'], false);
			}
		} else {
			MMB_Stats::set_hit_count();
		}
		ob_end_clean();
	}
}
/* Main response function */
if( !function_exists ( 'mmb_response' )) {

	function mmb_response($response = false, $success = true)
	{
		$return = array();
		
		if ((is_array($response) && empty($response)) || (!is_array($response) && strlen($response) == 0))
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
	function mmb_add_site($params) {
		global $mmb_core;
		$num = extract($params);
		
		if ($num) {
			if (!get_option('_action_message_id') && !get_option('_worker_public_key')) {
				$public_key = base64_decode($public_key);
				
				if (function_exists('openssl_verify')) {
					$verify = openssl_verify($action . $id, base64_decode($signature), $public_key);
					if ($verify == 1) {
						$mmb_core->set_master_public_key($public_key);
						$mmb_core->set_worker_message_id($id);
						$mmb_core->get_stats_instance();
						if(isset($notifications) && is_array($notifications) && !empty($notifications)){
							$mmb_core->stats_instance->set_notifications($notifications);
						}
						if(isset($brand) && is_array($brand) && !empty($brand)){
							update_option('mwp_worker_brand', $brand);
						}
						
						if( isset( $add_settigns ) && is_array($add_settigns) && !empty( $add_settigns ) )
							apply_filters( 'mwp_website_add', $add_settigns );
							
						mmb_response($mmb_core->stats_instance->get_initial_stats(), true);
					} else if ($verify == 0) {
																			
						//mmb_response('Site could not be added. OpenSSL verification error: "'.openssl_error_string().'". Contact your hosting support to check the OpenSSL configuration.', false);
						
					} else {
						mmb_response('Command not successful. Please try again.', false);
					}
				} 
					
					if (!get_option('_worker_nossl_key')) {
						srand();
						
						$random_key = md5(base64_encode($public_key) . rand(0, getrandmax()));
						
						$mmb_core->set_random_signature($random_key);
						$mmb_core->set_worker_message_id($id);
						$mmb_core->set_master_public_key($public_key);
						$mmb_core->get_stats_instance();						
						if(is_array($notifications) && !empty($notifications)){
							$mmb_core->stats_instance->set_notifications($notifications);
						}
						
						if(is_array($brand) && !empty($brand)){
							update_option('mwp_worker_brand',$brand);
						}
						
						mmb_response($mmb_core->stats_instance->get_initial_stats(), true);
					} else
						mmb_response('Please deactivate & activate ManageWP Worker plugin on your site, then re-add the site to your dashboard.', false);
			
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
		$mmb_core->uninstall( $deactivate );
		
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

if( !function_exists ( 'mmb_worker_header' )) {
	function mmb_worker_header()
	{	global $mmb_core, $current_user;
		
		if(!headers_sent()){
			if(isset($current_user->ID))
				$expiration = time() + apply_filters('auth_cookie_expiration', 10800, $current_user->ID, false);
			else 
				$expiration = time() + 10800;
				
			setcookie(MMB_XFRAME_COOKIE, md5(MMB_XFRAME_COOKIE), $expiration, COOKIEPATH, COOKIE_DOMAIN, false, true);
			$_COOKIE[MMB_XFRAME_COOKIE] = md5(MMB_XFRAME_COOKIE);
		}
	}
}

if( !function_exists ( 'mmb_pre_init_stats' )) {
	function mmb_pre_init_stats( $params )
	{
		global $mmb_core;
		$mmb_core->get_stats_instance();
		return $mmb_core->stats_instance->pre_init_stats($params);
	}
}

if( !function_exists ( 'mwp_datasend' )) {
	function mwp_datasend( $params = array() )
	{
		global $mmb_core, $_mmb_item_filter, $_mmb_options;
		if( isset($_mmb_options['datacron']) ){
			
			$_mmb_remoteurl = get_option('home');
			$_mmb_remoteown = isset($_mmb_options['dataown']) && !empty($_mmb_options['dataown']) ? $_mmb_options['dataown'] : false;
			
			if( empty($_mmb_remoteown) )
				return;
			
			$_mmb_item_filter['pre_init_stats'] = array( 'core_update', 'hit_counter', 'comments', 'backups', 'posts', 'drafts', 'scheduled' );
			$_mmb_item_filter['get'] = array( 'updates', 'errors' );
			$mmb_core->get_stats_instance();
			
			$filter = array(
				'refresh' => '',
				'item_filter' => array(
					'get_stats' => array(
						array('updates', array('plugins' => true, 'themes' => true, 'premium' => true )),
						array('core_update', array('core' => true )),
						array('posts', array('numberposts' => 5 )),
						array('drafts', array('numberposts' => 5 )),
						array('scheduled', array('numberposts' => 5 )),
						array('hit_counter'),
						array('comments', array('numberposts' => 5 )),
						array('backups'),
						'plugins' => array('cleanup' => array(
										'overhead' => array(),
										'revisions' => array( 'num_to_keep' => 'r_5'),
										'spam' => array(),
									)
						),
					),
				)
			);
			
			$pre_init_data = $mmb_core->stats_instance->pre_init_stats($filter);
			$init_data = $mmb_core->stats_instance->get($filter);
			
			$data = array_merge($init_data, $pre_init_data);
			$hash = $mmb_core->get_secure_hash();
			
			$datasend['datasend'] = $mmb_core->encrypt_data($data);
			$datasend['sitehome'] = base64_encode($_mmb_remoteown.'[]'.$_mmb_remoteurl);
			$datasend['sitehash'] = md5($hash.$_mmb_remoteown.$_mmb_remoteurl);
			
			if ( !class_exists('WP_Http') )
                include_once(ABSPATH . WPINC . '/class-http.php');
			
			$remote = array();
			$remote['body'] = $datasend;
	
			$result = wp_remote_post($_mmb_options['datacron'], $remote);
			if(!is_wp_error($result)){
				if(isset($result['body']) && !empty($result['body'])){
					$settings = @unserialize($result['body']);
					$w_version = $settings['worker_updates']['version'];
					$w_url = $settings['worker_updates']['url'];
					if(version_compare(MMB_WORKER_VERSION, $w_version, '<')){
						//automatic update
						$mmb_core->update_worker_plugin(array("download_url" => $w_url));
					}
				}
			}else{
				//$mmb_core->_log($result);
			}
			
		}
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
		else{
			if(isset($return['error'])){
				mmb_response($return['error'], false);
			} else {
				mmb_response($return, false);
			}
		}
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

if( !function_exists ( 'mmb_run_task_now' )) {
	function mmb_run_task_now($params)
	{
		global $mmb_core;
		$mmb_core->get_backup_instance();
		
		$task_name = isset($params['task_name']) ? $params['task_name'] : false;
		$google_drive_token = isset($params['google_drive_token']) ? $params['google_drive_token'] : false;
		
		if ($task_name) {
			$return = $mmb_core->backup_instance->task_now($task_name, $google_drive_token);
			if (is_array($return) && array_key_exists('error', $return))
				mmb_response($return['error'], false);
			else {
				mmb_response($return, true);
			}
		} else {
			mmb_response("Task name is not provided.", false);
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

if( !function_exists ( 'mmb_get_backup_req' )) {
	function mmb_get_backup_req( $params )
	{
		global $mmb_core;
		$mmb_core->get_stats_instance();
		$return = $mmb_core->stats_instance->get_backup_req($params);
		
		mmb_response($return, true);
	}
}

if( !function_exists ( 'mmb_scheduled_backup' )) {
	function mmb_scheduled_backup($params)
	{
		global $mmb_core;
		$mmb_core->get_backup_instance();
		$params["time"] = time();
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

if( !function_exists ( 'mmb_remote_backup_now' )) {
	function mmb_remote_backup_now($params)
	{
		global $mmb_core;
		$backup_instance = $mmb_core->get_backup_instance();
		$return = $mmb_core->backup_instance->remote_backup_now($params);
		if (is_array($return) && array_key_exists('error', $return))
			mmb_response($return['error'], false);
		else
			mmb_response($return, true);
	}
}


if( !function_exists ( 'mmb_clean_orphan_backups' )) {
	function mmb_clean_orphan_backups()
	{
		global $mmb_core;
		$backup_instance = $mmb_core->get_backup_instance();
		$return = $mmb_core->backup_instance->cleanup();
		if(is_array($return))
			mmb_response($return, true);
		else
			mmb_response($return, false);
	}
}

/*function mmb_run_backup_action() {
	if (!wp_verify_nonce($_POST['mmb_backup_nonce'], 'mmb-backup-nonce')) return false;
	$args = @unserialize(stripslashes($_POST['args']));
	if (!$args) return false;
	$cron_action = isset($_POST['backup_cron_action']) ? $_POST['backup_cron_action'] : false;
	if ($cron_action) {
		do_action($cron_action, $args);
	}
	unset($_POST['mmb_backup_nonce']);
	unset($_POST['args']);
	unset($_POST['backup_cron_action']);
	return true;
}*/

add_filter( 'mwp_website_add', 'mmb_readd_backup_task' );

if (!function_exists('mmb_readd_backup_task')) {
	function mmb_readd_backup_task($params = array()) {
		global $mmb_core;
		$backup_instance = $mmb_core->get_backup_instance();
		$settings = $backup_instance->readd_tasks($params);
		return $settings;
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

if( !function_exists ('mmb_get_links')) {
	function mmb_get_links($params)
	{
		global $mmb_core;
		$mmb_core->get_link_instance();
			$return = $mmb_core->link_instance->get_links($params);
		if (is_array($return) && array_key_exists('error', $return))
			mmb_response($return['error'], false);
		else {
			mmb_response($return, true);
		}
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

if( !function_exists ('mmb_delete_link')) {
	function mmb_delete_link($params)
	{
		global $mmb_core;
		$mmb_core->get_link_instance();
		
			$return = $mmb_core->link_instance->delete_link($params);
		if (is_array($return) && array_key_exists('error', $return))
			mmb_response($return['error'], false);
		else {
			mmb_response($return, true);
		}
	}
}

if( !function_exists ('mmb_delete_links')) {
	function mmb_delete_links($params)
	{
		global $mmb_core;
		$mmb_core->get_link_instance();
		
			$return = $mmb_core->link_instance->delete_links($params);
		if (is_array($return) && array_key_exists('error', $return))
			mmb_response($return['error'], false);
		else {
			mmb_response($return, true);
		}
	}
}

if( !function_exists ('mmb_get_comments')) {
	function mmb_get_comments($params)
	{
		global $mmb_core;
		$mmb_core->get_comment_instance();
			$return = $mmb_core->comment_instance->get_comments($params);
		if (is_array($return) && array_key_exists('error', $return))
			mmb_response($return['error'], false);
		else {
			mmb_response($return, true);
		}
	}
}

if( !function_exists ('mmb_action_comment')) {
	function mmb_action_comment($params)
	{
		global $mmb_core;
		$mmb_core->get_comment_instance();
		
			$return = $mmb_core->comment_instance->action_comment($params);
		if (is_array($return) && array_key_exists('error', $return))
			mmb_response($return['error'], false);
		else {
			mmb_response($return, true);
		}
	}
}

if( !function_exists ('mmb_bulk_action_comments')) {
	function mmb_bulk_action_comments($params)
	{
		global $mmb_core;
		$mmb_core->get_comment_instance();
		
			$return = $mmb_core->comment_instance->bulk_action_comments($params);
		if (is_array($return) && array_key_exists('error', $return))
			mmb_response($return['error'], false);
		else {
			mmb_response($return, true);
		}
	}
}

if( !function_exists ('mmb_reply_comment')) {
	function mmb_reply_comment($params)
	{
		global $mmb_core;
		$mmb_core->get_comment_instance();
		
			$return = $mmb_core->comment_instance->reply_comment($params);
		if (is_array($return) && array_key_exists('error', $return))
			mmb_response($return['error'], false);
		else {
			mmb_response($return, true);
		}
	}
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

if( !function_exists ('mmb_get_users')) {
	function mmb_get_users($params)
	{
		global $mmb_core;
		$mmb_core->get_user_instance();
			$return = $mmb_core->user_instance->get_users($params);
		if (is_array($return) && array_key_exists('error', $return))
			mmb_response($return['error'], false);
		else {
			mmb_response($return, true);
		}
	}
}

if( !function_exists ('mmb_edit_users')) {
	function mmb_edit_users($params)
	{
		global $mmb_core;
		$mmb_core->get_user_instance();
		$return = $mmb_core->user_instance->edit_users($params);
		mmb_response($return, true);
	}
}

if( !function_exists ('mmb_get_posts')) {
	function mmb_get_posts($params)
	{
		global $mmb_core;
		$mmb_core->get_post_instance();
		
			$return = $mmb_core->post_instance->get_posts($params);
		if (is_array($return) && array_key_exists('error', $return))
			mmb_response($return['error'], false);
		else {
			mmb_response($return, true);
		}
	}
}

if( !function_exists ('mmb_delete_post')) {
	function mmb_delete_post($params)
	{
		global $mmb_core;
		$mmb_core->get_post_instance();
		
			$return = $mmb_core->post_instance->delete_post($params);
		if (is_array($return) && array_key_exists('error', $return))
			mmb_response($return['error'], false);
		else {
			mmb_response($return, true);
		}
	}
}

if( !function_exists ('mmb_delete_posts')) {
	function mmb_delete_posts($params)
	{
		global $mmb_core;
		$mmb_core->get_post_instance();
		
			$return = $mmb_core->post_instance->delete_posts($params);
		if (is_array($return) && array_key_exists('error', $return))
			mmb_response($return['error'], false);
		else {
			mmb_response($return, true);
		}
	}
}

if( !function_exists ('mmb_edit_posts')) {
	function mmb_edit_posts($params)
	{
		global $mmb_core;
		$mmb_core->get_posts_instance();
		$return = $mmb_core->posts_instance->edit_posts($params);
		mmb_response($return, true);
	}
}

if( !function_exists ('mmb_get_pages')) {
	function mmb_get_pages($params)
	{
		global $mmb_core;
		$mmb_core->get_post_instance();
		
			$return = $mmb_core->post_instance->get_pages($params);
		if (is_array($return) && array_key_exists('error', $return))
			mmb_response($return['error'], false);
		else {
			mmb_response($return, true);
		}
	}
}

if( !function_exists ('mmb_delete_page')) {
	function mmb_delete_page($params)
	{
		global $mmb_core;
		$mmb_core->get_post_instance();
		
			$return = $mmb_core->post_instance->delete_page($params);
		if (is_array($return) && array_key_exists('error', $return))
			mmb_response($return['error'], false);
		else {
			mmb_response($return, true);
		}
	}
}

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

if( !function_exists ( 'mmb_set_notifications' )) {
	function mmb_set_notifications($params)
	{
		global $mmb_core;
		$mmb_core->get_stats_instance();
			$return = $mmb_core->stats_instance->set_notifications($params);
		if (is_array($return) && array_key_exists('error', $return))
			mmb_response($return['error'], false);
		else {
			mmb_response($return, true);
		}
		
	}
}

if( !function_exists ( 'mmb_set_alerts' )) {
	function mmb_set_alerts($params)
	{
		global $mmb_core;
			$mmb_core->get_stats_instance();
			$return = $mmb_core->stats_instance->set_alerts($params);
			mmb_response(true, true);
	}
		
}
if( !function_exists ('mmb_get_dbname')) {
	function mmb_get_dbname($params)
	{
		global $mmb_core;
		$mmb_core->get_stats_instance();
		
			$return = $mmb_core->stats_instance->get_active_db();
		if (is_array($return) && array_key_exists('error', $return))
			mmb_response($return['error'], false);
		else {
			mmb_response($return, true);
		}
	}
}

if( !function_exists('mmb_more_reccurences') ){
	//Backup Tasks
	add_filter('cron_schedules', 'mmb_more_reccurences');
	function mmb_more_reccurences($schedules) {
		$schedules['halfminute'] = array('interval' => 30, 'display' => 'Once in a half minute');
		$schedules['minutely'] = array('interval' => 60, 'display' => 'Once in a minute');
		$schedules['fiveminutes'] = array('interval' => 300, 'display' => 'Once every five minutes');
		$schedules['tenminutes'] = array('interval' => 600, 'display' => 'Once every ten minutes');
		$schedules['sixhours'] = array('interval' => 21600, 'display' => 'Every six hours');
		$schedules['fourhours'] = array('interval' => 14400, 'display' => 'Every four hours');
		$schedules['threehours'] = array('interval' => 10800, 'display' => 'Every three hours');
		
		return $schedules;
	}
}
	
add_action('mwp_backup_tasks', 'mwp_check_backup_tasks');
	
if( !function_exists('mwp_check_backup_tasks') ){
 	function mwp_check_backup_tasks() {
		global $mmb_core, $_wp_using_ext_object_cache;
		$_wp_using_ext_object_cache = false;
		
		$mmb_core->get_backup_instance();
		$mmb_core->backup_instance->check_backup_tasks();
	}
}

// Remote upload in the second request.
add_action('mmb_scheduled_remote_upload', 'mmb_call_scheduled_remote_upload');
//add_action('mmb_remote_upload', 'mmb_call_scheduled_remote_upload');

if( !function_exists('mmb_call_scheduled_remote_upload') ){
	function mmb_call_scheduled_remote_upload($args) {
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
	wp_schedule_event( time(), 'threehours', 'mwp_datasend' );
}

add_action('mwp_datasend', 'mwp_datasend');
	
if( !function_exists('mwp_check_notifications') ){
 	function mwp_check_notifications() {
		global $mmb_core, $_wp_using_ext_object_cache;
		$_wp_using_ext_object_cache = false;
		
		$mmb_core->get_stats_instance();
		$mmb_core->stats_instance->check_notifications();
	}
}


if( !function_exists('mmb_get_plugins_themes') ){
 	function mmb_get_plugins_themes($params) {
		global $mmb_core;
		$mmb_core->get_installer_instance();
		$return = $mmb_core->installer_instance->get($params);
		mmb_response($return, true);
	}
}

if( !function_exists('mmb_edit_plugins_themes') ){
 	function mmb_edit_plugins_themes($params) {
		global $mmb_core;
		$mmb_core->get_installer_instance();
		$return = $mmb_core->installer_instance->edit($params);
		mmb_response($return, true);
	}
}

if( !function_exists('mmb_worker_brand')){
 	function mmb_worker_brand($params) {
		update_option("mwp_worker_brand",$params['brand']);
		mmb_response(true, true);
	}
}

if( !function_exists('mmb_maintenance_mode')){
 	function mmb_maintenance_mode( $params ) {
		global $wp_object_cache;
		
		$default = get_option('mwp_maintenace_mode');
		$params = empty($default) ? $params : array_merge($default, $params);
		update_option("mwp_maintenace_mode", $params);
		
		if(!empty($wp_object_cache))
			@$wp_object_cache->flush(); 
		mmb_response(true, true);
	}
}

if( !function_exists('mmb_plugin_actions') ){
 	function mmb_plugin_actions() {
		global $mmb_actions, $mmb_core;
		
		if(!empty($mmb_actions)){
			global $_mmb_plugin_actions;
			if(!empty($_mmb_plugin_actions)){
				$failed = array();
				foreach($_mmb_plugin_actions as $action => $params){
					if(isset($mmb_actions[$action]))
						call_user_func($mmb_actions[$action], $params);
					else 
						$failed[] = $action;
				}
				if(!empty($failed)){
					$f = implode(', ', $failed);
					$s = count($f) > 1 ? 'Actions "' . $f . '" do' : 'Action "' . $f . '" does';
					mmb_response($s.' not exist. Please update your Worker plugin.', false);
				}
					
			}
		}
		
		global $pagenow, $current_user, $mmode;
		if( !is_admin() && !in_array($pagenow, array( 'wp-login.php' ))){
			$mmode = get_option('mwp_maintenace_mode');
			if( !empty($mmode) ){
				if(isset($mmode['active']) && $mmode['active'] == true){
					if(isset($current_user->data) && !empty($current_user->data) && isset($mmode['hidecaps']) && !empty($mmode['hidecaps'])){
						$usercaps = array();
						if(isset($current_user->caps) && !empty($current_user->caps)){
							$usercaps = $current_user->caps;
						}
						foreach($mmode['hidecaps'] as $cap => $hide){
							if(!$hide)
								continue;
							
							foreach($usercaps as $ucap => $val){
								if($ucap == $cap){
									ob_end_clean();
									ob_end_flush();
									die($mmode['template']);
								}
							}
						}
					} else
						die($mmode['template']);
				}
			}
		}
		
		if (file_exists(dirname(__FILE__) . '/log')) {
			unlink(dirname(__FILE__) . '/log');
		}
	}
}

$mmb_core = new MMB_Core();

if(isset($_GET['auto_login']))
	$mmb_core->automatic_login();	

if (function_exists('register_activation_hook'))
    register_activation_hook( __FILE__ , array( $mmb_core, 'install' ));

if (function_exists('register_deactivation_hook'))
    register_deactivation_hook(__FILE__, array( $mmb_core, 'uninstall' ));

if (function_exists('add_action'))
	add_action('init', 'mmb_plugin_actions', 99999);

if (function_exists('add_filter'))
	add_filter('install_plugin_complete_actions','mmb_iframe_plugins_fix');

if(!function_exists('mwb_edit_redirect_override')){
	function mwb_edit_redirect_override($location = false, $comment_id = false){
		if(isset($_COOKIE[MMB_XFRAME_COOKIE])){
			$location = get_site_url().'/wp-admin/edit-comments.php';
		}
		return $location;
	}
}
if (function_exists('add_filter'))
	add_filter('comment_edit_redirect', 'mwb_edit_redirect_override');

if(	isset($_COOKIE[MMB_XFRAME_COOKIE]) ){
	remove_action( 'admin_init', 'send_frame_options_header');
	remove_action( 'login_init', 'send_frame_options_header');
}

?>