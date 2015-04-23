<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_IncrementalBackup_Database_PdoConnection implements MWP_IncrementalBackup_Database_ConnectionInterface
{

    /**
     * @var MWP_IncrementalBackup_Database_Configuration
     */
    private $configuration;

    /**
     * @var PDO
     */
    private $connection;

    public function __construct(MWP_IncrementalBackup_Database_Configuration $configuration)
    {
        $this->configuration = $configuration;

        if (!extension_loaded('pdo_mysql')) {
            throw new MWP_IncrementalBackup_Database_Exception_ConnectionException("PDO extension is disabled.");
        }

        $options = array(
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        );

        $this->connection = new PDO(self::getDsn($this->configuration), $this->configuration->getUsername(), $this->configuration->getPassword(), $options);
    }

    /**
     * {@inheritdoc}
     */
    public function query($query)
    {
        return new MWP_IncrementalBackup_Database_PdoStatement($this->connection->query($query));
    }

    /**
     * {@inheritdoc}
     */
    public function quote($value)
    {
        return $this->connection->quote($value);
    }

    /**
     * @param MWP_IncrementalBackup_Database_Configuration $configuration
     *
     * @return string
     */
    private static function getDsn(MWP_IncrementalBackup_Database_Configuration $configuration)
    {
        $pdoParameters = array(
            'dbname'  => $configuration->getDatabase(),
            'charset' => $configuration->getCharset(),
        );

        if ($configuration->isSocket()) {
            $pdoParameters['unix_socket'] = $configuration->getSocketPath();
        } else {
            $pdoParameters['host'] = $configuration->getHost();

            if (($port = $configuration->getPort()) !== null) {
                $pdoParameters['port'] = $configuration->getPort();
            }
        }

        $parameters = array();
        foreach ($pdoParameters as $name => $value) {
            $parameters[] = $name.'='.$value;
        }

        $dsn = sprintf("mysql:%s", implode(';', $parameters));

        return $dsn;
    }
}
