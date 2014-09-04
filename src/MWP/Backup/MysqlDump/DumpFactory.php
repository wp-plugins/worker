<?php

class MWP_Backup_MysqlDump_DumpFactory
{

    /**
     * @param       $config
     * @param array $options
     *
     * @return MWP_Backup_MysqlDump_MysqlDump
     * @throws InvalidArgumentException
     */
    public static function make($config, array $options = array(), MWP_Backup_Writer_WriterInterface $writer = null)
    {
        // Determine which dumping strategy is to be used
        // Check if user is trying to force a particular method
        $forcedMethod = MWP_Backup_ArrayHelper::getKey($options, 'force_method');

        if ($forcedMethod) {
            switch ($forcedMethod) {
                case 'mysqldump':
                    $strategy = new MWP_Backup_MysqlDump_ShellDump($config, $options, $writer);
                    break;
                case 'sequential':
                    $strategy = new MWP_Backup_MysqlDump_QuerySequenceDump($config, $options, $writer);
                    break;
                default:
                    throw new InvalidArgumentException('Trying to force a non existing backup method');
                    break;
            }
        } else {
            // Not forced, choose the best method
            $strategy = new MWP_Backup_MysqlDump_ShellDump($config, $options, $writer);
            if ($config instanceof PDO || !self::isShellExecAvailable() || !$strategy->isMysqldumpAvailable()) {
                $strategy = new MWP_Backup_MysqlDump_QuerySequenceDump($config, $options, $writer);
            }
        }

        return $strategy;
    }

    /**
     * Checks if shell_exec function is available
     *
     * @return bool
     */
    public static function isShellExecAvailable()
    {
        if (ini_get('safe_mode') == true || !function_exists('shell_exec')) {
            return false;
        }

        $disabledFunctions = array_map('trim', explode(',', ini_get('disable_functions')));

        return !in_array('shell_exec', $disabledFunctions);
    }
}
