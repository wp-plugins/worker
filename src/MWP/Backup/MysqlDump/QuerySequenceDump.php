<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Backup_MysqlDump_QuerySequenceDump extends MWP_Backup_MysqlDump_MysqlDump
{
    /** @var Resource File Pointer */
    protected $file;

    /**
     * @inherit
     */
    public function dumpToFile()
    {
        $writer    = $this->getWriter();
        $allTables = $this->getConnection()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $tables    = array_intersect($allTables, $this->getOptions('tables', $allTables));

        $writer->open();

        $writer->write("
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;\n\n"
        );

        foreach ($tables as $tableName) {
            // Get the SHOW CREATE TABLE part
            $content = $this->getConnection()
                ->query("SHOW CREATE TABLE `{$tableName}`;", PDO::FETCH_ASSOC)
                ->fetchAll();
            if (is_array($content)) {
                foreach ($content as $entry) {
                    if ($this->getOptions('drop_tables')) {
                        $writer->writeLine("DROP TABLE IF EXISTS `$tableName`;");

                        if ($this->getOptions('create_tables')) {
                            $writer->write("
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;\n"
                            );
                            $writer->write($entry['Create Table'].";\n");
                            $writer->write("/*!40101 SET character_set_client = @saved_cs_client */;\n\n");
                        }
                    }
                }

                $columns = $this->getConnection()
                    ->query("SHOW COLUMNS IN `{$tableName}`;", PDO::FETCH_ASSOC)
                    ->fetchAll();

                if (is_array($columns)) {
                    $columns = $this->repack($columns, 'Field');
                }

                $allData = $this->getConnection()
                    ->query($this->selectAllDataQuery($tableName, $columns), PDO::FETCH_ASSOC);

                // Go through row by row
                if (!$this->getOptions('skip_lock_tables')) {
                    $writer->writeLine("LOCK TABLES `$tableName` WRITE;");
                }
                $writer->writeLine("/*!40000 ALTER TABLE `$tableName` DISABLE KEYS */;");

                while ($row = $allData->fetch()) {
                    $writer->writeLine($this->createRowInsertStatement($tableName, $row, $columns));
                }
                $writer->writeLine(";");
                $writer->writeLine("/*!40000 ALTER TABLE `$tableName` ENABLE KEYS */;");
                if (!$this->getOptions('skip_lock_tables')) {
                    $writer->writeLine("UNLOCK TABLES;");
                }
            }
        }

        $writer->write("
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;"
        );
        $writer->close();
    }

    /**
     * Repacks an array by making a key of a particular column
     *
     * @param array $array
     * @param       $column
     *
     * @return array
     */
    protected function repack(
        array $array,
        $column
    ) {
        $repacked = array();
        foreach ($array as $element) {
            $repacked[$element[$column]] = $element;
        }

        return $repacked;
    }

    /**
     * Creates an SQL statement for fetching all data from a particular table
     *
     * @param $tableName
     * @param $columnData
     *
     * @return string
     */
    protected function selectAllDataQuery(
        $tableName,
        $columnData
    ) {
        $columns = array();
        foreach ($columnData as $columnName => $metadata) {
            if (strpos($metadata['Type'], 'blob') !== false) {
                $fullColumnName = "`{$tableName}`.`{$columnName}`";
                $columns[]      = "HEX($fullColumnName) as `{$columnName}`";
            } else {
                $columns[] = "`{$tableName}`.`{$columnName}`";
            }
        }
        $cols = join(', ', $columns);
        $sql  = "SELECT $cols FROM `$tableName`;";

        return $sql;
    }

    /**
     * Creates an sql statement for row insertion
     *
     * @param string $tableName
     * @param array  $row
     * @param array  $columns
     *
     * @return string
     */
    protected function createRowInsertStatement(
        $tableName,
        array $row,
        array $columns = array()
    ) {
        $values = $this->createRowInsertValues($row, $columns);
        $joined = join(', ', $values);
        $sql    = "INSERT INTO `$tableName` VALUES($joined);";

        return $sql;
    }

    protected function createRowInsertValues($row, $columns)
    {
        $values = array();

        foreach ($row as $columnName => $value) {
            $type = $columns[$columnName]['Type'];
            // If it should not be enclosed
            if ($value === null) {
                $values[] = 'null';
            } elseif (strpos($type, 'int') !== false
                || strpos($type, 'float') !== false
                || strpos($type, 'double') !== false
                || strpos($type, 'decimal') !== false
                || strpos($type, 'bool') !== false
            ) {
                $values[] = $value;
            } elseif (strpos($type, 'blob') !== false) {
                $values[] = strlen($value) ? ('0x'.$value) : "''";
            } else {
                $values[] = $this->getConnection()->quote($value);
            }
        }

        return $values;
    }

    /**
     * Gets the preferred size for the output buffer
     *
     * @return int
     * @unused
     */
    protected function calculatePreferredBufferSize()
    {
        $usage = memory_get_usage(true);
        $limit = $this->getMemoryLimit();

        return (int) floor(($limit - $usage) / 2);
    }

    /**
     * Returns the PHP memory limit in bytes
     *
     * @return int
     * @unused
     */
    protected function getMemoryLimit()
    {
        $limit  = ini_get('memory_limit');
        $rank   = strtolower(substr($limit, -1));
        $number = (int) substr($limit, strlen($limit) - 1);
        switch ($rank) {
            case 'k':
                $coefficient = 1024;
                break;
            case 'm':
                $coefficient = pow(1024, 2);
                break;
            case 'g':
                $coefficient = pow(1024, 3);
                break;
            case 'b':
            default:
                $coefficient = 1;
                break;
        }

        return $number * $coefficient;
    }
}
