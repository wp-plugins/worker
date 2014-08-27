<?php

/*************************************************************
 * core.class.php
 * Upgrade Plugins
 * Copyright (c) 2011 Prelovac Media
 * www.prelovac.com
 **************************************************************/
class MMB_Core extends MMB_Helper
{
    public $name;
    public $slug;
    public $settings;
    public $remote_client;
    public $comment_instance;
    public $plugin_instance;
    public $theme_instance;
    public $wp_instance;
    public $post_instance;
    public $stats_instance;
    public $search_instance;
    public $links_instance;
    public $user_instance;
    public $security_instance;
    public $backup_instance;
    public $installer_instance;
    public $mmb_multisite;
    public $network_admin_install;
    private $action_call;
    private $action_params;
    private $mmb_pre_init_actions;
    private $mmb_pre_init_filters;
    private $mmb_init_actions;

    function __construct()
    {
        global $wpmu_version, $blog_id, $_mmb_plugin_actions, $_mmb_item_filter, $_mmb_options;

        $_mmb_plugin_actions = array();
        $_mmb_options        = get_option('wrksettings');
        $_mmb_options        = !empty($_mmb_options) ? $_mmb_options : array();

        $this->name          = 'Manage Multiple Blogs';
        $this->action_call   = null;
        $this->action_params = null;

        if (function_exists('is_multisite')) {
            if (is_multisite()) {
                $this->mmb_multisite         = $blog_id;
                $this->network_admin_install = get_option('mmb_network_admin_install');
            }
        } else {
            if (!empty($wpmu_version)) {
                $this->mmb_multisite         = $blog_id;
                $this->network_admin_install = get_option('mmb_network_admin_install');
            } else {
                $this->mmb_multisite         = false;
                $this->network_admin_install = null;
            }
        }

        // admin notices
        if (!get_option('_worker_public_key')) {
            if ($this->mmb_multisite) {
                if (is_network_admin() && $this->network_admin_install == '1') {
                    add_action('network_admin_notices', array(&$this, 'network_admin_notice'));
                } else {
                    if ($this->network_admin_install != '1') {
                        $parent_key = $this->get_parent_blog_option('_worker_public_key');
                        if (empty($parent_key)) {
                            add_action('admin_notices', array(&$this, 'admin_notice'));
                        }
                    }
                }
            } else {
                add_action('admin_notices', array(&$this, 'admin_notice'));
            }
        }

        // default filters
        //$this->mmb_pre_init_filters['get_stats']['mmb_stats_filter'][] = array('MMB_Stats', 'pre_init_stats'); // called with class name, use global $mmb_core inside the function instead of $this
        $this->mmb_pre_init_filters['get_stats']['mmb_stats_filter'][] = 'mmb_pre_init_stats';

        $_mmb_item_filter['pre_init_stats'] = array('core_update', 'hit_counter', 'comments', 'backups', 'posts', 'drafts', 'scheduled');
        $_mmb_item_filter['get']            = array('updates', 'errors');

        $this->mmb_pre_init_actions = array(
            'backup_req' => 'mmb_get_backup_req',
        );

        $this->mmb_init_actions = array(
            'do_upgrade'                       => 'mmb_do_upgrade',
            'get_stats'                        => 'mmb_stats_get',
            'remove_site'                      => 'mmb_remove_site',
            'backup_clone'                     => 'mmb_backup_now',
            'restore'                          => 'mmb_restore_now',
            'optimize_tables'                  => 'mmb_optimize_tables',
            'check_wp_version'                 => 'mmb_wp_checkversion',
            'create_post'                      => 'mmb_post_create',
            'update_worker'                    => 'mmb_update_worker_plugin',
            'change_comment_status'            => 'mmb_change_comment_status',
            'change_post_status'               => 'mmb_change_post_status',
            'get_comment_stats'                => 'mmb_comment_stats_get',
            'install_addon'                    => 'mmb_install_addon',
            'install_addons'                   => 'mmb_install_addons',
            'get_links'                        => 'mmb_get_links',
            'add_link'                         => 'mmb_add_link',
            'delete_link'                      => 'mmb_delete_link',
            'delete_links'                     => 'mmb_delete_links',
            'get_comments'                     => 'mmb_get_comments',
            'action_comment'                   => 'mmb_action_comment',
            'bulk_action_comments'             => 'mmb_bulk_action_comments',
            'replyto_comment'                  => 'mmb_reply_comment',
            'add_user'                         => 'mmb_add_user',
            'email_backup'                     => 'mmb_email_backup',
            'check_backup_compat'              => 'mmb_check_backup_compat',
            'scheduled_backup'                 => 'mmb_scheduled_backup',
            'run_task'                         => 'mmb_run_task_now',
            'execute_php_code'                 => 'mmb_execute_php_code',
            'delete_backup'                    => 'mmm_delete_backup',
            'remote_backup_now'                => 'mmb_remote_backup_now',
            'set_notifications'                => 'mmb_set_notifications',
            'clean_orphan_backups'             => 'mmb_clean_orphan_backups',
            'get_users'                        => 'mmb_get_users',
            'edit_users'                       => 'mmb_edit_users',
            'get_posts'                        => 'mmb_get_posts',
            'delete_post'                      => 'mmb_delete_post',
            'delete_posts'                     => 'mmb_delete_posts',
            'edit_posts'                       => 'mmb_edit_posts',
            'get_pages'                        => 'mmb_get_pages',
            'delete_page'                      => 'mmb_delete_page',
            'get_plugins_themes'               => 'mmb_get_plugins_themes',
            'edit_plugins_themes'              => 'mmb_edit_plugins_themes',
            'worker_brand'                     => 'mmb_worker_brand',
            'maintenance'                      => 'mmb_maintenance_mode',
            'get_dbname'                       => 'mmb_get_dbname',
            'security_check'                   => 'mbb_security_check',
            'security_fix_folder_listing'      => 'mbb_security_fix_folder_listing',
            'security_fix_php_reporting'       => 'mbb_security_fix_php_reporting',
            'security_fix_database_reporting'  => 'mbb_security_fix_database_reporting',
            'security_fix_wp_version'          => 'mbb_security_fix_wp_version',
            'security_fix_admin_username'      => 'mbb_security_fix_admin_username',
            'security_fix_htaccess_permission' => 'mbb_security_fix_htaccess_permission',
            'security_fix_scripts_styles'      => 'mbb_security_fix_scripts_styles',
            'security_fix_file_permission'     => 'mbb_security_fix_file_permission',
            'security_fix_all'                 => 'mbb_security_fix_all',
            'get_autoupdate_plugins_themes'    => 'mmb_get_autoupdate_plugins_themes',
            'edit_autoupdate_plugins_themes'   => 'mmb_edit_autoupdate_plugins_themes',
            'ping_backup'                      => 'mwp_ping_backup',
        );

        $mwp_worker_brand = get_option("mwp_worker_brand");
        //!$mwp_worker_brand['hide_managed_remotely']
        if ($mwp_worker_brand == false || (is_array($mwp_worker_brand) && !array_key_exists('hide_managed_remotely', $mwp_worker_brand))) {
            add_action('rightnow_end', array(&$this, 'add_right_now_info'));
        }
        if ($mwp_worker_brand != false && is_array($mwp_worker_brand) && isset($mwp_worker_brand['text_for_client']) && ($mwp_worker_brand['email_or_link'] != 0)) {
            add_action('admin_init', array($this, 'enqueue_scripts'));
            add_action('admin_init', array($this, 'enqueue_styles'));
            add_action('admin_menu', array($this, 'add_support_page'));
            add_action('admin_head', array($this, 'support_page_script'));
            add_action('admin_footer', array($this, 'support_page_dialog'));
            add_action('admin_init', array($this, 'send_email_to_admin'));
        }
        add_action('plugins_loaded', array(&$this, 'dissalow_text_editor'));

        add_action('admin_init', array(&$this, 'admin_actions'));
        add_action('init', array(&$this, 'mmb_remote_action'), 9999);
        add_action('setup_theme', 'mmb_run_forked_action', 1);
        add_action('plugins_loaded', 'mmb_authenticate', 1);
        add_action('setup_theme', 'mmb_parse_request');
        add_action('set_auth_cookie', array(&$this, 'mmb_set_auth_cookie'));
        add_action('set_logged_in_cookie', array(&$this, 'mmb_set_logged_in_cookie'));

        if(!get_option('_worker_nossl_key') && !get_option('_worker_public_key')){
            add_action('init', array(&$this, 'deactivateWorkerIfNotAddedAfterTenMinutes'));
        }
    }

    function mmb_remote_action()
    {
        if ($this->action_call != null) {
            $params = isset($this->action_params) && $this->action_params != null ? $this->action_params : array();
            call_user_func($this->action_call, $params);
        }
    }

    function register_action_params($action = false, $params = array())
    {

        if (isset($this->mmb_pre_init_actions[$action]) && function_exists($this->mmb_pre_init_actions[$action])) {
            call_user_func($this->mmb_pre_init_actions[$action], $params);
        }

        if (isset($this->mmb_init_actions[$action]) && function_exists($this->mmb_init_actions[$action])) {
            $this->action_call   = $this->mmb_init_actions[$action];
            $this->action_params = $params;

            if (isset($this->mmb_pre_init_filters[$action]) && !empty($this->mmb_pre_init_filters[$action])) {
                global $mmb_filters;

                foreach ($this->mmb_pre_init_filters[$action] as $_name => $_functions) {
                    if (!empty($_functions)) {
                        $data = array();

                        foreach ($_functions as $_callback) {
                            if (is_array($_callback) && method_exists($_callback[0], $_callback[1])) {
                                $data = call_user_func($_callback, $params);
                            } elseif (is_string($_callback) && function_exists($_callback)) {
                                $data = call_user_func($_callback, $params);
                            }
                            $mmb_filters[$_name] = isset($mmb_filters[$_name]) && !empty($mmb_filters[$_name]) ? array_merge($mmb_filters[$_name], $data) : $data;
                            add_filter($_name, create_function('$a', 'global $mmb_filters; return array_merge($a, $mmb_filters["'.$_name.'"]);'));
                        }
                    }

                }
            }

            return true;
        }

        return false;
    }

    /**
     * Add notice to network admin dashboard for security reasons
     */
    function network_admin_notice()
    {
        global $status, $page, $s;
        $context = $status;
        $plugin  = 'worker/init.php';
        $nonce   = wp_create_nonce('deactivate-plugin_'.$plugin);
        $actions = 'plugins.php?action=deactivate&amp;plugin='.urlencode($plugin).'&amp;plugin_status='.$context.'&amp;paged='.$page.'&amp;s='.$s.'&amp;_wpnonce='.$nonce;
        $configurationService = new MWP_Configuration_Service();
        $configuration = $configurationService->getConfiguration();
        $notice = $configuration->getNetworkNotice();
        $notice = str_replace("{deactivate_url}", $actions, $notice);
        echo $notice;
    }


    /**
     * Add notice to admin dashboard for security reasons
     */
    function admin_notice()
    {
        global $status, $page, $s;
        $context      = $status;
        $plugin       = 'worker/init.php';
        $nonce        = wp_create_nonce('deactivate-plugin_'.$plugin);
        $actions      = 'plugins.php?action=deactivate&amp;plugin='.urlencode($plugin).'&amp;plugin_status='.$context.'&amp;paged='.$page.'&amp;s='.$s.'&amp;_wpnonce='.$nonce;
        $configurationService = new MWP_Configuration_Service();
        $configuration        = $configurationService->getConfiguration();
        $notice               = $configuration->getNotice();
        $deactivateText       = $configuration->getDeactivateText();
        if ($this->mmb_multisite && $this->network_admin_install != '1') {
            $deactivateTextLink = ''.$deactivateText;
        }else{
            $deactivateTextLink = '<a href="'.$actions.'" class="mwp_text_notice">'.$deactivateText.'</a>';
        }
        $notice = str_replace("{deactivate_text}", $deactivateTextLink, $notice);

        echo $notice;
    }

    //

    /**
     * Add an item into the Right Now Dashboard widget
     * to inform that the blog can be managed remotely
     */
    function add_right_now_info()
    {
        $mwp_worker_brand = get_option('mwp_worker_brand');
        echo '<div class="mmb-slave-info">';
        if ($mwp_worker_brand && isset($mwp_worker_brand['remotely_managed_text'])) {
            /*$url = isset($mwp_worker_brand['author_url']) ? $mwp_worker_brand['author_url'] : null;
            if($url) {
                $scheme = parse_url($mwp_worker_brand['author_url'], PHP_URL_SCHEME);
                if(empty($scheme)) {
                    $url = 'http://' . $url;
                }
            }
            if($url) {
                $managedBy = '<a target="_blank" href="'.htmlspecialchars($url).'">'
                    .htmlspecialchars($mwp_worker_brand['author'])
                    .'</a>';
            } else {
                $managedBy = htmlspecialchars($mwp_worker_brand['author']);
            }
            echo sprintf('<p>This site is managed by %s.</p>', $managedBy);*/
            echo '<p>'.$mwp_worker_brand['remotely_managed_text'].'</p>';
        } else {
            echo '<p>This site can be managed remotely.</p>';
        }
        echo '</div>';
    }

    function enqueue_scripts()
    {
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-dialog');
    }

    function enqueue_styles()
    {
        wp_enqueue_style('wp-jquery-ui');
        wp_enqueue_style('jquery-style', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.10.3/themes/smoothness/jquery-ui.css');
    }

    function send_email_to_admin()
    {
        if (!isset($_POST['support_mwp_message'])) {
            return;
        }
        global $current_user;
        if (empty($_POST['support_mwp_message'])) {
            $this->mwp_send_ajax_response(false, "Please enter a message.");
        }
        $mwp_worker_brand = get_option('mwp_worker_brand');
        if (empty($mwp_worker_brand['admin_email'])) {
            $this->mwp_send_ajax_response(false, "Unable to send email to admin.");
        }
        $subject       = 'New ticket for site '.get_bloginfo('url');
        $message       = <<<EOF
    Hi,
    User with a username {$current_user->user_login} has a new question:
    {$_POST['support_mwp_message']}
EOF;
        $has_been_sent = wp_mail($mwp_worker_brand['admin_email'], $subject, $message);
        if (!$has_been_sent) {
            $this->mwp_send_ajax_response(false, "Unable to send email. Please try again.");
        }
        $this->mwp_send_ajax_response(true, "Message successfully sent.");
    }

    function mwp_send_ajax_response($success = true, $message = '')
    {
        $response = json_encode(
            array(
                'success' => $success,
                'message' => $message,
            )
        );
        print $response;
        exit;
    }

    function support_page_dialog()
    {
        $mwp_worker_brand = get_option('mwp_worker_brand');

        if ($mwp_worker_brand && isset($mwp_worker_brand['text_for_client']) && ($mwp_worker_brand['text_for_client'] != '')) {
            $notification_text = $mwp_worker_brand['text_for_client'];
        }
        ?>
        <div id="support_dialog" style="display: none;">
            <?php if (!empty($notification_text)): ?>
                <div>
                    <p><?php echo $notification_text; ?></p>
                </div>
            <?php endif ?>
            <?php if ($mwp_worker_brand['email_or_link'] == 1): ?>
                <div style="margin: 19px 0 0;">
                    <form method="post" id="support_form">
                        <textarea name="support_mwp_message" id="support_message" style="width:500px;height:150px;display:block;margin-left:auto;margin-right:auto;"></textarea>
                        <button type="submit" class="button-primary" style="display:block;margin:20px auto 7px auto;border:1px solid #a1a1a1;padding:0 31px;border-radius: 4px;">Send</button>
                    </form>
                    <div id="support_response_id" style="margin-top: 14px"></div>
                    <style scoped="scoped">
                        .mwp-support-dialog.ui-dialog {
                            z-index: 300002;
                        }
                    </style>
                </div>
            <?php endif ?>
        </div>
    <?php
    }

    function support_page_script()
    {
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function ($) {
                var $dialog = $('#support_dialog');
                var $form = $('#support_form');
                var $messageContainer = $('#support_response_id');
                $form.submit(function (e) {
                    e.preventDefault();
                    var data = $(this).serialize();
                    $.ajax({
                        type: "POST",
                        url: 'index.php',
                        dataType: 'json',
                        data: data,
                        success: function (data, textStatus, jqXHR) {
                            if (data.success) {
                                $form.slideUp();
                            }
                            $messageContainer.html(data.message);
                        },
                        error: function (jqXHR, textStatus, errorThrown) {
                            $messageContainer.html('An error occurred, please try again.');
                        }
                    });
                });
                $('.toplevel_page_mwp-support').click(function (e) {
                    e.preventDefault();
                    $form.show();
                    $messageContainer.empty();
                    $dialog.dialog({
                        draggable: false,
                        resizable: false,
                        modal: true,
                        width: '530px',
                        height: 'auto',
                        title: 'Contact Support',
                        dialogClass: 'mwp-support-dialog',
                        close: function () {
                            $('#support_response_id').html('');
                            $(this).dialog("destroy");
                        }
                    });
                });
            });
        </script>
    <?php
    }

    /**
     * Add Support page on Top Menu
     */
    function add_support_page()
    {
        $mwp_worker_brand = get_option('mwp_worker_brand');
        if ($mwp_worker_brand && isset($mwp_worker_brand['text_for_client']) && ($mwp_worker_brand['text_for_client'] != '')) {
            add_menu_page(__('Support', 'wp-support'), __('Support', 'wp-support'), 'read', 'mwp-support', array(&$this, 'support_function'), '');
        }
    }

    /**
     * Support page handler
     */
    function support_function()
    {
    }


    /**
     * Remove editor from plugins&themes submenu page
     */
    function dissalow_text_editor()
    {
        $mwp_worker_brand = get_option('mwp_worker_brand');
        if ($mwp_worker_brand && isset($mwp_worker_brand['dissalow_edit']) && ($mwp_worker_brand['dissalow_edit'] == 'checked')) {
            define('DISALLOW_FILE_EDIT', true);
            define('DISALLOW_FILE_MODS', true);
        }
    }

    /**
     * Get parent blog options
     */
    private function get_parent_blog_option($option_name = '')
    {
        /** @var wpdb $wpdb */
        global $wpdb;
        $option = $wpdb->get_var($wpdb->prepare("SELECT `option_value` FROM {$wpdb->base_prefix}options WHERE option_name = '%s' LIMIT 1", $option_name));

        return $option;
    }

    /**
     * Gets an instance of the Comment class
     */
    function get_comment_instance()
    {
        if (!isset($this->comment_instance)) {
            $this->comment_instance = new MMB_Comment();
        }

        return $this->comment_instance;
    }

    /**
     * Gets an instance of MMB_Post class
     */
    function get_post_instance()
    {
        if (!isset($this->post_instance)) {
            $this->post_instance = new MMB_Post();
        }

        return $this->post_instance;
    }

    /**
     * Gets an instance of User
     */
    function get_user_instance()
    {
        if (!isset($this->user_instance)) {
            $this->user_instance = new MMB_User();
        }

        return $this->user_instance;
    }

    /**
     * Gets an instance of Security
     */
    function get_security_instance()
    {
        if (!isset($this->security_instance)) {
            $this->security_instance = new MMB_Security();
        }

        return $this->security_instance;
    }


    /**
     * Gets an instance of stats class
     */
    function get_stats_instance()
    {
        if (!isset($this->stats_instance)) {
            $this->stats_instance = new MMB_Stats();
        }

        return $this->stats_instance;
    }

    /**
     * Gets an instance of stats class
     */
    function get_backup_instance()
    {
        if (!isset($this->backup_instance)) {
            $this->backup_instance = new MMB_Backup();
        }

        return $this->backup_instance;
    }

    /**
     * Gets an instance of links class

     */
    function get_link_instance()
    {
        if (!isset($this->link_instance)) {
            $this->link_instance = new MMB_Link();
        }

        return $this->link_instance;
    }

    function get_installer_instance()
    {
        if (!isset($this->installer_instance)) {
            $this->installer_instance = new MMB_Installer();
        }

        return $this->installer_instance;
    }

    /**
     * Plugin install callback function
     * Check PHP version
     */
    function install()
    {
        /** @var wpdb $wpdb */
        global $wpdb, $_wp_using_ext_object_cache;
        $_wp_using_ext_object_cache = false;

        //delete plugin options, just in case
        if ($this->mmb_multisite != false) {
            $network_blogs = $wpdb->get_results("select `blog_id`, `site_id` from `{$wpdb->blogs}`");
            if (!empty($network_blogs)) {
                if (is_network_admin()) {
                    update_option('mmb_network_admin_install', 1);
                    foreach ($network_blogs as $details) {
                        if ($details->site_id == $details->blog_id) {
                            update_blog_option($details->blog_id, 'mmb_network_admin_install', 1);
                        } else {
                            update_blog_option($details->blog_id, 'mmb_network_admin_install', -1);
                        }

                        delete_blog_option($details->blog_id, '_worker_nossl_key');
                        delete_blog_option($details->blog_id, '_worker_public_key');
                        delete_blog_option($details->blog_id, '_action_message_id');
                    }
                } else {
                    update_option('mmb_network_admin_install', -1);
                    delete_option('_worker_nossl_key');
                    delete_option('_worker_public_key');
                    delete_option('_action_message_id');
                }
            }
        } else {
            delete_option('_worker_nossl_key');
            delete_option('_worker_public_key');
            delete_option('_action_message_id');
        }

        //delete_option('mwp_backup_tasks');
        delete_option('mwp_notifications');
        delete_option('mwp_worker_brand');
        delete_option('mwp_pageview_alerts');
        delete_option('mwp_worker_configuration');
        $path = realpath(dirname(__FILE__)."/../../worker.json");
        if (file_exists($path)) {
            $configuration     = file_get_contents($path);
            $jsonConfiguration = json_decode($configuration, true);
            if($jsonConfiguration !== NULL){
                update_option("mwp_worker_configuration", $jsonConfiguration);
            }
        }
        update_option('mmb_worker_activation_time', time());
    }
    /**
     * Saves the (modified) options into the database
     * Deprecated
     */
    function save_options($options = array())
    {
        global $_mmb_options;

        $_mmb_options = array_merge($_mmb_options, $options);
        update_option('wrksettings', $options);
    }

    /**
     * Deletes options for communication with master
     */
    function uninstall($deactivate = false)
    {
        /** @var wpdb $wpdb */
        global $current_user, $wpdb, $_wp_using_ext_object_cache;
        $_wp_using_ext_object_cache = false;

        if ($this->mmb_multisite != false) {
            $network_blogs = $wpdb->get_col("select `blog_id` from `{$wpdb->blogs}`");
            if (!empty($network_blogs)) {
                if (is_network_admin()) {
                    if ($deactivate) {
                        delete_option('mmb_network_admin_install');
                        foreach ($network_blogs as $blog_id) {
                            delete_blog_option($blog_id, 'mmb_network_admin_install');
                            delete_blog_option($blog_id, '_worker_nossl_key');
                            delete_blog_option($blog_id, '_worker_public_key');
                            delete_blog_option($blog_id, '_action_message_id');
                            delete_blog_option($blog_id, 'mwp_maintenace_mode');
                            //delete_blog_option($blog_id, 'mwp_backup_tasks');
                            delete_blog_option($blog_id, 'mwp_notifications');
                            delete_blog_option($blog_id, 'mwp_worker_brand');
                            delete_blog_option($blog_id, 'mwp_pageview_alerts');
                            delete_blog_option($blog_id, 'mwp_pageview_alerts');
                        }
                    }
                } else {
                    if ($deactivate) {
                        delete_option('mmb_network_admin_install');
                    }

                    delete_option('_worker_nossl_key');
                    delete_option('_worker_public_key');
                    delete_option('_action_message_id');
                }
            }
        } else {
            delete_option('_worker_nossl_key');
            delete_option('_worker_public_key');
            delete_option('_action_message_id');
        }

        //Delete options
        delete_option('mwp_maintenace_mode');
        //delete_option('mwp_backup_tasks');
        delete_option('mwp_notifications');
        delete_option('mwp_worker_brand');
        delete_option('mwp_pageview_alerts');
        wp_clear_scheduled_hook('mwp_backup_tasks');
        wp_clear_scheduled_hook('mwp_notifications');
        wp_clear_scheduled_hook('mwp_datasend');
        delete_option('mwp_worker_configuration');
        delete_option('mmb_worker_activation_time');
    }



    /**
     * Constructs a url (for ajax purpose)
     *
     * @param mixed $base_page
     */
    function construct_url($params = array(), $base_page = 'index.php')
    {
        $url = "$base_page?_wpnonce=".wp_create_nonce($this->slug);
        foreach ($params as $key => $value) {
            $url .= "&$key=$value";
        }

        return $url;
    }

    /**
     * Worker update
     */
    function update_worker_plugin($params)
    {
        if ($params['download_url']) {
            @include_once ABSPATH.'wp-admin/includes/file.php';
            @include_once ABSPATH.'wp-admin/includes/misc.php';
            @include_once ABSPATH.'wp-admin/includes/template.php';
            @include_once ABSPATH.'wp-admin/includes/class-wp-upgrader.php';
            @include_once ABSPATH.'wp-admin/includes/screen.php';
            @include_once ABSPATH.'wp-admin/includes/plugin.php';

            if (!$this->is_server_writable()) {
                return array(
                    'error' => 'Failed, please <a target="_blank" href="http://managewp.com/user-guide/faq/my-pluginsthemes-fail-to-update-or-i-receive-a-yellow-ftp-warning">add FTP details for automatic upgrades.</a>'
                );
            }

            ob_start();
            @unlink(dirname(__FILE__));
            $upgrader = new Plugin_Upgrader();
            $result   = $upgrader->run(
                array(
                    'package'           => $params['download_url'],
                    'destination'       => WP_PLUGIN_DIR,
                    'clear_destination' => true,
                    'clear_working'     => true,
                    'hook_extra'        => array(
                        'plugin' => 'worker/init.php'
                    )
                )
            );
            ob_end_clean();
            if (is_wp_error($result) || !$result) {
                return array(
                    'error' => 'ManageWP Worker plugin could not be updated.'
                );
            } else {
                return array(
                    'success' => 'ManageWP Worker plugin successfully updated.'
                );
            }
        }

        return array(
            'error' => 'Bad download path for worker installation file.'
        );
    }

    /**
     * Automatically logs in when called from Master
     */
    function automatic_login()
    {
        $where      = isset($_GET['mwp_goto']) ? $_GET['mwp_goto'] : false;
        $username   = isset($_GET['username']) ? $_GET['username'] : '';
        $auto_login = isset($_GET['auto_login']) ? $_GET['auto_login'] : 0;

        if (!function_exists('is_user_logged_in')) {
            include_once(ABSPATH.'wp-includes/pluggable.php');
        }

        if (($auto_login && strlen(trim($username)) && !is_user_logged_in()) || (isset($this->mmb_multisite) && $this->mmb_multisite)) {
            $signature  = base64_decode($_GET['signature']);
            $message_id = trim($_GET['message_id']);

            $auth = $this->authenticate_message($where.$message_id, $signature, $message_id);
            if ($auth === true) {

                if (!headers_sent()) {
                    header('P3P: CP="CAO PSA OUR"');
                }

                if (!defined('MMB_USER_LOGIN')) {
                    define('MMB_USER_LOGIN', true);
                }

                $siteurl = function_exists('get_site_option') ? get_site_option('siteurl') : get_option('siteurl');
                $user    = $this->mmb_get_user_info($username);
                wp_set_current_user($user->ID);

                if (!defined('COOKIEHASH') || (isset($this->mmb_multisite) && $this->mmb_multisite)) {
                    wp_cookie_constants();
                }

                wp_set_auth_cookie($user->ID);
                @mmb_worker_header();

                if ((isset($this->mmb_multisite) && $this->mmb_multisite) || isset($_REQUEST['mwpredirect'])) {
                    if (function_exists('wp_safe_redirect') && function_exists('admin_url')) {
                        wp_safe_redirect(admin_url($where));
                        exit();
                    }
                }
            } else {
                wp_die($auth['error']);
            }
        } elseif (is_user_logged_in()) {
            @mmb_worker_header();
            if (isset($_REQUEST['mwpredirect'])) {
                if (function_exists('wp_safe_redirect') && function_exists('admin_url')) {
                    wp_safe_redirect(admin_url($where));
                    exit();
                }
            }
        }
    }

    function mmb_set_auth_cookie($auth_cookie)
    {
        if (!defined('MMB_USER_LOGIN')) {
            return;
        }

        if (!defined('COOKIEHASH')) {
            wp_cookie_constants();
        }

        $_COOKIE['wordpress_'.COOKIEHASH] = $auth_cookie;
    }

    function mmb_set_logged_in_cookie($logged_in_cookie)
    {
        if (!defined('MMB_USER_LOGIN')) {
            return;
        }

        if (!defined('COOKIEHASH')) {
            wp_cookie_constants();
        }

        $_COOKIE['wordpress_logged_in_'.COOKIEHASH] = $logged_in_cookie;
    }

    function admin_actions()
    {
        add_filter('all_plugins', array($this, 'worker_replace'));
    }

    function worker_replace($all_plugins)
    {
        $replace = get_option("mwp_worker_brand");
        if (is_array($replace)) {
            if ($replace['name'] || $replace['desc'] || $replace['author'] || $replace['author_url']) {
                $all_plugins['worker/init.php']['Name']        = $replace['name'];
                $all_plugins['worker/init.php']['Title']       = $replace['name'];
                $all_plugins['worker/init.php']['Description'] = $replace['desc'];
                $all_plugins['worker/init.php']['AuthorURI']   = $replace['author_url'];
                $all_plugins['worker/init.php']['Author']      = $replace['author'];
                $all_plugins['worker/init.php']['AuthorName']  = $replace['author'];
                $all_plugins['worker/init.php']['PluginURI']   = '';
            }

            if ($replace['hide']) {
                if (!function_exists('get_plugins')) {
                    include_once(ABSPATH.'wp-admin/includes/plugin.php');
                }
                $activated_plugins = get_option('active_plugins');
                if (!$activated_plugins) {
                    $activated_plugins = array();
                }
                if (in_array('worker/init.php', $activated_plugins)) {
                    unset($all_plugins['worker/init.php']);
                }
            }
        }

        return $all_plugins;
    }

    function deactivateWorkerIfNotAddedAfterTenMinutes()
    {
        $workerActivationTime = get_option("mmb_worker_activation_time");
        if((int)$workerActivationTime + 600 > time()){
            return;
        }
        $activated_plugins = get_option('active_plugins');
        $keyWorker = array_search("worker/init.php", $activated_plugins, true);
        if($keyWorker === false){
            return;
        }
        unset($activated_plugins[$keyWorker]);
        update_option('active_plugins',$activated_plugins);
    }
}
