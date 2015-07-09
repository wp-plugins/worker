<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

abstract class MWP_Backup_MysqlDump_MysqlDump
{
    /**
     * @var array|PDO Database connection options
     * <ul>
     *  <li>host : string</li>
     *  <li>database : string</li>
     *  <li>username : string</li>
     *  <li>password : string</li>
     *  <li>port : int|string</li>
     * </ul>
     */
    protected $config = array();

    /** @var array Additional options
     * <ul>
     *  <li>tables : array List of tables that should be dumped</li>
     *  <li>save_path : string Path of a file that dump should be saved into</li>
     *  <li>compression : string Compression type (gzip) <strong>Not Implemented</strong></li>
     *  <li>force_method: string mysqldump|sequential</li>
     * </ul>
     */
    protected $options = array(
        'foreign_key_checks' => false,
        'create_tables'      => true,
        'drop_tables'        => true,
        'compression_method' => null,
        'skip_lock_tables'   => true,
    );

    /** @var  PDO Connection instance */
    protected $connection;

    /** @var  MWP_Backup_MysqlDump_MysqlDump Database dumping strategy object */
    protected $strategy;

    /** @var  MWP_Backup_Writer_WriterInterface */
    protected $writer;

    public function __construct($config, array $options = array(), MWP_Backup_Writer_WriterInterface $writer = null)
    {
        if ($config instanceof PDO) {
            $this->connection = $config;
        } else {
            $this->config = $config;
        }

        $this->options = array_merge($this->options, $options);

        $this->writer = $writer;
    }

    /**
     * Retrieves a value from the options attribute if a key is given, otherwise returns the whole attribute.
     * If key doesn't exist in an array, returns the value specified by the $default param.
     *
     * @param mixed $key     Array key
     * @param mixed $default Default return value
     *
     * @return mixed
     */
    public function getOptions($key = null, $default = null)
    {
        if (isset($key)) {
            return array_key_exists($key, $this->options) ? $this->options[$key] : $default;
        } else {
            return $this->options;
        }
    }

    /**
     * Method signature, it should be overriden.
     *
     * @throws Exception
     */
    abstract public function dumpToFile();

    /**
     * Checks if mysqldump is available and excecutable at the given or default path
     *
     * @return bool
     */
    public function isMysqldumpAvailable($path = null)
    {
        $path = is_string($path) ? $path : $this->getMysqlPath('/bin/mysqldump');

        return file_exists($path) && is_executable($path);
    }

    /**
     * Returns the path to the MySQL installation
     *
     * @param string $path Where to go relative to the installation root
     *
     * @return string
     */
    public function getMysqlPath($path = null)
    {
        $base = $this->getConnection()->query('SELECT @@basedir as dir', PDO::FETCH_CLASS, 'StdClass')->fetch();

        return $base->dir.(isset($path) ? (DIRECTORY_SEPARATOR.ltrim($path, DIRECTORY_SEPARATOR)) : '');
    }

    /**
     * Returns the PDO connection object using lazy instantiation
     * Creates and|or returns the existing PDO connection based on the input configuration
     *
     * @return PDO
     */
    public function getConnection()
    {
        if (!($this->connection instanceof PDO)) {
            // Add a port to the socket or host
            if ($this->getConfig('socket')) {
                $host = 'unix_socket='.$this->getConfig('socket');
            } else {
                $host = 'host='.$this->getConfig('host').($this->getConfig('port') ? (';port='.$this->getConfig('port')) : '');
            }
            // Build a MySQL connection string
            $connectionString = "mysql:dbname={$this->getConfig('database')};{$host}";
            // Create a PDO instance
            $this->connection = new PDO($connectionString, $this->getConfig('username'), $this->getConfig('password'), array(
                /** @handled constant */
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8",
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            ));
        }

        return $this->connection;
    }

    /**
     * Retrieves a value from the config attribute if a key is given, otherwise returns the whole attribute.
     * If key doesn't exist in an array, returns the value specified by the $default param.
     *
     * @param mixed $key     Array key
     * @param mixed $default Default return value
     *
     * @return mixed
     */
    public function getConfig($key = null, $default = null)
    {
        if (isset($key)) {
            return array_key_exists($key, $this->config) ? $this->config[$key] : $default;
        } else {
            return $this->config;
        }
    }

    /**
     * @return MWP_Backup_Writer_WriterInterface
     */
    public function getWriter()
    {
        return $this->writer;
    }

    /**
     * @param MWP_Backup_Writer_WriterInterface $writer
     */
    public function setWriter(MWP_Backup_Writer_WriterInterface $writer)
    {
        $this->writer = $writer;

        return $this;
    }

    /**
     * @return MWP_Backup_MysqlDump_MysqlDump
     */
    public function getStrategy()
    {
        return $this->strategy;
    }

    /**
     * @param MWP_Backup_MysqlDump_MysqlDump $strategy
     */
    public function setStrategy($strategy)
    {
        $this->strategy = $strategy;

        return $this;
    }
}
