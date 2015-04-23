<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Action_IncrementalBackup_DumpTables extends MWP_Action_IncrementalBackup_Abstract
{
    const METHOD_MYSQLDUMP = 'mysqldump';
    const METHOD_PHPDUMPER = 'phpdumper';

    public function execute(array $params = array(), MWP_Worker_Request $request)
    {
        $method = $params['method'];
        $tables = $params['tables'];

        $this->assertTablesExist($tables);

        if (isset($params['options']) && is_array($params['options'])) {
            $options = $params['options'];
        } else {
            $options = array();
        }

        $dumper = $this->getDumper($method, $options);

        try {
            $stream = $dumper->dump($tables);
        } catch (Exception $e) {
            if ($e instanceof MWP_Worker_Exception) {
                throw $e;
            }

            // Convert any other exception to MWP_Worker_Exception
            throw new MWP_Worker_Exception(MWP_Worker_Exception::BACKUP_DATABASE_FAILED, $e->getMessage(), array(
                'code'    => $e->getCode(),
                'message' => $e->getMessage(),
                'type'    => get_class($e),
            ));
        }

        $file = new MWP_IncrementalBackup_Model_File();
        $file->setPathname('tables.sql');
        $file->setStream($stream);

        $result = new MWP_IncrementalBackup_Model_FetchFilesResult();
        $result->setServerStatistics(MWP_IncrementalBackup_Model_ServerStatistics::factory());
        $result->setFiles(array($file));

        return $result;
    }

    /**
     * @param array $tables
     *
     * @throws MWP_Worker_Exception
     */
    private function assertTablesExist(array $tables)
    {
        $rows = mwp_context()->getDb()->get_results("SHOW TABLES", ARRAY_N);
        foreach ($rows as $row) {
            // $row is always an array with only a single member
            $table = $row[0];

            $index = array_search($table, $tables);
            if ($index !== false) {
                // Remove from $tables - don't worry, $tables has not been passed by reference :)
                array_splice($tables, $index, 1);
            }
        }

        // Tables which have not been found are located in $tables
        if (count($tables)) {
            throw new MWP_Worker_Exception(MWP_Worker_Exception::BACKUP_DATABASE_MISSING_TABLES, "Some tables are missing", array(
                // Return an array of missing tables
                'tables' => $tables,
            ));
        }
    }

    /**
     * @param string $method
     *
     * @param array  $options
     *
     * @throws MWP_Worker_Exception
     * @return MWP_IncrementalBackup_Database_DumperInterface
     */
    private function getDumper($method, array $options = array())
    {
        $dumperOptions = MWP_IncrementalBackup_Database_DumpOptions::createFromArray($options);

        switch ($method) {
            case self::METHOD_MYSQLDUMP:
                $configuration = MWP_IncrementalBackup_Database_Configuration::createFromWordPressContext(mwp_context());
                $dumper        = new MWP_IncrementalBackup_Database_MysqlDumpDumper($configuration, $dumperOptions);

                return $dumper;
            case self::METHOD_PHPDUMPER:
                $configuration = MWP_IncrementalBackup_Database_Configuration::createFromWordPressContext(mwp_context());
                $dumper        = new MWP_IncrementalBackup_Database_PhpDumper($configuration, mwp_container()->getSystemEnvironment(), $dumperOptions);

                return $dumper;
            default:
                throw new MWP_Worker_Exception(MWP_Worker_Exception::BACKUP_DATABASE_METHOD_NOT_AVAILABLE);
                break;
        }
    }
} 
