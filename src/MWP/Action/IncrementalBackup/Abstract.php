<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Action_IncrementalBackup_Abstract extends MWP_Action_Abstract
{
    /**
     * @param array $result
     *
     * @return array
     */
    protected function createResult(array $result)
    {
        return array(
            'result' => $result,
            'server' => $this->getServerStatistics()->toArray(),
        );
    }

    /**
     * Get file real path given a path relative to WordPress root.
     *
     * @param $relativePath
     *
     * @return string
     */
    protected function getRealPath($relativePath)
    {
        return realpath(untrailingslashit(ABSPATH).'/'.$relativePath);
    }

    /**
     * @return MWP_IncrementalBackup_Model_ServerStatistics
     */
    private function getServerStatistics()
    {
        return MWP_IncrementalBackup_Model_ServerStatistics::factory();
    }

}
