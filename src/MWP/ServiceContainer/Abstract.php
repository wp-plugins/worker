<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

abstract class MWP_ServiceContainer_Abstract implements MWP_ServiceContainer_Interface
{

    private $parameters;

    private $wordPressContext;

    private $eventDispatcher;

    private $actionMapper;

    private $requestStack;

    private $userQuery;

    private $postQuery;

    private $commentQuery;

    private $pluginProvider;

    private $themeProvider;

    private $workerBrand;

    private $autoUpdateManager;

    private $updateManager;

    private $signer;

    private $crypter;

    private $nonceManager;

    private $configuration;

    private $logger;

    private $responseCallback;

    private $errorHandler;

    private $executableFinder;

    private $hitCounter;

    private $jsonMessageHandler;

    private $errorLogger;

    public function __construct(array $parameters = array())
    {
        $this->parameters = $parameters;

        $this->parameters += array(
            // This plugin's main file absolute path.
            'worker_realpath'                     => null,
            // This plugin's "basename", ie. 'worker/init.php'.
            'worker_basename'                     => null,
            // Always use PhpSecLib, even if the PHP extension 'openssl' is loaded.
            'prefer_phpseclib'                    => false,
            // Log file to use for all worker logs.
            'log_file'                            => null,
            // GrayLog2 server to use for all worker logs.
            'gelf_server'                         => null,
            'gelf_port'                           => null,
            // Capture errors in master response.
            'log_errors'                          => false,
            // Pad length used for progress message flushing.
            'message_pad_length'                  => 16384,
            // Minimum log level for streamed messages.
            'message_minimum_level'               => Monolog_Logger::INFO,
            // Memory size (in kilobytes) to allocate for fatal error handling when the request is authenticated.
            'fatal_error_reserved_memory_size'    => 1024,
            'hit_counter_blacklisted_ips'         => array(
                // Uptime monitoring robot.
                '/^74\.86\.158\.106$/',
                '/^74\.86\.158\.107$/',
                '/^74\.86\.158\.109$/',
                '/^74\.86\.158\.110$/',
                '/^74\.86\.158\.108$/',
                '/^46\.137\.190\.132$/',
                '/^122\.248\.234\.23$/',
                '/^188\.226\.183\.141$/',
                '/^178\.62\.52\.237$/',
                '/^54\.79\.28\.129$/',
                '/^54\.94\.142\.218$/',
            ),
            'hit_counter_blacklisted_user_agents' => array(
                '/bot/',
                '/crawl/',
                '/feed/',
                '/java\//',
                '/spider/',
                '/curl/',
                '/libwww/',
                '/alexa/',
                '/altavista/',
                '/aolserver/',
                '/appie/',
                '/Ask Jeeves/',
                '/baidu/',
                '/Bing/',
                '/borg/',
                '/BrowserMob/',
                '/ccooter/',
                '/dataparksearch/',
                '/Download Demon/',
                '/echoping/',
                '/FAST/',
                '/findlinks/',
                '/Firefly/',
                '/froogle/',
                '/GomezA/',
                '/Google/',
                '/grub-client/',
                '/htdig/',
                '/http%20client/',
                '/HttpMonitor/',
                '/ia_archiver/',
                '/InfoSeek/',
                '/inktomi/',
                '/larbin/',
                '/looksmart/',
                '/Microsoft URL Control/',
                '/Minimo/',
                '/mogimogi/',
                '/NationalDirectory/',
                '/netcraftsurvey/',
                '/nuhk/',
                '/oegp/',
                '/panopta/',
                '/rabaz/',
                '/Read%20Later/',
                '/Scooter/',
                '/scrubby/',
                '/SearchExpress/',
                '/searchsight/',
                '/semanticdiscovery/',
                '/Slurp/',
                '/snappy/',
                '/Spade/',
                '/TechnoratiSnoop/',
                '/TECNOSEEK/',
                '/teoma/',
                '/twiceler/',
                '/URL2PNG/',
                '/vortex/',
                '/WebBug/',
                '/www\.galaxy\.com/',
                '/yahoo/',
                '/yandex/',
                '/zao/',
                '/zeal/',
                '/ZooShot/',
                '/ZyBorg/',
            ),
        );
    }

    public function getParameter($name)
    {
        if (!array_key_exists($name, $this->parameters)) {
            throw new InvalidArgumentException(sprintf('The parameter named "%s" does not exist.', $name));
        }

        return $this->parameters[$name];
    }

    public function getWordPressContext()
    {
        if ($this->wordPressContext === null) {
            $this->wordPressContext = $this->createWordPressContext();
        }

        return $this->wordPressContext;
    }

    /**
     * @return MWP_WordPress_Context
     */
    protected abstract function createWordPressContext();

    public function getEventDispatcher()
    {
        if ($this->eventDispatcher === null) {
            $this->eventDispatcher = $this->createEventDispatcher();
        }

        return $this->eventDispatcher;
    }

    /**
     * @return Symfony_EventDispatcher_EventDispatcherInterface
     */
    protected abstract function createEventDispatcher();

    public function getRequestStack()
    {
        if ($this->requestStack === null) {
            $this->requestStack = $this->createRequestStack();
        }

        return $this->requestStack;
    }

    protected abstract function createRequestStack();

    public function getActionRegistry()
    {
        if ($this->actionMapper === null) {
            $this->actionMapper = $this->createActionRegistry();
        }

        return $this->actionMapper;
    }

    /**
     * @return MWP_Action_Registry
     */
    protected abstract function createActionRegistry();

    public function getUserQuery()
    {
        if ($this->userQuery === null) {
            $this->userQuery = $this->createUserQuery();
        }

        return $this->userQuery;
    }

    /**
     * @return MWP_WordPress_Query_User
     */
    protected abstract function createUserQuery();

    public function getPostQuery()
    {
        if ($this->postQuery === null) {
            $this->postQuery = $this->createPostQuery();
        }

        return $this->postQuery;
    }

    protected abstract function createPostQuery();

    public function getCommentQuery()
    {
        if ($this->commentQuery === null) {
            $this->commentQuery = $this->createCommentQuery();
        }

        return $this->commentQuery;
    }

    protected abstract function createCommentQuery();

    /**
     * @return MWP_WordPress_Provider_Plugin
     */
    public function getPluginProvider()
    {
        if ($this->pluginProvider === null) {
            $this->pluginProvider = $this->createPluginProvider();
        }

        return $this->pluginProvider;
    }

    protected abstract function createPluginProvider();

    /**
     * @return MWP_WordPress_Provider_Theme
     */
    public function getThemeProvider()
    {
        if ($this->themeProvider === null) {
            $this->themeProvider = $this->createThemeProvider();
        }

        return $this->themeProvider;
    }

    protected abstract function createThemeProvider();

    /**
     * @return MWP_Worker_Brand
     */
    public function getBrand()
    {
        if ($this->workerBrand === null) {
            $this->workerBrand = $this->createWorkerBrand();
        }

        return $this->workerBrand;
    }

    protected abstract function createWorkerBrand();

    public function getAutoUpdateManager()
    {
        if ($this->autoUpdateManager === null) {
            $this->autoUpdateManager = $this->createAutoUpdateManager();
        }

        return $this->autoUpdateManager;
    }

    protected abstract function createAutoUpdateManager();

    public function getUpdateManager()
    {
        if ($this->updateManager === null) {
            $this->updateManager = $this->createUpdateManager();
        }

        return $this->updateManager;
    }

    /**
     * @return MWP_Updater_UpdateManager
     */
    protected abstract function createUpdateManager();

    public function getSigner()
    {
        if ($this->signer === null) {
            $this->signer = $this->createSigner();
        }

        return $this->signer;
    }

    /**
     * @return MWP_Signer_Interface
     */
    protected abstract function createSigner();

    public function getCrypter()
    {
        if ($this->crypter === null) {
            $this->crypter = $this->createCrypter();
        }

        return $this->crypter;
    }

    /**
     * @return MWP_Crypter_Interface
     */
    protected abstract function createCrypter();

    public function getNonceManager()
    {
        if ($this->nonceManager === null) {
            $this->nonceManager = $this->createNonceManager();
        }

        return $this->nonceManager;
    }

    /**
     * @return MWP_Security_NonceManager
     */
    protected abstract function createNonceManager();

    public function getConfiguration()
    {
        if ($this->configuration === null) {
            $this->configuration = $this->createConfiguration();
        }

        return $this->configuration;
    }

    /**
     * @return MWP_Worker_Configuration
     */
    protected abstract function createConfiguration();

    public function getLogger()
    {
        if ($this->logger === null) {
            $this->logger = $this->createLogger();
        }

        return $this->logger;
    }

    /**
     * @return Monolog_Logger
     */
    protected abstract function createLogger();

    /**
     * @return MWP_Worker_ResponseCallback
     */
    public function getResponseCallback()
    {
        if ($this->responseCallback === null) {
            $this->responseCallback = $this->createResponseCallback();
        }

        return $this->responseCallback;
    }

    protected abstract function createResponseCallback();

    public function getErrorHandler()
    {
        if ($this->errorHandler === null) {
            $this->errorHandler = $this->createErrorHandler();
        }

        return $this->errorHandler;
    }

    /**
     * @return Monolog_ErrorHandler
     */
    protected abstract function createErrorHandler();

    public function getExecutableFinder()
    {
        if ($this->executableFinder === null) {
            $this->executableFinder = $this->createExecutableFinder();
        }

        return $this->executableFinder;
    }

    /**
     * @return Symfony_Process_ExecutableFinder
     */
    protected abstract function createExecutableFinder();

    public function getHitCounter()
    {
        if ($this->hitCounter === null) {
            $this->hitCounter = $this->createHitCounter();
        }

        return $this->hitCounter;
    }

    /**
     * @return MWP_Extension_HitCounter
     */
    protected abstract function createHitCounter();

    public function getJsonMessageHandler()
    {
        if ($this->jsonMessageHandler === null) {
            $this->jsonMessageHandler = $this->createJsonMessageHandler();
        }

        return $this->jsonMessageHandler;
    }

    /**
     * @return MWP_Monolog_Handler_JsonMessageHandler
     */
    protected abstract function createJsonMessageHandler();

    public function getErrorLogger()
    {
        if ($this->errorLogger === null) {
            $this->errorLogger = $this->createErrorLogger();
        }

        return $this->errorLogger;
    }

    /**
     * @return Monolog_Logger
     */
    protected abstract function createErrorLogger();
}
