<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_IncrementalBackup_Database_PhpDumper implements MWP_IncrementalBackup_Database_DumperInterface
{

    private $transferSize = 102400; // 100kb

    /**
     * @var MWP_IncrementalBackup_Database_Configuration
     */
    private $configuration;

    /**
     * @var MWP_System_Environment
     */
    private $environment;

    /**
     * @var MWP_IncrementalBackup_Database_DumpOptions
     */
    private $options;

    /**
     * @param MWP_IncrementalBackup_Database_Configuration $configuration
     * @param MWP_System_Environment                       $environment
     * @param MWP_IncrementalBackup_Database_DumpOptions   $options
     */
    public function __construct(MWP_IncrementalBackup_Database_Configuration $configuration, MWP_System_Environment $environment, MWP_IncrementalBackup_Database_DumpOptions $options)
    {
        $this->configuration = $configuration;
        $this->environment   = $environment;
        $this->options       = $options;
    }

    /**
     * {@inheritdoc}
     */
    public function dump($table, $realpath)
    {
        $stream = $this->createStream(array($table));
        $handle = @fopen($realpath, 'w');
        if ($handle === false) {
            $error   = error_get_last();
            $message = isset($error['message']) ? $error['message'] : sprintf('Unable to open file %s for writing.', $realpath);
            throw new MWP_Worker_Exception(MWP_Worker_Exception::IO_EXCEPTION, $message, $error);
        }

        while (!$stream->eof()) {
            fwrite($handle, $stream->read($this->transferSize));
        }

        fclose($handle);
    }

    /**
     * {@inheritdoc}
     */
    public function createStream(array $tables = array())
    {
        if ($this->environment->isPdoEnabled()) {
            $connection = new MWP_IncrementalBackup_Database_PdoConnection($this->configuration);
        } elseif ($this->environment->isMysqliEnabled()) {
            $connection = new MWP_IncrementalBackup_Database_MysqliConnection($this->configuration);
        } elseif ($this->environment->isMysqlEnabled()) {
            $connection = new MWP_IncrementalBackup_Database_MysqlConnection($this->configuration);
        } else {
            throw new MWP_IncrementalBackup_Database_Exception_ConnectionException("No mysql drivers available.");
        }

        $this->options->setTables($tables);
        $dumper = new MWP_IncrementalBackup_Database_StreamableQuerySequenceDump($connection, $this->options);

        return $dumper->createStream();
    }
}
