<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

interface MWP_ServiceContainer_Interface
{
    /**
     * @param array $parameters
     */
    public function __construct(array $parameters = array());

    /**
     * @param string $name
     *
     * @return mixed
     *
     * @throws InvalidArgumentException If the parameter does not exist.
     */
    public function getParameter($name);

    /**
     * @return MWP_WordPress_Context
     */
    public function getWordPressContext();

    /**
     * @return Symfony_EventDispatcher_EventDispatcherInterface
     */
    public function getEventDispatcher();

    /**
     * @return MWP_Worker_RequestStack
     */
    public function getRequestStack();

    /**
     * @return MWP_Action_Registry
     */
    public function getActionRegistry();

    /**
     * @return MWP_WordPress_Query_User
     */
    public function getUserQuery();

    /**
     * @return MWP_WordPress_Query_Post
     */
    public function getPostQuery();

    /**
     * @return MWP_WordPress_Query_Comment
     */
    public function getCommentQuery();

    /**
     * @return MWP_WordPress_Provider_Plugin
     */
    public function getPluginProvider();

    /**
     * @return MWP_WordPress_Provider_Theme
     */
    public function getThemeProvider();

    /**
     * @return MWP_Worker_Brand
     */
    public function getBrand();

    /**
     * @return MWP_Updater_AutoUpdateManager
     */
    public function getAutoUpdateManager();

    /**
     * @return MWP_Updater_UpdateManager
     */
    public function getUpdateManager();

    /**
     * @return MWP_Signer_Interface
     */
    public function getSigner();

    /**
     * @return MWP_Crypter_Interface
     */
    public function getCrypter();

    /**
     * @return MWP_Security_NonceManager
     */
    public function getNonceManager();

    /**
     * @return MWP_Worker_Configuration
     */
    public function getConfiguration();

    /**
     * @return Monolog_Logger
     */
    public function getLogger();

    /**
     * @return MWP_Worker_ResponseCallback
     */
    public function getResponseCallback();

    /**
     * @return Monolog_ErrorHandler
     */
    public function getErrorHandler();

    /**
     * @return Symfony_Process_ExecutableFinder
     */
    public function getExecutableFinder();

    /**
     * @return MWP_Extension_HitCounter
     */
    public function getHitCounter();

    /**
     * @return MWP_Monolog_Handler_JsonMessageHandler
     */
    public function getJsonMessageHandler();

    /**
     * @return Monolog_Logger
     */
    public function getErrorLogger();

    /**
     * @return MWP_System_Environment
     */
    public function getSystemEnvironment();
}
