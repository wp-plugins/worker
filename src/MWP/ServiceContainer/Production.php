<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_ServiceContainer_Production extends MWP_ServiceContainer_Abstract
{
    /**
     * @return MWP_WordPress_Context
     */
    protected function createWordPressContext()
    {
        return new MWP_WordPress_Context();
    }

    /**
     * @return Symfony_EventDispatcher_EventDispatcherInterface
     */
    protected function createEventDispatcher()
    {
        $dispatcher = new Symfony_EventDispatcher_EventDispatcher();

        $dispatcher->addSubscriber(new MWP_EventListener_PublicRequest_BrandContactSupport($this->getWordPressContext(), $this->getBrand(), $this->getRequestStack()));
        $dispatcher->addSubscriber(new MWP_EventListener_PublicRequest_DisableEditor($this->getWordPressContext(), $this->getBrand()));
        $dispatcher->addSubscriber(new MWP_EventListener_PublicRequest_SetPluginInfo($this->getWordPressContext(), $this->getBrand()));
        $dispatcher->addSubscriber(new MWP_EventListener_PublicRequest_SetHitCounter($this->getWordPressContext(), $this->getHitCounter(), $this->getParameter('hit_counter_blacklisted_ips'), $this->getParameter('hit_counter_blacklisted_user_agents')));
        $dispatcher->addSubscriber(new MWP_EventListener_PublicRequest_AutomaticLogin($this->getWordPressContext(), $this->getNonceManager(), $this->getSigner(), $this->getConfiguration()));

        $dispatcher->addSubscriber(new MWP_EventListener_MasterRequest_VerifyConnectionInfo($this->getWordPressContext(), $this->getSigner()));
        $dispatcher->addSubscriber(new MWP_EventListener_MasterRequest_VerifyNonce($this->getNonceManager()));
        $dispatcher->addSubscriber(new MWP_EventListener_MasterRequest_AuthenticateRequest($this->getConfiguration(), $this->getSigner()));
        $dispatcher->addSubscriber(new MWP_EventListener_MasterRequest_SetErrorHandler($this->getErrorLogger(), $this->getErrorHandler(), $this->getRequestStack(), $this->getResponseCallback(), $this, $this->getParameter('log_errors'), $this->getParameter('fatal_error_reserved_memory_size')));
        $dispatcher->addSubscriber(new MWP_EventListener_MasterRequest_AttachJsonMessageHandler($this->getLogger(), $this->getJsonMessageHandler()));
        $dispatcher->addSubscriber(new MWP_EventListener_MasterRequest_RemoveUsernameParam());
        $dispatcher->addSubscriber(new MWP_EventListener_MasterRequest_AuthenticateLegacyRequest($this->getConfiguration()));

        $dispatcher->addSubscriber(new MWP_EventListener_ActionRequest_SetCurrentUser($this->getWordPressContext()));
        $dispatcher->addSubscriber(new MWP_EventListener_ActionRequest_SetSettings($this->getWordPressContext(), $this->getSystemEnvironment()));
        $dispatcher->addSubscriber(new MWP_EventListener_ActionRequest_LogRequest($this->getLogger()));

        $dispatcher->addSubscriber(new MWP_EventListener_ActionException_SetExceptionData());

        $dispatcher->addSubscriber(new MWP_EventListener_ActionResponse_SetActionData());
        $dispatcher->addSubscriber(new MWP_EventListener_ActionResponse_SetLegacyWebsiteConnectionData($this->getWordPressContext()));
        $dispatcher->addSubscriber(new MWP_EventListener_ActionResponse_ChainState($this));
        $dispatcher->addSubscriber(new MWP_EventListener_ActionResponse_SetLegacyPhpExecutionData());

        $dispatcher->addSubscriber(new MWP_EventListener_MasterResponse_LogResponse($this->getLogger()));

        $dispatcher->addSubscriber(new MWP_EventListener_FixCompatibility($this->getWordPressContext()));

        $dispatcher->addSubscriber(new MWP_EventListener_EncodeMasterResponse());

        return $dispatcher;
    }

    protected function createRequestStack()
    {
        return new MWP_Worker_RequestStack();
    }

    /**
     * @return MWP_Action_Registry
     */
    protected function createActionRegistry()
    {
        $mapper = new MWP_Action_Registry();

        $mapper->addDefinition('do_upgrade', new MWP_Action_Definition('mmb_do_upgrade', array('hook_name' => 'init', 'hook_priority' => 9999)));
        $mapper->addDefinition('get_stats', new MWP_Action_Definition('mmb_stats_get', array('hook_name' => 'init', 'hook_priority' => 9999)));
        $mapper->addDefinition('remove_site', new MWP_Action_Definition('mmb_remove_site'));
        $mapper->addDefinition('backup_clone', new MWP_Action_Definition('mmb_backup_now'));
        $mapper->addDefinition('restore', new MWP_Action_Definition('mmb_restore_now'));
        $mapper->addDefinition('create_post', new MWP_Action_Definition('mmb_post_create', array('hook_name' => 'init', 'hook_priority' => 9999)));
        $mapper->addDefinition('update_worker', new MWP_Action_Definition('mmb_update_worker_plugin'));
        $mapper->addDefinition('change_post_status', new MWP_Action_Definition('mmb_change_post_status', array('hook_name' => 'init', 'hook_priority' => 9999)));
        $mapper->addDefinition('install_addon', new MWP_Action_Definition('mmb_install_addon', array('hook_name' => 'init', 'hook_priority' => 9999)));
        $mapper->addDefinition('get_comments', new MWP_Action_Definition('mmb_get_comments', array('hook_name' => 'init', 'hook_priority' => 9999)));
        $mapper->addDefinition('bulk_action_comments', new MWP_Action_Definition('mmb_bulk_action_comments', array('hook_name' => 'init', 'hook_priority' => 9999)));
        $mapper->addDefinition('replyto_comment', new MWP_Action_Definition('mmb_reply_comment', array('hook_name' => 'init', 'hook_priority' => 9999)));
        $mapper->addDefinition('add_user', new MWP_Action_Definition('mmb_add_user', array('hook_name' => 'init', 'hook_priority' => 9999)));
        $mapper->addDefinition('scheduled_backup', new MWP_Action_Definition('mmb_scheduled_backup'));
        $mapper->addDefinition('run_task', new MWP_Action_Definition('mmb_run_task_now'));
        $mapper->addDefinition('execute_php_code', new MWP_Action_Definition('mmb_execute_php_code'));
        $mapper->addDefinition('delete_backup', new MWP_Action_Definition('mmm_delete_backup'));
        $mapper->addDefinition('remote_backup_now', new MWP_Action_Definition('mmb_remote_backup_now'));
        $mapper->addDefinition('get_users', new MWP_Action_Definition('mmb_get_users', array('hook_name' => 'init', 'hook_priority' => 9999)));
        $mapper->addDefinition('edit_users', new MWP_Action_Definition('mmb_edit_users', array('hook_name' => 'init', 'hook_priority' => 9999)));
        $mapper->addDefinition('get_posts', new MWP_Action_Definition('mmb_get_posts', array('hook_name' => 'init', 'hook_priority' => 9999)));
        $mapper->addDefinition('delete_post', new MWP_Action_Definition('mmb_delete_post', array('hook_name' => 'init', 'hook_priority' => 9999)));
        $mapper->addDefinition('delete_posts', new MWP_Action_Definition('mmb_delete_posts', array('hook_name' => 'init', 'hook_priority' => 9999)));
        $mapper->addDefinition('get_pages', new MWP_Action_Definition('mmb_get_pages', array('hook_name' => 'init', 'hook_priority' => 9999)));
        $mapper->addDefinition('delete_page', new MWP_Action_Definition('mmb_delete_page', array('hook_name' => 'init', 'hook_priority' => 9999)));
        $mapper->addDefinition('get_plugins_themes', new MWP_Action_Definition('mmb_get_plugins_themes', array('hook_name' => 'init', 'hook_priority' => 9999)));
        $mapper->addDefinition('edit_plugins_themes', new MWP_Action_Definition('mmb_edit_plugins_themes', array('hook_name' => 'init', 'hook_priority' => 9999)));
        $mapper->addDefinition('worker_brand', new MWP_Action_Definition('mmb_worker_brand'));
        $mapper->addDefinition('maintenance', new MWP_Action_Definition('mmb_maintenance_mode'));
        $mapper->addDefinition('get_autoupdate_plugins_themes', new MWP_Action_Definition('mmb_get_autoupdate_plugins_themes'));
        $mapper->addDefinition('edit_autoupdate_plugins_themes', new MWP_Action_Definition('mmb_edit_autoupdate_plugins_themes'));
        $mapper->addDefinition('ping_backup', new MWP_Action_Definition('mwp_ping_backup'));
        $mapper->addDefinition('cleanup_delete', new MWP_Action_Definition('cleanup_delete_worker', array('hook_name' => 'init', 'hook_priority' => 9999)));
        $mapper->addDefinition('backup_req', new MWP_Action_Definition('mmb_get_backup_req'));
        $mapper->addDefinition('change_comment_status', new MWP_Action_Definition('mmb_change_comment_status', array('hook_name' => 'init', 'hook_priority' => 9999)));
        $mapper->addDefinition('get_state', new MWP_Action_Definition(array('MWP_Action_GetState', 'execute')));
        $mapper->addDefinition('add_site', new MWP_Action_Definition(array('MWP_Action_ConnectWebsite', 'execute')));

        return $mapper;
    }

    protected function createUserQuery()
    {
        return new MWP_WordPress_Query_User($this->getWordPressContext());
    }

    protected function createPostQuery()
    {
        return new MWP_WordPress_Query_Post($this->getWordPressContext());
    }

    protected function createCommentQuery()
    {
        return new MWP_WordPress_Query_Comment($this->getWordPressContext());
    }

    protected function createPluginProvider()
    {
        return new MWP_WordPress_Provider_Plugin($this->getWordPressContext());
    }

    protected function createThemeProvider()
    {
        return new MWP_WordPress_Provider_Theme($this->getWordPressContext());
    }

    protected function createWorkerBrand()
    {
        return new MWP_Worker_Brand($this->getWordPressContext());
    }

    protected function createAutoUpdateManager()
    {
        return new MWP_Updater_AutoUpdateManager($this->getWordPressContext());
    }

    /**
     * @return MWP_Updater_UpdateManager
     */
    protected function createUpdateManager()
    {
        return new MWP_Updater_UpdateManager($this->getWordPressContext());
    }

    /**
     * @return MWP_Signer_Interface
     */
    public function createSigner()
    {
        if ($this->getParameter('prefer_phpseclib')) {
            return MWP_Signer_Factory::createPhpSecLibSigner();
        }

        return MWP_Signer_Factory::createSigner();
    }

    /**
     * @return MWP_Crypter_Interface
     */
    public function createCrypter()
    {
        if ($this->getParameter('prefer_phpseclib')) {
            return MWP_Crypter_Factory::createPhpSecLibCrypter();
        }

        return MWP_Crypter_Factory::createCrypter();
    }

    /**
     * @return MWP_Security_NonceManager
     */
    protected function createNonceManager()
    {
        return new MWP_Security_NonceManager($this->getWordPressContext());
    }

    /**
     * @return MWP_Worker_Configuration
     */
    protected function createConfiguration()
    {
        return new MWP_Worker_Configuration($this->getWordPressContext());
    }

    protected function createLogger()
    {
        $handlers = array();

        if ($this->getParameter('log_file')) {
            $fileHandler = new Monolog_Handler_StreamHandler(fopen(dirname(__FILE__).'/../../../'.$this->getParameter('log_file'), 'a'));
            $fileHandler->setFormatter(new Monolog_Formatter_HtmlFormatter());
            $handlers[] = $fileHandler;
        }

        if ($this->getParameter('gelf_server')) {
            $publisher  = new Gelf_Publisher($this->getParameter('gelf_server'), $this->getParameter('gelf_port') ? $this->getParameter('gelf_port') : Gelf_Publisher::GRAYLOG2_DEFAULT_PORT);
            $handlers[] = new Monolog_Handler_LegacyGelfHandler($publisher);
        }

        $processors = array();
        if (count($handlers) > 0) {
            // We do have some loggers set up.
            $processors += array(
                array(new Monolog_Processor_MemoryUsageProcessor(), 'callback'),
                array(new Monolog_Processor_MemoryPeakUsageProcessor(), 'callback'),
                array(new Monolog_Processor_IntrospectionProcessor(), 'callback'),
                array(new Monolog_Processor_PsrLogMessageProcessor(), 'callback'),
                array(new Monolog_Processor_UidProcessor(), 'callback'),
                array(new Monolog_Processor_WebProcessor(), 'callback'),
                array(new MWP_Monolog_Processor_TimeUsageProcessor(), 'callback'),
                array(new MWP_Monolog_Processor_ExceptionProcessor(), 'callback'),
                array(new MWP_Monolog_Processor_ProcessProcessor(), 'callback'),
            );
        } else {
            $handlers[] = new Monolog_Handler_NullHandler();
        }

        $logger = new Monolog_Logger('worker', $handlers, $processors);

        return $logger;
    }

    protected function createResponseCallback()
    {
        return new MWP_Worker_ResponseCallback();
    }

    protected function createErrorHandler()
    {
        return new Monolog_ErrorHandler($this->getErrorLogger());
    }

    /**
     * @return Symfony_Process_ExecutableFinder
     */
    protected function createExecutableFinder()
    {
        $finder = new Symfony_Process_ExecutableFinder();

        $db = $this->getWordPressContext()->getDb();

        if (is_callable(array($db, 'get_var'))) {
            $basePath = rtrim($db->get_var('select @@basedir'), '/\\');
            if ($basePath) {
                $basePath .= '/bin';
                $finder->addExtraDir($basePath);
            }
        }

        return $finder;
    }

    /**
     * @return MWP_Extension_HitCounter
     */
    protected function createHitCounter()
    {
        $counter = new MWP_Extension_HitCounter($this->getWordPressContext(), 14);

        return $counter;
    }

    /**
     * @return MWP_Monolog_Handler_JsonMessageHandler
     */
    protected function createJsonMessageHandler()
    {
        $handler = new MWP_Monolog_Handler_JsonMessageHandler($this->getParameter('message_minimum_level'));
        $handler->setPadLength($this->getParameter('message_pad_length'));

        return $handler;
    }

    /**
     * @return Monolog_Logger
     */
    protected function createErrorLogger()
    {
        $processors = array(
            array(new Monolog_Processor_PsrLogMessageProcessor(), 'callback'),
        );

        $logger = new Monolog_Logger('worker.error', array(), $processors);

        return $logger;
    }

    /**
     * @return MWP_System_Environment
     */
    protected function createSystemEnvironment()
    {
        return new MWP_System_Environment();
    }
}
