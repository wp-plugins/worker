<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Action_IncrementalBackup_DumpTables extends MWP_Action_IncrementalBackup_AbstractTablesAction
{

    /** @var string */
    private $baseDir;

    public function __construct($baseDir = null)
    {
        if ($baseDir === null) {
            $baseDir = str_replace(realpath(ABSPATH), '', realpath(WP_CONTENT_DIR)).'/managewp/backups/db';
        }

        $this->baseDir = $baseDir;
    }

    /**
     * {@inheritdoc}
     */
    protected function executeAction(MWP_IncrementalBackup_Database_DumperInterface $dumper, array $tables = array(), array $params = array())
    {
        $path = isset($params['path']) ? $params['path'] : $this->baseDir;

        $this->createDirectory($path);

        $result = array();

        foreach ($tables as $entry) {
            $table    = isset($entry['name']) ? $entry['name'] : null;
            $filename = isset($entry['filename']) ? $entry['filename'] : $this->generateFilename($table);
            $pathname = $path.'/'.$filename;
            $realpath = ABSPATH.$pathname;

            try {
                if ($table === null) {
                    throw new RuntimeException('Table name not passed.');
                }

                $this->assertTablesExist(array($table));

                $dumper->dump($table, $realpath);
                $result[] = array(
                    'table'    => $table,
                    'pathname' => $pathname,
                    'realpath' => $realpath,
                    'url'      => site_url().'/'.$pathname,
                );
            } catch (Exception $e) {
                mwp_logger()->error('Failed dumping table', array(
                    'table'    => $table,
                    'filename' => $filename,
                    'pathname' => $pathname,
                    'error'    => $e->getMessage(),
                ));

                $this->cleanDumpedTables($result);

                if ($e instanceof MWP_Worker_Exception) {
                    throw $e;
                }

                throw new MWP_Worker_Exception(MWP_Worker_Exception::BACKUP_DATABASE_FAILED, $e->getMessage(), array(
                    'message' => $e->getMessage(),
                    'line'    => $e->getLine(),
                    'file'    => $e->getFile(),
                ));
            }
        }

        return $this->createResult(array('tables' => $result));
    }

    private function cleanDumpedTables($result)
    {
        foreach ($result as $entry) {
            @unlink($entry['realpath']);
        }
    }

    private function generateFilename($table)
    {
        return uniqid($table.'_', true).'.sql';
    }

    /**
     * @param $path
     */
    protected function createDirectory($path)
    {
        if (!file_exists(ABSPATH.$path)) {
            mkdir(ABSPATH.$path, 0755, true);
        }

        $segments = explode('/', str_replace(DIRECTORY_SEPARATOR, '/', $path));
        $pathname = rtrim(ABSPATH, DIRECTORY_SEPARATOR.'/');

        foreach ($segments as $segment) {
            $pathname .= DIRECTORY_SEPARATOR.$segment;
            if (!file_exists($pathname.DIRECTORY_SEPARATOR.'index.php') && !file_exists($pathname.DIRECTORY_SEPARATOR.'index.html')) {
                file_put_contents($pathname.DIRECTORY_SEPARATOR.'index.php', '');
            }
        }
    }
}
