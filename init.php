<?php
/*
Plugin Name: ManageWP - Worker
Plugin URI: https://managewp.com
Description: ManageWP Worker plugin allows you to manage your WordPress sites from one dashboard. Visit <a href="https://managewp.com">ManageWP.com</a> for more information.
Version: 4.1.6
Author: ManageWP
Author URI: https://managewp.com
License: GPL2
*/

/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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

        // Signal ourselves that the installation is corrupt.
        update_option('mwp_recovering', time());

        $siteUrl = get_option('siteurl');
        $title   = sprintf("ManageWP Worker corrupt on %s", $siteUrl);
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
        $body = sprintf("Corrupt ManageWP Worker v%s installation detected. Site URL in question is %s. User email is %s (User ID: %s). Attempting recovery process at %s. The error that caused this:\n\n<pre>%s</pre>", $GLOBALS['MMB_WORKER_VERSION'], $siteUrl, $to, $userID, date('Y-m-d H:i:s'), $fullError);
        mail('dev@managewp.com', $title, $body, "Content-Type: text/html");

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

        private $responseSent = false;

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
                    if (!$this->responseSent) {
                        // This looks pretty ugly, but the "execute PHP" function handles fatal errors and wraps them
                        // in a valid action response. That fatal error may also be handled by the global fatal error
                        // handler, which also wraps the error in a response. We keep the state in this class, so we
                        // don't send a worker response twice, first time as an action response, second time as a
                        // global response.
                        // If this is to be removed, simply remove fatal error handling from the "execute PHP" action.
                        $lastResponse->send();
                        $this->responseSent = true;
                    }
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

if (!class_exists('MwpRecoveryKit', false)):
    class MwpRecoveryKit
    {
        public function recover()
        {
            ignore_user_abort(true);
            $dirName = realpath(dirname(__FILE__));
            $checksumResponse = wp_remote_get(sprintf('http://s3-us-west-2.amazonaws.com/mwp-orion-public/worker/raw/%s/checksum.json', $GLOBALS['MMB_WORKER_VERSION']));
            if ($checksumResponse instanceof WP_Error){
                $this->selfDeactivate('Unable to download checksum.json: '.$checksumResponse->get_error_message());

                return;
            }
            if ($checksumResponse['response']['code'] !== 200) {
                $this->selfDeactivate('Unable to download checksum.json: invalid status code ('.$checksumResponse['response']['code'].')');

                return;
            }

            $filesAndChecksums = json_decode($checksumResponse['body'], true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->selfDeactivate(sprintf('Error while parsing checksum.json [%s]: %s', json_last_error(), json_last_error_msg()));

                return;
            }

            require_once ABSPATH.'wp-admin/includes/file.php';

            $options = array();

            $fsMethod = get_filesystem_method();
            if ($fsMethod !== 'direct') {
                ob_start();
                $options = request_filesystem_credentials('');
                ob_end_clean();
            }

            /** @var WP_Filesystem_Base $fs */
            WP_Filesystem($options);
            $fs = $GLOBALS['wp_filesystem'];
            $connected = $fs->connect();

            if (!$connected) {
                $this->selfDeactivate('Unable to connect to the file system', error_get_last());
            }

            // First create directories and remove them from the array.
            // Must be done before shuffling because of nesting.
            foreach ($filesAndChecksums as $relativePath => $checksum) {
                if ($checksum !== '') {
                    continue;
                }
                unset ($filesAndChecksums[$relativePath]);
                $absolutePath = $dirName.'/'.$relativePath;
                // Directories are ordered first.
                if (!is_dir($absolutePath)) {
                    $fs->mkdir($absolutePath);
                }
            }

            // Check and recreate files. Shuffle them so multiple running instances have a smaller collision.
            $recoveredFiles = array();
            foreach ($this->shuffleAssoc($filesAndChecksums) as $relativePath => $checksum) {
                $absolutePath = $dirName.'/'.$relativePath;
                if (file_exists($absolutePath) && md5_file($absolutePath) === $checksum) {
                    continue;
                }
                $fileUrl      = sprintf('http://s3-us-west-2.amazonaws.com/mwp-orion-public/worker/raw/%s/%s', $GLOBALS['MMB_WORKER_VERSION'], $relativePath);
                $response = wp_remote_get($fileUrl);
                if ($response instanceof WP_Error){
                    $this->selfDeactivate('Unable to download file '.$fileUrl.': '.$response->get_error_message());

                    return;
                }
                if ($response['response']['code'] !== 200) {
                    $this->selfDeactivate('Unable to download file '.$fileUrl.': invalid status code ('.$response['response']['code'].')');

                    return;
                }
                $saved = $fs->put_contents($fs->find_folder(WP_PLUGIN_DIR).'worker/'.$relativePath, $response['body']);

                if (!$saved) {
                    $this->selfDeactivate('File saving failed.', error_get_last());

                    return;
                }

                $recoveredFiles[] = $relativePath;
            }

            // Recovery complete.
            delete_option('mwp_recovering');
            mail('dev@managewp.com', sprintf("ManageWP Worker recovered on %s", get_option('siteurl')), sprintf("%d files successfully recovered in this recovery fork of ManageWP Worker v%s. Filesystem method used was <code>%s</code>.\n\n<pre>%s</pre>", count($recoveredFiles), $GLOBALS['MMB_WORKER_VERSION'], $fsMethod, implode("\n", $recoveredFiles)), 'Content-Type: text/html');
        }

        private function shuffleAssoc($array)
        {
            $keys = array_keys($array);
            shuffle($keys);
            $shuffled = array();
            foreach ($keys as $key) {
                $shuffled[$key] = $array[$key];
            }

            return $shuffled;
        }

        private function selfDeactivate($reason, array $lastError = null)
        {
            $activePlugins = get_option('active_plugins');
            $workerIndex   = array_search(plugin_basename(__FILE__), $activePlugins);
            if ($workerIndex === false) {
                // Plugin is not yet enabled, possibly in activation context.
                return;
            }
            unset($activePlugins[$workerIndex]);
            // Reset indexes.
            $activePlugins = array_values($activePlugins);

            delete_option('mwp_recovering');
            update_option('active_plugins', $activePlugins);

            $lastErrorMessage = '';
            if ($lastError !== null) {
                $lastErrorMessage = "\n\nLast error: ".$lastError['message'];
            }
            mail('dev@managewp.com', sprintf("ManageWP Worker recovery aborted on %s", get_option('siteurl')), sprintf("ManageWP Worker v%s. Reason: %s%s", $GLOBALS['MMB_WORKER_VERSION'], $reason, $lastErrorMessage));
        }
    }
endif;

if (!function_exists('mwp_init')):
    function mwp_init()
    {
        $GLOBALS['MMB_WORKER_VERSION']  = '4.1.6';
        $GLOBALS['MMB_WORKER_REVISION'] = '2015-05-15 00:00:00';

        // Ensure PHP version compatibility.
        if (version_compare(PHP_VERSION, '5.2', '<')) {
            trigger_error("ManageWP Worker plugin requires PHP 5.2 or higher.", E_USER_ERROR);
            exit;
        }

        if (get_option('mwp_recovering')) {
            $recoveryKey = get_transient('mwp_recovery_key');
            if (!$passedRecoveryKey = filter_input(INPUT_POST, 'mwp_recovery_key')) {
                $recoveryKey = md5(uniqid('', true));
                set_transient('mwp_recovery_key', $recoveryKey, time() + 604800); // 1 week.
                wp_remote_post(get_bloginfo('wpurl'), array(
                    'reject_unsafe_urls' => false,
                    'body'               => array(
                        'mwp_recovery_key' => $recoveryKey,
                    ),
                    'timeout'            => 0.01,
                    'cookies'            => array(
                        'XDEBUG_SESSION' => 'PHPSTORM',
                    ),
                ));
            } else {
                if ($recoveryKey !== $passedRecoveryKey) {
                    return;
                }
                delete_transient('mwp_recovery_key');
                $repairKit = new MwpRecoveryKit();
                $repairKit->recover();
            }

            return;
        }

        // Register the autoloader that loads everything except the Google namespace.
        if (version_compare(PHP_VERSION, '5.3', '<')) {
            spl_autoload_register('mwp_autoload');
        } else {
            // The prepend parameter was added in PHP 5.3.0
            spl_autoload_register('mwp_autoload', true, true);
        }

        $GLOBALS['mmb_plugin_dir']   = WP_PLUGIN_DIR.'/'.basename(dirname(__FILE__));
        $GLOBALS['_mmb_item_filter'] = array();
        $GLOBALS['mmb_core']         = $core = $mmb_core_backup = new MMB_Core();

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
        register_uninstall_hook(dirname(__FILE__).'/functions.php', 'mwp_uninstall');

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
