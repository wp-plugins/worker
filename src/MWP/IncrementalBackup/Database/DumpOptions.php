<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_IncrementalBackup_Database_DumpOptions
{

    /**
     * @var string[]
     */
    private $tables = null;

    /**
     * @var bool
     */
    private $skipLockTables = true;

    /**
     * @var bool
     */
    private $skipExtendedInsert = true;

    /**
     * @var bool
     */
    private $dropTables = true;

    /**
     * @return array
     */
    public function getTables()
    {
        return $this->tables;
    }

    /**
     * @param array $tables
     */
    public function setTables($tables)
    {
        $this->tables = $tables;
    }

    /**
     * @return boolean
     */
    public function isSkipLockTables()
    {
        return $this->skipLockTables;
    }

    /**
     * @param boolean $skipLockTables
     */
    public function setSkipLockTables($skipLockTables)
    {
        $this->skipLockTables = $skipLockTables;
    }

    /**
     * @return boolean
     */
    public function isSkipExtendedInsert()
    {
        return $this->skipExtendedInsert;
    }

    /**
     * @param boolean $skipExtendedInsert
     */
    public function setSkipExtendedInsert($skipExtendedInsert)
    {
        $this->skipExtendedInsert = $skipExtendedInsert;
    }

    /**
     * @return boolean
     */
    public function isDropTables()
    {
        return $this->dropTables;
    }

    /**
     * @param boolean $dropTables
     */
    public function setDropTables($dropTables)
    {
        $this->dropTables = $dropTables;
    }

    public static function createFromArray(array $options = array())
    {
        $dumpOptions = new self();

        if (isset($options['skip_lock_tables'])) {
            $dumpOptions->setSkipLockTables($options['skip_lock_tables']);
        }

        if (isset($options['skip_extended_insert'])) {
            $dumpOptions->setSkipExtendedInsert($options['skip_extended_insert']);
        }

        return $dumpOptions;
    }
}
