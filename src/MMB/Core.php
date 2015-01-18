<?php

/*************************************************************
 * core.class.php
 * Upgrade Plugins
 * Copyright (c) 2011 Prelovac Media
 * www.prelovac.com
 **************************************************************/
class MMB_Core extends MMB_Helper
{

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

    public $user_instance;

    public $backup_instance;

    public $installer_instance;

    public $mmb_multisite;

    public $network_admin_install;

    private $action_call;

    private $action_params;

    private $mmb_init_actions;

    public function __construct()
    {
        global $blog_id, $_mmb_item_filter, $_mmb_options;

        $_mmb_options        = get_option('wrksettings');
        $_mmb_options        = !empty($_mmb_options) ? $_mmb_options : array();

        if (is_multisite()) {
            $this->mmb_multisite         = $blog_id;
            $this->network_admin_install = get_option('mmb_network_admin_install');
        } else {
            $this->mmb_multisite         = false;
            $this->network_admin_install = null;
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

        $_mmb_item_filter['pre_init_stats'] = array('core_update', 'hit_counter', 'comments', 'backups', 'posts', 'drafts', 'scheduled', 'site_statistics');
        $_mmb_item_filter['get']            = array('updates', 'errors');

        $this->mmb_init_actions = array();

        add_action('init', array(&$this, 'mmb_remote_action'), 9999);
        add_action('setup_theme', 'mmb_run_forked_action', 1);

        if (!get_option('_worker_nossl_key') && !get_option('_worker_public_key')) {
            add_action('init', array(&$this, 'deactivateWorkerIfNotAddedAfterTenMinutes'));
        }
    }

    public function mmb_remote_action()
    {
        if ($this->action_call != null) {
            $params = isset($this->action_params) && $this->action_params != null ? $this->action_params : array();
            call_user_func($this->action_call, $params);
        }
    }

    /**
     * Add notice to network admin dashboard for security reasons
     */
    public function network_admin_notice()
    {
        global $status, $page, $s;
        $context              = $status;
        $plugin               = 'worker/init.php';
        $nonce                = wp_create_nonce('deactivate-plugin_'.$plugin);
        $actions              = 'plugins.php?action=deactivate&amp;plugin='.urlencode($plugin).'&amp;plugin_status='.$context.'&amp;paged='.$page.'&amp;s='.$s.'&amp;_wpnonce='.$nonce;
        $configurationService = new MWP_Configuration_Service();
        $configuration        = $configurationService->getConfiguration();
        $notice               = $configuration->getNetworkNotice();
        $notice               = str_replace("{deactivate_url}", $actions, $notice);
        echo $notice;
    }

    /**
     * Add notice to admin dashboard for security reasons
     */
    public function admin_notice()
    {
        global $status, $page, $s;
        $context              = $status;
        $plugin               = 'worker/init.php';
        $nonce                = wp_create_nonce('deactivate-plugin_'.$plugin);
        $actions              = 'plugins.php?action=deactivate&amp;plugin='.urlencode($plugin).'&amp;plugin_status='.$context.'&amp;paged='.$page.'&amp;s='.$s.'&amp;_wpnonce='.$nonce;
        $configurationService = new MWP_Configuration_Service();
        $configuration        = $configurationService->getConfiguration();
        $notice               = $configuration->getNotice();
        $deactivateText       = $configuration->getDeactivateText();
        if ($this->mmb_multisite && $this->network_admin_install != '1') {
            $deactivateTextLink = ''.$deactivateText;
        } else {
            $deactivateTextLink = '<a href="'.$actions.'" class="mwp_text_notice">'.$deactivateText.'</a>';
        }
        $notice = str_replace("{deactivate_text}", $deactivateTextLink, $notice);

        echo $notice;
    }

    public function mwp_send_ajax_response($success = true, $message = '')
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
    public function get_comment_instance()
    {
        if (!isset($this->comment_instance)) {
            $this->comment_instance = new MMB_Comment();
        }

        return $this->comment_instance;
    }

    /**
     * Gets an instance of MMB_Post class
     */
    public function get_post_instance()
    {
        if (!isset($this->post_instance)) {
            $this->post_instance = new MMB_Post();
        }

        return $this->post_instance;
    }

    /**
     * Gets an instance of User
     */
    public function get_user_instance()
    {
        if (!isset($this->user_instance)) {
            $this->user_instance = new MMB_User();
        }

        return $this->user_instance;
    }

    /**
     * Gets an instance of stats class
     */
    public function get_stats_instance()
    {
        if (!isset($this->stats_instance)) {
            $this->stats_instance = new MMB_Stats();
        }

        return $this->stats_instance;
    }

    /**
     * Gets an instance of stats class
     */
    public function get_backup_instance()
    {
        if (!isset($this->backup_instance)) {
            $this->backup_instance = new MMB_Backup();
        }

        return $this->backup_instance;
    }

    public function get_installer_instance()
    {
        if (!isset($this->installer_instance)) {
            $this->installer_instance = new MMB_Installer();
        }

        return $this->installer_instance;
    }

    public function buildLoaderContent($pluginBasename)
    {
        $loader = <<<EOF
<?php

/*
Plugin Name: ManageWP - Worker Loader
Plugin URI: https://managewp.com
Description: This is automatically generated by the ManageWP Worker plugin to increase performance and reliability. It is automatically disabled when disabling the main plugin.
Author: ManageWP
Author URI: https://managewp.com
License: GPL2
*/

if (!function_exists('untrailingslashit') || !defined('WP_PLUGIN_DIR')) {
    // WordPress is probably not bootstrapped.
    exit;
}

if (file_exists(untrailingslashit(WP_PLUGIN_DIR).'/$pluginBasename')) {
    if (in_array('$pluginBasename', (array) get_option('active_plugins'))) {
        \$GLOBALS['mwp_is_mu'] = true;
        include_once untrailingslashit(WP_PLUGIN_DIR).'/$pluginBasename';
    }
}

EOF;

        return $loader;
    }

    public function registerMustUse($loaderName, $loaderContent)
    {
        $mustUsePluginDir = rtrim(WPMU_PLUGIN_DIR, '/');
        $loaderPath       = $mustUsePluginDir.'/'.$loaderName;

        if (file_exists($loaderPath) && md5($loaderContent) === md5_file($loaderPath)) {
            return;
        }

        if (!is_dir($mustUsePluginDir)) {
            $dirMade = @mkdir($mustUsePluginDir);

            if (!$dirMade) {
                $error = error_get_last();
                throw new Exception(sprintf('Unable to create loader directory: %s', $error['message']));
            }
        }

        $loaderWritten = @file_put_contents($loaderPath, $loaderContent);

        if (!$loaderWritten) {
            $error = error_get_last();
            throw new Exception(sprintf('Unable to write loader: %s', $error['message']));
        }
    }

    /**
     * Plugin install callback function
     * Check PHP version
     */
    public function install()
    {
        try {
            $this->registerMustUse('0-worker.php', $this->buildLoaderContent('worker/init.php'));
        } catch (Exception $e) {
            mwp_logger()->error('Unable to write ManageWP loader', array('exception' => $e));
        }

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

        delete_option('mwp_notifications');
        delete_option('mwp_worker_brand');
        delete_option('mwp_pageview_alerts');
        delete_option('mwp_worker_configuration');
        $path = realpath(dirname(__FILE__)."/../../worker.json");
        if (file_exists($path)) {
            $configuration     = file_get_contents($path);
            $jsonConfiguration = json_decode($configuration, true);
            if ($jsonConfiguration !== null) {
                update_option("mwp_worker_configuration", $jsonConfiguration);
            }
        }
        update_option('mmb_worker_activation_time', time());
    }

    /**
     * Saves the (modified) options into the database
     * Deprecated
     */
    public function save_options($options = array())
    {
        global $_mmb_options;

        $_mmb_options = array_merge($_mmb_options, $options);
        update_option('wrksettings', $options);
    }

    /**
     * Deletes options for communication with master
     */
    public function deactivate($deactivate = false)
    {
        /** @var wpdb $wpdb */
        mwp_uninstall();
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
    public function construct_url($params = array(), $base_page = 'index.php')
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
    public function update_worker_plugin($params)
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
                    'error' => 'Failed, please <a target="_blank" href="http://managewp.com/user-guide/faq/my-pluginsthemes-fail-to-update-or-i-receive-a-yellow-ftp-warning">add FTP details for automatic upgrades.</a>',
                );
            }

            mwp_load_required_components();

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
                        'plugin' => 'worker/init.php',
                    ),
                )
            );
            ob_end_clean();
            if (is_wp_error($result) || !$result) {
                return array(
                    'error' => 'ManageWP Worker plugin could not be updated.',
                );
            } else {
                return array(
                    'success' => 'ManageWP Worker plugin successfully updated.',
                );
            }
        }

        return array(
            'error' => 'Bad download path for worker installation file.',
        );
    }

    public function deactivateWorkerIfNotAddedAfterTenMinutes()
    {
        $workerActivationTime = get_option("mmb_worker_activation_time");
        if ((int) $workerActivationTime + 600 > time()) {
            return;
        }
        $activated_plugins = get_option('active_plugins');
        $keyWorker         = array_search("worker/init.php", $activated_plugins, true);
        if ($keyWorker === false) {
            return;
        }
        unset($activated_plugins[$keyWorker]);
        update_option('active_plugins', $activated_plugins);
    }
}
