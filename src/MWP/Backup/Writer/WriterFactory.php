<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Backup_Writer_WriterFactory
{
    /**
     * @param string $filename
     * @param string $type     Writer type
     * @param string $mode
     *
     * @return MWP_Backup_Writer_WriterInterface
     */
    public static function make($filename, $type = null, $mode = 'w')
    {
        switch ($type) {
            case 'gzip':
                $classname = 'MWP_Backup_Writer_GzipWriter';

                break;
            case 'none':
                $classname = 'MWP_Backup_Writer_PlainWriter';

                break;
            default:
                if (function_exists('gzopen') && substr_compare($filename, '.gz', -3, 3, true) === 0) {
                    $classname = 'MWP_Backup_Writer_GzipWriter';
                } else {
                    $classname = 'MWP_Backup_Writer_PlainWriter';
                }
                break;
        }
        $writer = new $classname($filename, $mode);

        return $writer;
    }
}
