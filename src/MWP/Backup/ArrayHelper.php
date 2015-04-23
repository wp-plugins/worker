<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Backup_ArrayHelper
{
    public static function getKey($array, $key, $default = null)
    {
        return is_array($array) && array_key_exists($key, $array) ? $array[$key] : $default;
    }

    public static function arrayColumn($array, $columnIndex = 0)
    {
        $result = array();
        foreach ($array as $arr) {
            if (!is_array($arr)) {
                continue;
            }

            $arr = array_values($arr);
            $result[] = $arr[$columnIndex];
        }

        return $result;
    }
}
