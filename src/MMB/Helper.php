<?php

/*************************************************************
 * helper.class.php
 * Utility functions
 * Copyright (c) 2011 Prelovac Media
 * www.prelovac.com
 **************************************************************/
class MMB_Helper
{

    public $mmb_multisite;

    public function mmb_get_user_info($user_info = false, $info = 'login')
    {
        if ($user_info === false) {
            return false;
        }

        if (strlen(trim($user_info)) == 0) {
            return false;
        }

        return get_user_by($info, $user_info);
    }

    /**
     * Call action item filters
     */
    public function mmb_parse_action_params($key = '', $params = null, $call_object = null)
    {
        global $_mmb_item_filter;
        $call_object = $call_object !== null ? $call_object : $this;
        $return      = array();

        if (isset($_mmb_item_filter[$key]) && !empty($_mmb_item_filter[$key])) {
            if (isset($params['item_filter']) && !empty($params['item_filter'])) {
                foreach ($params['item_filter'] as $_items) {
                    if (!empty($_items)) {
                        foreach ($_items as $_item) {
                            if (isset($_item[0]) && in_array($_item[0], $_mmb_item_filter[$key])) {
                                $_item[1] = isset($_item[1]) ? $_item[1] : array();
                                $return   = call_user_func(array(&$call_object, 'get_'.$_item[0]), $return, $_item[1]);
                            }
                        }
                    }
                }
            }
        }

        return $return;
    }

    /**
     * Check if function exists or not on `suhosin` black list
     */
    public function mmb_function_exists($function_callback)
    {
        if (!function_exists($function_callback)) {
            return false;
        }

        $disabled = explode(', ', @ini_get('disable_functions'));
        if (in_array($function_callback, $disabled)) {
            return false;
        }

        if (extension_loaded('suhosin')) {
            $suhosin = @ini_get("suhosin.executor.func.blacklist");
            if (empty($suhosin) == false) {
                $suhosin   = explode(',', $suhosin);
                $blacklist = array_map('trim', $suhosin);
                $blacklist = array_map('strtolower', $blacklist);
                if (in_array($function_callback, $blacklist)) {
                    return false;
                }
            }
        }

        return true;
    }

    public function mmb_get_transient($option_name)
    {
        if (trim($option_name) == '') {
            return false;
        }
        if (!empty($this->mmb_multisite)) {
            return $this->mmb_get_sitemeta_transient($option_name);
        }

        $transient = get_option('_site_transient_'.$option_name);

        return apply_filters("site_transient_".$option_name, $transient);
    }

    public function mmb_delete_transient($option_name)
    {
        if (trim($option_name) == '') {
            return;
        }

        delete_option('_site_transient_'.$option_name);
    }

    public function mmb_get_sitemeta_transient($option_name)
    {
        /** @var wpdb $wpdb */
        global $wpdb;
        $option_name = '_site_transient_'.$option_name;

        $result = $wpdb->get_var($wpdb->prepare("SELECT `meta_value` FROM `{$wpdb->sitemeta}` WHERE meta_key = '%s' AND `site_id` = '%s'", $option_name, $this->mmb_multisite));
        $result = maybe_unserialize($result);

        return $result;
    }

    public function mmb_set_sitemeta_transient($option_name, $option_value)
    {
        /** @var wpdb $wpdb */
        global $wpdb;
        $option_name = '_site_transient_'.$option_name;

        if ($this->mmb_get_sitemeta_transient($option_name)) {
            $result = $wpdb->update(
                $wpdb->sitemeta,
                array(
                    'meta_value' => maybe_serialize($option_value),
                ),
                array(
                    'meta_key' => $option_name,
                    'site_id'  => $this->mmb_multisite,
                )
            );
        } else {
            $result = $wpdb->insert(
                $wpdb->sitemeta,
                array(
                    'meta_key'   => $option_name,
                    'meta_value' => maybe_serialize($option_value),
                    'site_id'    => $this->mmb_multisite,
                )
            );
        }

        return $result;
    }

    public function delete_temp_dir($directory)
    {
        if (substr($directory, -1) == "/") {
            $directory = substr($directory, 0, -1);
        }
        if (!file_exists($directory) || !is_dir($directory)) {
            return false;
        } elseif (!is_readable($directory)) {
            return false;
        } else {
            $directoryHandle = opendir($directory);

            while ($contents = readdir($directoryHandle)) {
                if ($contents != '.' && $contents != '..') {
                    $path = $directory."/".$contents;

                    if (is_dir($path)) {
                        $this->delete_temp_dir($path);
                    } else {
                        unlink($path);
                    }
                }
            }
            closedir($directoryHandle);
            rmdir($directory);

            return true;
        }
    }

    public function set_worker_message_id($message_id = false)
    {
        if ($message_id) {
            add_option('_action_message_id', $message_id) or update_option('_action_message_id', $message_id);

            return $message_id;
        }

        return false;
    }

    public function get_master_public_key()
    {
        if (!get_option('_worker_public_key')) {
            return false;
        }

        return base64_decode(get_option('_worker_public_key'));
    }

    public function get_random_signature()
    {
        if (!get_option('_worker_nossl_key')) {
            return false;
        }

        return base64_decode(get_option('_worker_nossl_key'));
    }

    public function get_secure_hash()
    {
        $pl_key = $this->get_master_public_key();
        if (empty($pl_key) || $this->get_random_signature() !== false) {
            $pl_key = $this->get_random_signature();
        }

        if (!empty($pl_key)) {
            return md5(base64_encode($pl_key));
        }

        return false;
    }

    public function _secure_data($data = false)
    {
        if ($data == false) {
            return false;
        }

        $pl_key = $this->get_master_public_key();
        if (!$pl_key) {
            return false;
        }

        $secure = '';
        if (function_exists('openssl_public_decrypt') && !$this->get_random_signature()) {
            if (is_array($data) && !empty($data)) {
                foreach ($data as $input) {
                    openssl_public_decrypt($input, $decrypted, $pl_key);
                    $secure .= $decrypted;
                }
            } else {
                if (is_string($data)) {
                    openssl_public_decrypt($data, $decrypted, $pl_key);
                    $secure = $decrypted;
                } else {
                    $secure = $data;
                }
            }

            return $secure;
        }

        return false;
    }

    public function encrypt_data($data = false)
    {
        if (empty($data)) {
            return $data;
        }

        $pl_key = $this->get_master_public_key();
        if (!$pl_key) {
            return false;
        }

        $data    = serialize($data);
        $crypted = '';
        if (function_exists('openssl_public_encrypt') && !$this->get_random_signature()) {
            $length = strlen($data);
            if ($length > 100) {
                for ($i = 0; $i <= $length + 100; $i = $i + 100) {
                    $input = substr($data, $i, 100);
                    openssl_public_encrypt($input, $crypt, $pl_key);
                    $crypted .= base64_encode($crypt).'::';
                }
            } else {
                openssl_public_encrypt($data, $crypted, $pl_key);
            }
        } else {
            $crypted = base64_encode($data);
        }

        return $crypted;
    }

    public function remove_http($url = '')
    {
        if ($url == 'http://' or $url == 'https://') {
            return $url;
        }

        return preg_replace('/^(http|https)\:\/\/(www.)?/i', '', $url);
    }

    public function mmb_get_error($error_object)
    {
        if (!is_wp_error($error_object)) {
            return $error_object != '' ? $error_object : '';
        } else {
            $errors = array();
            if (!empty($error_object->error_data)) {
                foreach ($error_object->error_data as $error_key => $error_string) {
                    $errors[] = str_replace('_', ' ', ucfirst($error_key)).': '.$error_string;
                }
            } elseif (!empty($error_object->errors)) {
                foreach ($error_object->errors as $error_key => $err) {
                    $errors[] = 'Error: '.str_replace('_', ' ', strtolower($error_key));
                }
            }

            return implode('<br />', $errors);
        }
    }

    public function is_server_writable()
    {
        if ((!defined('FTP_HOST') || !defined('FTP_USER')) && (get_filesystem_method(array(), false) != 'direct')) {
            return false;
        } else {
            return true;
        }
    }

    public function w3tc_flush($flushAll = false)
    {
        if ($flushAll) {
            if (function_exists('w3tc_pgcache_flush')) {
                w3tc_pgcache_flush();
            }

            if (function_exists('w3tc_dbcache_flush')) {
                w3tc_dbcache_flush();
            }
        }

        if (function_exists('w3tc_objectcache_flush')) {
            w3tc_objectcache_flush();
        }
    }

    protected function notifyMyself($functionName, $args = array())
    {
        if (mwp_container()->getParameter('disable_ping_back')) {
            do_action($functionName, $args);
            return;
        }

        global $current_user;
        $nonce      = wp_create_nonce("mmb-fork-nonce");
        $cron_url   = site_url('index.php');
        $public_key = get_option('_worker_public_key');
        $args       = array(
            'body'      => array(
                'mwp_forked_action' => $functionName,
                'args'              => json_encode($args),
                'mmb_fork_nonce'    => $nonce,
                'public_key'        => $public_key,
                'username'          => $current_user->user_login,
            ),
            'timeout'   => 0.01,
            'blocking'  => false,
            'sslverify' => apply_filters('https_local_ssl_verify', true),
        );
        wp_remote_post($cron_url, $args);
    }

    public function getUsersIDs()
    {
        global $wpdb;
        $users_authors = array();
        $users         = $wpdb->get_results("SELECT ID as user_id, display_name FROM $wpdb->users WHERE user_status=0");

        foreach ($users as $user_key => $user_val) {
            $users_authors[$user_val->user_id] = $user_val->display_name;
        }

        return $users_authors;
    }
}
