<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Backup_MysqlDump_ShellDump extends MWP_Backup_MysqlDump_MysqlDump
{
    public function dumpToFile()
    {
        $mysqldumpPath = $this->getMysqlPath('bin/mysqldump');

        // Log in
        $cliArgList = array(
            '--host'                     => $this->getConfig('host'),
            '--socket'                   => $this->getConfig('socket'),
            '--port'                     => $this->getConfig('port'),
            '--user'                     => $this->getConfig('username'),
            '--password'                 => $this->getConfig('password'),
            '--add-drop-table'           => $this->getOptions('drop_tables', true),
            '--skip-lock-tables'         => $this->getOptions('skip_lock_tables', true),
            $this->getConfig('database') => true,
        );
        $cliArgs = array();
        foreach ($cliArgList as $arg => $value) {
            if (isset($value) && $value !== false) {
                $cliArgs[] = escapeshellarg($value === true ? $arg : "$arg=$value");
            }
        }

        $command = $mysqldumpPath
            .' '.join(' ', $cliArgs)
            .' '.join(' ', $this->getOptions('tables', array()));

        $command = $this->getWriter()->consoleOutput($command, $this->getOptions('save_path'));

        return shell_exec($command);
    }
}
