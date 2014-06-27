<?php

class MMB_Container
{
    private $executableFinder;
    private $logger;

    private $parameters = array();

    public function __construct(array $parameters = array())
    {
        $parameters += array(
            'gelf_server' => null,
            'gelf_port'   => null,
            'log_file'    => null,
        );

        $this->parameters = $parameters;
    }

    public function getExecutableFinder()
    {
        if ($this->executableFinder === null) {
            global $wpdb;
            $this->executableFinder = new Symfony_Process_ExecutableFinder();

            if (is_callable(array($wpdb, 'get_var'))) {
                /** @var wpdb $wpdb */
                $basePath = rtrim($wpdb->get_var('select @@basedir'), '/\\');
                if ($basePath) {
                    $basePath .= '/bin';
                    $this->executableFinder->addExtraDir($basePath);
                }
            }
        }

        return $this->executableFinder;
    }

    public function getLogger()
    {
        if ($this->logger === null) {
            $processors = array(
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
            $handlers   = array();

            if (!empty($this->parameters['log_file'])) {
                $fileHandler = new Monolog_Handler_StreamHandler(fopen(dirname(dirname(dirname(__FILE__))).'/'.$this->parameters['log_file'], 'a'));
                $fileHandler->setFormatter(new Monolog_Formatter_HtmlFormatter());
                $handlers[] = $fileHandler;
            } elseif (!empty($this->parameters['gelf_server'])) {
                $publisher  = new Gelf_Publisher($this->parameters['gelf_server'], $this->parameters['gelf_port'] ? $this->parameters['gelf_port'] : Gelf_Publisher::GRAYLOG2_DEFAULT_PORT);
                $handlers[] = new Monolog_Handler_LegacyGelfHandler($publisher);
            } else {
                $handlers[] = new Monolog_Handler_NullHandler();
            }

            $this->logger = new Monolog_Logger('worker', $handlers, $processors);
        }

        return $this->logger;
    }
}
