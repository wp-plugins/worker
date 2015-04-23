<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Action_IncrementalBackup_ListFiles extends MWP_Action_IncrementalBackup_Abstract
{
    /**
     * Return a list of all files
     *
     * @param array              $params
     * @param MWP_Worker_Request $request
     *
     * @return array
     */
    public function queryFiles(array $params = array(), MWP_Worker_Request $request)
    {
        if (isset($params['query']) && is_array($params['query'])) {
            $files = $this->getFilesInfo($params['query']);
        } else {
            $files = $this->listFiles(ABSPATH, true);
        }

        $files = $this->replaceWindowsPaths($files);

        return $this->createResult(array('files' => $files));
    }

    /**
     * @param array              $params
     * @param MWP_Worker_Request $request
     *
     * @return array
     */
    public function listDirectories(array $params = array(), MWP_Worker_Request $request)
    {
        $directories = $params['directories'];

        $result = array();

        foreach ($directories as $directory) {
            $path      = $directory['path'];
            $recursive = isset($directory['recursive']) ? $directory['recursive'] : false;
            $offset    = isset($directory['offset']) ? $directory['offset'] : 0;
            $limit     = isset($directory['limit']) ? $directory['limit'] : 0;

            $realPath = $this->getRealPath($path);
            if (!file_exists($realPath)) {
                $result[$path] = false;
                continue;
            }

            $filesInDirectory                         = $this->listFiles($realPath, $recursive, $offset, $limit);
            $filesInDirectory                         = $this->replaceWindowsPaths($filesInDirectory);
            $result[$this->replaceWindowsPath($path)] = $filesInDirectory;
        }

        return $this->createResult(array('directories' => $result));
    }

    /**
     * Return a list of files in the WordPress root directory, recursively
     *
     * @param string $rootPath
     * @param bool   $recursive
     * @param int    $offset
     * @param int    $limit
     *
     * @return array
     */
    private function listFiles($rootPath, $recursive = false, $offset = 0, $limit = 0)
    {
        $result = array();

        $iterator = $this->createIterator($recursive, $rootPath);

        $i = 0;

        foreach ($iterator as $file) {
            if ($i++ < $offset) {
                continue;
            }

            if ($limit !== 0 && $i > $offset + $limit) {
                break;
            }

            /** @var SplFileInfo $file */
            $fileResult = $this->createFileResult($file);

            $result[] = $fileResult;
        }

        return $result;
    }

    /**
     * Get a list of file stats for given $files
     *
     * @param array $files
     *
     * @return array
     */
    protected function getFilesInfo(array $files)
    {
        $result = array();

        foreach ($files as $path) {
            $realPath = $this->getRealPath($path);

            if (!file_exists($realPath)) {
                $result[] = array(
                    'path'   => $path,
                    'exists' => false,
                );
                continue;
            }

            $file     = new SplFileInfo($realPath);
            $result[] = $this->createFileResult($file);
        }

        return $result;
    }

    /**
     * @param string $realPath
     * @param string $rootPath
     *
     * @return string
     */
    private function getRelativePath($realPath, $rootPath)
    {
        if ($realPath === untrailingslashit($rootPath)) {
            return '.';
        } else {
            $relative = str_replace(untrailingslashit($rootPath).'/', '', $realPath);
            if ($relative == $realPath) {
                $up = '';
                while (($pos = strpos($realPath, $rootPath)) === false) {
                    $rootPath = dirname($rootPath);
                    $up .= '../';
                }

                return $up.substr($realPath, $pos + strlen($rootPath) + 1);
            } else {
                return $relative;
            }
        }
    }

    /**
     * @param SplFileInfo $file
     *
     * @return array
     */
    private function createFileResult(SplFileInfo $file)
    {
        $fileResult = array(
            'path'        => $this->getRelativePath($file->getRealPath(), ABSPATH),
            'size'        => $file->getSize(),
            'isDirectory' => $file->isDir(),
            'isLink'      => $file->isLink(),
            'owner'       => $file->getOwner(),
            'group'       => $file->getGroup(),
            'permissions' => $file->getPerms(),
            'exists'      => true,
        );

        if ($file->isLink()) {
            $fileResult['linkTarget'] = $file->getLinkTarget();
        };

        return $fileResult;
    }

    /**
     * Create a recursive or non-recursive iterator for $path. Handles php 5.2 incompatiblity.
     *
     * @param bool   $recursive
     * @param string $path
     *
     * @return Iterator
     */
    private function createIterator($recursive, $path)
    {
        if ($recursive) {
            // PHP 5.2.x does not have the SKIP_DOTS flag because it skips all dots by default
            // The behavior was changed in PHP 5.3+ and it does not skip dots without the SKIP_DOTS flag
            $php52 = version_compare(phpversion(), '5.3', '<');

            if ($php52) {
                $directory = new RecursiveDirectoryIterator($path);
            } else {
                $directory = new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS);
            }

            $iterator = new RecursiveIteratorIterator($directory, RecursiveIteratorIterator::SELF_FIRST);
        } else {
            $directory = new Symfony_Filesystem_FilesystemIterator($path, FilesystemIterator::SKIP_DOTS);
            $iterator  = new IteratorIterator($directory);
        }

        return $iterator;
    }
}
