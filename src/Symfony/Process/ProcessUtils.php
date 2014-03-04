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
 * ProcessUtils is a bunch of utility methods.
 *
 * This class contains static methods only and is not meant to be instantiated.
 *
 * @author Martin Hasoň <martin.hason@gmail.com>
 */
class Symfony_Process_ProcessUtils
{
    /**
     * This class should not be instantiated
     */
    private function __construct()
    {
    }

    /**
     * Escapes a string to be used as a shell argument.
     *
     * @param string $argument The argument that will be escaped
     *
     * @return string The escaped argument
     */
    public static function escapeArgument($argument)
    {
        //Fix for PHP bug #43784 escapeshellarg removes % from given string
        //Fix for PHP bug #49446 escapeshellarg doesn't work on Windows
        //@see https://bugs.php.net/bug.php?id=43784
        //@see https://bugs.php.net/bug.php?id=49446
        if (Symfony_Process_ProcessUtils::isWindows()) {
            if ('' === $argument) {
                return escapeshellarg($argument);
            }

            $escapedArgument = '';
            foreach (preg_split('/([%"])/i', $argument, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE) as $part) {
                if ('"' === $part) {
                    $escapedArgument .= '\\"';
                } elseif ('%' === $part) {
                    $escapedArgument .= '^%';
                } else {
                    $escapedArgument .= escapeshellarg($part);
                }
            }

            return $escapedArgument;
        }

        return escapeshellarg($argument);
    }

    public static function isWindows()
    {
        return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    }

    /**
     * Backport of PHP >=5.3 function array_replace
     *
     * @link http://www.php.net/manual/en/function.array-replace.php#94458
     */
    public static function arrayReplace(array $array, array $array1)
    {
        $args  = func_get_args();
        $count = func_num_args();

        for ($i = 0; $i < $count; ++$i) {
            if (is_array($args[$i])) {
                foreach ($args[$i] as $key => $val) {
                    $array[$key] = $val;
                }
            } else {
                trigger_error(
                  __FUNCTION__.'(): Argument #'.($i + 1).' is not an array',
                  E_USER_WARNING
                );

                return null;
            }
        }

        return $array;
    }
}
