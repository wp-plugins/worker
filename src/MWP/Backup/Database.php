<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Class MWP_Backup_Database
 * Creates a database SQL dump
 *
 * @version 0.1
 * @author  Ivan Batić
 */
class MWP_Backup_Database
{
    /**
     * Creates the database dump at a given path
     *
     * @param       $config
     * @param array $options
     *
     * @return MWP_Backup_MysqlDump_MysqlDump
     */
    public static function dump($config, array $options = array())
    {
        @set_time_limit(0);
        $writer = MWP_Backup_Writer_WriterFactory::make(
            MWP_Backup_ArrayHelper::getKey($options, 'save_path'),
            MWP_Backup_ArrayHelper::getKey($options, 'compression_method')
        );

        $dumper = MWP_Backup_MysqlDump_DumpFactory::make($config, $options, $writer);
        $dumper->dumpToFile();
    }
}
