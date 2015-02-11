<?php
/*
Plugin Name: ManageWP - Worker
Plugin URI: https://managewp.com
Description: ManageWP Worker plugin allows you to manage your WordPress sites from one dashboard. Visit <a href="https://managewp.com">ManageWP.com</a> for more information.
Version: 4.0.5
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

/**
 * Handler for incomplete plugin installations.
 */
if (!function_exists('mwp_fail_safe')):
    /**
     * Reserved memory for fatal error handling execution context.
     */
    $GLOBALS['mwp_reserved_memory'] = str_repeat(' ', 1024 * 20);
    /**
     * If we ever get only partially upgraded due to a server error or misconfiguration,
     * attempt to disable the plugin.
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
        $title   = sprintf("ManageWP Worker deactivated on %s", $siteUrl);
        $to      = get_option('admin_email');
        $brand   = get_option('mwp_worker_brand');
        if (!empty($brand['admin_email'])) {
            $to = $brand['admin_email'];
        }

        $fullError      = print_r($lastError, 1);
        $workerSettings = get_option('wrksettings');
        $userID         = 0;
        if (!empty($workerSettings['dataown'])) {
            $userID = (int) $workerSettings['dataown'];
        }
        $body = sprintf('Worker deactivation due to an error. The site that was deactivated - %s. User email - %s (UserID: %s). The error that caused this: %s', $siteUrl, $to, $userID, $fullError);
        $mailFn('dev@managewp.com', $title, $body);

        // If we're inside a cron scope, don't attempt to hide this error.
        if (defined('DOING_CRON') && DOING_CRON) {
            return;
        }

        // If we're inside a normal request scope retry the request so user doesn't have to see an ugly error page.
        if (!empty($_SERVER['REQUEST_URI'])) {
            $siteUrl .= $_SERVER['REQUEST_URI'];
        }
        if (headers_sent()) {
            // The headers are probably sent if the PHP configuration has the 'display_errors' directive enabled. In that case try a meta redirect.
            printf('<meta http-equiv="refresh" content="0; url=%s">', htmlspecialchars($siteUrl, ENT_QUOTES));
        } else {
            header('Location: '.htmlspecialchars($siteUrl, ENT_QUOTES));
        }
        exit;
    }

    register_shutdown_function('mwp_fail_safe');
endif;

if (!class_exists('MwpWorkerResponder', false)):
    /**
     * We're not allowed to use lambda functions because this is PHP 5.2, so use a responder
     * class that's able to access the service container.
     */
    class MwpWorkerResponder
    {

        private $container;

        function __construct(MWP_ServiceContainer_Interface $container)
        {
            $this->container = $container;
        }

        function callback(Exception $e = null, MWP_Http_ResponseInterface $response = null)
        {
            if ($response !== null) {
                $responseEvent = new MWP_Event_MasterResponse($response);
                $this->container->getEventDispatcher()->dispatch(MWP_Event_Events::MASTER_RESPONSE, $responseEvent);
                $lastResponse = $responseEvent->getResponse();

                if ($lastResponse !== null) {
                    $lastResponse->send();
                    exit;
                }
            } elseif ($e !== null) {
                // Exception is thrown and the response is empty. This should never happen, so don't try to hide it.
                throw $e;
            }
        }

        /**
         * @return callable
         */
        public function getCallback()
        {
            return array($this, 'callback');
        }
    }
endif;

if (!function_exists('mwp_container')):
    /**
     * @return MWP_ServiceContainer_Interface
     */
    function mwp_container()
    {
        static $container;

        if ($container === null) {
            $parameters = (array) get_option('mwp_container_parameters', array());
            $container  = new MWP_ServiceContainer_Production(array(
                    'worker_realpath' => __FILE__,
                    'worker_basename' => 'worker/init.php',
                    'worker_version'  => $GLOBALS['MMB_WORKER_VERSION'],
                    'worker_revision' => $GLOBALS['MMB_WORKER_REVISION'],
                ) + $parameters);
        }

        return $container;
    }
endif;

if (!function_exists('mwp_uninstall')) {
    function mwp_uninstall()
    {
        $loaderName = '0-worker.php';
        try {
            $mustUsePluginDir = rtrim(WPMU_PLUGIN_DIR, '/');
            $loaderPath       = $mustUsePluginDir.'/'.$loaderName;

            if (!file_exists($loaderPath)) {
                return;
            }

            $removed = @unlink($loaderPath);

            if (!$removed) {
                $error = error_get_last();
                throw new Exception(sprintf('Unable to remove loader: %s', $error['message']));
            }
        } catch (Exception $e) {
            mwp_logger()->error('Unable to remove loader', array('exception' => $e));
        }
    }
}

if (!function_exists('mwp_init')):
    function mwp_init()
    {
        // Ensure PHP version compatibility.
        if (version_compare(PHP_VERSION, '5.2', '<')) {
            trigger_error("ManageWP Worker plugin requires PHP 5.2 or higher.", E_USER_ERROR);
            exit;
        }

        // Register the autoloader that loads everything except the Google namespace.
        if (version_compare(PHP_VERSION, '5.3', '<')) {
            spl_autoload_register('mwp_autoload');
        } else {
            // The prepend parameter was added in PHP 5.3.0
            spl_autoload_register('mwp_autoload', true, true);
        }

        $GLOBALS['MMB_WORKER_VERSION']  = '4.0.5';
        $GLOBALS['MMB_WORKER_REVISION'] = '2015-02-11 00:00:00';
        $GLOBALS['mmb_plugin_dir']      = WP_PLUGIN_DIR.'/'.basename(dirname(__FILE__));
        $GLOBALS['_mmb_item_filter']    = array();
        $GLOBALS['mmb_core']            = $core = $GLOBALS['mmb_core_backup'] = new MMB_Core();

        $siteUrl = function_exists('get_site_option') ? get_site_option('siteurl') : get_option('siteurl');
        define('MMB_XFRAME_COOKIE', 'wordpress_'.md5($siteUrl).'_xframe');

        define('MWP_BACKUP_DIR', WP_CONTENT_DIR.'/managewp/backups');
        define('MWP_DB_DIR', MWP_BACKUP_DIR.'/mwp_db');

        add_filter('mmb_stats_filter', 'mmb_get_extended_info');
        add_action('plugins_loaded', 'mwp_return_core_reference', 1);
        add_filter('cron_schedules', 'mmb_more_reccurences');
        add_action('mmb_remote_upload', 'mmb_call_scheduled_remote_upload');
        add_action('mwp_datasend', 'mwp_datasend');
        add_action('init', 'mmb_plugin_actions', 99999);
        add_filter('install_plugin_complete_actions', 'mmb_iframe_plugins_fix');
        add_filter('comment_edit_redirect', 'mwb_edit_redirect_override');

        // Datasend cron.
        if (!wp_next_scheduled('mwp_datasend')) {
            wp_schedule_event(time(), 'threehours', 'mwp_datasend');
        }

        // Register updater hooks.
        MMB_Updater::register();

        // Plugin management hooks.
        register_activation_hook(__FILE__, array($core, 'install'));
        register_deactivation_hook(__FILE__, array($core, 'deactivate'));
        register_uninstall_hook(__FILE__, 'mwp_uninstall');

        // Don't send the "X-Frame-Options: SAMEORIGIN" header if we're logging in inside an iframe.
        if (isset($_COOKIE[MMB_XFRAME_COOKIE])) {
            remove_action('admin_init', 'send_frame_options_header');
            remove_action('login_init', 'send_frame_options_header');
        }

        // Remove legacy scheduler.
        if (wp_next_scheduled('mwp_backup_tasks')) {
            wp_clear_scheduled_hook('mwp_backup_tasks');
        }

        mwp_set_plugin_priority();

        $request   = MWP_Worker_Request::createFromGlobals();
        $container = mwp_container();
        $responder = new MwpWorkerResponder($container);

        $kernel = new MWP_Worker_Kernel($container);
        $kernel->handleRequest($request, $responder->getCallback(), true);
    }

    require_once dirname(__FILE__).'/functions.php';

    mwp_init();
endif;
