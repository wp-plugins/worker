<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Generic executable finder.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Symfony_Process_ExecutableFinder
{
    private $suffixes = array('.exe', '.bat', '.cmd', '.com');

    private $extraDirs = array();

    /**
     * Replaces default suffixes of executable.
     *
     * @param array $suffixes
     */
    public function setSuffixes(array $suffixes)
    {
        $this->suffixes = $suffixes;
    }

    /**
     * Adds new possible suffix to check for executable.
     *
     * @param string $suffix
     */
    public function addSuffix($suffix)
    {
        $this->suffixes[] = $suffix;
    }

    /**
     * Sets extra directories to check for executable.
     *
     * @param array $extraDirs
     */
    public function setExtraDirs(array $extraDirs)
    {
        $this->extraDirs = $extraDirs;
    }

    /**
     * Adds extra directory to check for executable.
     *
     * @param string $dir
     */
    public function addExtraDir($dir)
    {
        $this->extraDirs[] = $dir;
    }

    /**
     * Finds an executable by name.
     *
     * @param string $name      The executable name (without the extension)
     * @param string $default   The default to return if no executable is found
     * @param array  $extraDirs Additional dirs to check into
     *
     * @return string The executable path or default value
     */
    public function find($name, $default = null, array $extraDirs = array())
    {
        if (ini_get('open_basedir')) {
            $searchPath = explode(PATH_SEPARATOR, getenv('open_basedir'));
            $dirs       = array();
            foreach ($searchPath as $path) {
                if (is_dir($path)) {
                    $dirs[] = $path;
                } else {
                    $file = str_replace(dirname($path), '', $path);
                    if ($file == $name && is_executable($path)) {
                        return $path;
                    }
                }
            }
        } else {
            $dirs = array_merge(
              explode(PATH_SEPARATOR, getenv('PATH') ? getenv('PATH') : getenv('Path')),
              $extraDirs
            );
        }

        $suffixes = array('');
        if (Symfony_Process_ProcessUtils::isWindows()) {
            $pathExt  = getenv('PATHEXT');
            $suffixes = $pathExt ? explode(PATH_SEPARATOR, $pathExt) : $this->suffixes;
        }
        foreach ($suffixes as $suffix) {
            foreach ($dirs as $dir) {
                if (is_file($file = $dir.DIRECTORY_SEPARATOR.$name.$suffix) && (Symfony_Process_ProcessUtils::isWindows() || is_executable($file))) {
                    return $file;
                }
            }
        }

        return $default;
    }
}
