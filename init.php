<?php
/*
Plugin Name: ManageWP - Worker
Plugin URI: http://managewp.com/
Description: Manage all your blogs from one dashboard.
Author: Prelovac Media
Version: 3.6.3
Author URI: http://prelovac.com/
*/

// PHP warnings can break our XML stuffs

if ($_SERVER['REMOTE_ADDR'] != '127.0.0.1')
{
    error_reporting(E_ERROR);
}

define('MMB_WORKER_VERSION', '3.6.3');

global $wpdb, $mmb_plugin_dir, $mmb_plugin_url;

$mmb_plugin_dir = WP_PLUGIN_DIR . '/' . basename(dirname(__FILE__));
$mmb_plugin_url = WP_PLUGIN_URL . '/' . basename(dirname(__FILE__));
//$mmb_plugin_dir = WP_PLUGIN_DIR . '/manage-multiple-blogs-worker';
//$mmb_plugin_url = WP_PLUGIN_URL . '/manage-multiple-blogs-worker';

require_once(ABSPATH . 'wp-includes/class-IXR.php');
require_once("$mmb_plugin_dir/helper.class.php");
require_once("$mmb_plugin_dir/ende.class.php");
require_once("$mmb_plugin_dir/core.class.php");
require_once("$mmb_plugin_dir/comment.class.php");
require_once("$mmb_plugin_dir/plugin.class.php");
require_once("$mmb_plugin_dir/theme.class.php");
require_once("$mmb_plugin_dir/category.class.php");
require_once("$mmb_plugin_dir/wp.class.php");
require_once("$mmb_plugin_dir/page.class.php");
require_once("$mmb_plugin_dir/post.class.php");
require_once("$mmb_plugin_dir/stats.class.php");
require_once("$mmb_plugin_dir/user.class.php");
require_once("$mmb_plugin_dir/tags.class.php");
require_once("$mmb_plugin_dir/backup.class.php");
require_once("$mmb_plugin_dir/clone.class.php");
require_once("$mmb_plugin_dir/mmb.wp.upgrader.php");

//class Mmb_IXR extends IXR_Server{
//
//}

$mmb_core = new Mmb_Core();
register_activation_hook(__FILE__, array($mmb_core, 'install'));

function mmb_worker_upgrade($args) {
    global $mmb_core;
    return $mmb_core->update_this_plugin($args);
}

function mmb_stats_get($args)
{
    global $mmb_core;
    return $mmb_core->get_stats_instance()->get($args);
}

function mmb_stats_server_get($args) {
    global $mmb_core;
    return $mmb_core->get_stats_instance()->get_server_stats($args);
}

function mmb_stats_hit_count_get($args) {
    global $mmb_core;
    return $mmb_core->get_stats_instance()->get_hit_count($args);
}

function mmb_plugin_get_list($args)
{
    global $mmb_core;
    return $mmb_core->get_plugin_instance()->get_list($args);
}
        
function mmb_plugin_activate($args)
{
    global $mmb_core;
    return $mmb_core->get_plugin_instance()->activate($args);
}
        
function mmb_plugin_deactivate($args)
{
    global $mmb_core;
    return $mmb_core->get_plugin_instance()->deactivate($args);
}

function mmb_plugin_upgrade($args)
{
    global $mmb_core;
    return $mmb_core->get_plugin_instance()->upgrade($args);
}

function mmb_plugin_upgrade_multiple($args)
{
    global $mmb_core;
    return $mmb_core->get_plugin_instance()->upgrade_multiple($args);
}

function mmb_plugin_upgrade_all($args)
{
    global $mmb_core;
    return $mmb_core->get_plugin_instance()->upgrade_all($args);
}

function mmb_plugin_delete($args)
{
    global $mmb_core;
    return $mmb_core->get_plugin_instance()->delete($args);
}

function mmb_plugin_install($args)
{
    global $mmb_core;
    return $mmb_core->get_plugin_instance()->install($args);
}

function mmb_plugin_upload_by_url($args)
{
    global $mmb_core;
    return $mmb_core->get_plugin_instance()->upload_by_url($args);
}


        
function mmb_theme_get_list($args)
{
    global $mmb_core;
    return $mmb_core->get_theme_instance()->get_list($args);
}

function mmb_theme_activate($args)
{
    global $mmb_core;
    return $mmb_core->get_theme_instance()->activate($args);
}

function mmb_theme_delete($args)
{
    global $mmb_core;
    return $mmb_core->get_theme_instance()->delete($args);
}

function mmb_theme_install($args)
{
    global $mmb_core;
    return $mmb_core->get_theme_instance()->install($args);
}

function mmb_theme_upgrade($args)
{
    global $mmb_core;
    return $mmb_core->get_theme_instance()->upgrade($args);
}

function mmb_themes_upgrade($args)
{
	global $mmb_core;
    return $mmb_core->get_theme_instance()->upgrade_all($args);
}

function mmb_theme_upload_by_url($args)
{
    global $mmb_core;
    return $mmb_core->get_theme_instance()->upload_theme_by_url($args);
}



        
function mmb_wp_checkversion($args)
{
    global $mmb_core;
	return $mmb_core->get_wp_instance()->check_version($args);
}

function mmb_wp_upgrade($args)
{
    global $mmb_core;
    return $mmb_core->get_wp_instance()->upgrade($args);
}

function mmb_wp_get_updates($args)
{
    global $mmb_core;
    return $mmb_core->get_wp_instance()->get_updates($args);
}






function mmb_cat_get_list($args)
{
    global $mmb_core;
    return $mmb_core->get_category_instance()->get_list($args);
}

function mmb_cat_update($args)
{
    global $mmb_core;
    return $mmb_core->get_category_instance()->update($args);
}

function mmb_cat_add($args)
{
    global $mmb_core;
    return $mmb_core->get_category_instance()->add($args);
}


//Tag start


function mmb_tag_get_list($args)
{
    global $mmb_core;
    return $mmb_core->get_tag_instance()->get_list($args);
}

function mmb_tag_update($args)
{
    global $mmb_core;
    return $mmb_core->get_tag_instance()->update($args);
}

function mmb_tag_add($args)
{  
    global $mmb_core;
    return $mmb_core->get_tag_instance()->add($args);
}

function mmb_tag_delete($args)
{
    global $mmb_core;
    return $mmb_core->get_tag_instance()->delete($args);
}
////End of tag

function mmb_page_get_edit_data($args)
{
    global $mmb_core;
    return $mmb_core->get_page_instance()->get_edit_data($args);
}

function mmb_page_get_new_data($args)
{
    global $mmb_core;
    return $mmb_core->get_page_instance()->get_new_data($args);
}

function mmb_page_update($args)
{
    global $mmb_core;
    return $mmb_core->get_page_instance()->update($args);
}

function mmb_page_create($args)
{
    global $mmb_core;
    return $mmb_core->get_page_instance()->create($args);
}

function mmb_post_get_list($args)
{
    global $mmb_core;
    return $mmb_core->get_post_instance()->get_list($args);
}

function mmb_post_get_edit_data($args)
{
    global $mmb_core;
    return $mmb_core->get_post_instance()->get_edit_data($args);
}

function mmb_post_update($args)
{
    global $mmb_core;
    return $mmb_core->get_post_instance()->update($args);
}

function mmb_post_get_new_data($args)
{
    global $mmb_core;
    return $mmb_core->get_post_instance()->get_new_data($args);
}

function mmb_post_create($args)
{
    global $mmb_core;
    return $mmb_core->get_post_instance()->create($args);
}

function mmb_post_publish($args)
{
    global $mmb_core;
    return $mmb_core->get_post_instance()->publish($args);
}

function mmb_post_checksum($args)
{
    global $mmb_core;
    return $mmb_core->get_post_instance()->checksum($args);
}

function mmb_user_change_password($args)
{
    global $mmb_core;
    return $mmb_core->get_user_instance()->change_password($args);
}


function mmb_bulk_edit_comment($args) {
    global $mmb_core;
    return $mmb_core->get_comment_instance()->bulk_edit_comments($args);
}

function mmb_get_comment_count($args) {
    global $mmb_core;
   return $mmb_core->get_comment_instance()->get_comment_count($args);
}

function mmb_restore_comment($args) {
    global $mmb_core;
    return $mmb_core->get_comment_instance()->restore_comment($args);
}

function mmb_backup_now($args) {
    global $mmb_core;
    return $mmb_core->get_backup_instance()->backup($args);
}

function mmb_restore_now($args) {
    global $mmb_core;
    return $mmb_core->get_backup_instance()->restore($args);
}

function mmb_get_backup_url($args) {
        global $mmb_core;
        return $mmb_core->get_backup_instance()->get_backup_details($args);
}

function mmb_weekly_backup($args) {
        global $mmb_core;
        return $mmb_core->get_backup_instance()->get_weekly_backup($args);
}

function mmb_geet_last_worker_message($args) {
        global $mmb_core;
        return $mmb_core->_get_last_worker_message();
}
?>