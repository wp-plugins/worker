<?php

class MWP_Backup_ArrayHelper
{
    public static function getKey($array, $key, $default = null)
    {
        return is_array($array) && array_key_exists($key, $array) ? $array[$key] : $default;
    }
}