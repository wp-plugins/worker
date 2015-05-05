<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

interface MWP_IncrementalBackup_Database_ConnectionInterface
{
    /**
     * @param string $query
     *
     * @return MWP_IncrementalBackup_Database_StatementInterface
     */
    public function query($query);

    /**
     * @param mixed $value any primitive value
     *
     * @return string Quoted string e.g. 'hello' with opening and closing quotes
     */
    public function quote($value);
}
