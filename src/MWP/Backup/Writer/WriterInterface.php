<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

interface MWP_Backup_Writer_WriterInterface
{
    public function open();

    public function write($content = '');

    public function close();

    public function writeLine($content = '');

    public function consoleOutput($outputCommand, $destination, $options = null);
}
