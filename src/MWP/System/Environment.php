<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_System_Environment
{
    public function getMemoryLimit()
    {
        return MWP_System_Utils::convertToBytes(ini_get('memory_limit'));
    }
}
