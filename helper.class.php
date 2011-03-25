<?php
/*************************************************************
 * 
 * helper.class.php
 * 
 * Utility functions
 * 
 * 
 * Copyright (c) 2011 Prelovac Media
 * www.prelovac.com
 **************************************************************/

class MMB_Helper
{
    /**
     * A helper function to log data
     * 
     * @param mixed $mixed
     */
    function _log($mixed)
    {
        if (is_array($mixed)) {
            $mixed = print_r($mixed, 1);
        } else if (is_object($mixed)) {
            ob_start();
            var_dump($mixed);
            $mixed = ob_get_clean();
        }
        
        $handle = fopen(dirname(__FILE__) . '/log', 'a');
        fwrite($handle, $mixed . PHP_EOL);
        fclose($handle);
    }
        
    function _escape(&$array)
    {
        global $wpdb;
        
        if (!is_array($array)) {
            return ($wpdb->escape($array));
        } else {
            foreach ((array) $array as $k => $v) {
                if (is_array($v)) {
                    $this->_escape($array[$k]);
                } else if (is_object($v)) {
                    //skip
                } else {
                    $array[$k] = $wpdb->escape($v);
                }
            }
        }
    }
    
    /**
     * Initializes the file system
     * 
     */
    function init_filesystem()
    {
        global $wp_filesystem;
        
        if (!$wp_filesystem || !is_object($wp_filesystem)) {
            WP_Filesystem();
        }
        
        if (!is_object($wp_filesystem))
            return FALSE;
        
        return TRUE;
    }
    
    /**
     *  Gets transient based on WP version
     *
     * @global string $wp_version
     * @param string $option_name
     * @return mixed
     */
    function mmb_get_transient($option_name)
    {
        if (trim($option_name) == '') {
            return FALSE;
        }
        
        global $wp_version, $_wp_using_ext_object_cache;
        
        if (version_compare($wp_version, '2.8.0', '<')) {
            return get_option($option_name);
        } else if (version_compare($wp_version, '3.0.0', '<')) {
            if (get_transient($option_name))
                return get_transient($option_name);
            else
                return get_option('_transient_' . $option_name);
        } else {
            if (get_site_transient($option_name))
                return get_site_transient($option_name);
            else
                return get_option('_site_transient_' . $option_name);
        }
    }
    
    function mmb_delete_transient($option_name)
    {
        if (trim($option_name) == '') {
            return FALSE;
        }
        
        global $wp_version;
        
        if (version_compare($wp_version, '2.8.0', '<')) {
            delete_option($option_name);
        } else if (version_compare($wp_version, '3.0.0', '<')) {
            if (delete_transient($option_name))
                delete_transient($option_name);
            else
                delete_option('_transient_' . $option_name);
        } else {
            if (delete_site_transient($option_name))
                delete_site_transient($option_name);
            else
                delete_option('_site_transient_' . $option_name);
        }
    }
      
    function delete_temp_dir($directory)
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
                    $path = $directory . "/" . $contents;
                    
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
    
    function set_worker_message_id( $message_id = false)
    {
        if ($message_id) {
            add_option('_action_message_id', $message_id) or update_option('_action_message_id', $message_id);
            return $message_id;
        }
        return false;
    }
    
    function get_worker_message_id()
    {
        return (int) get_option('_action_message_id');
    }
    
    function set_master_public_key($public_key = false)
    {
        if ($public_key && !get_option('_worker_public_key')) {
            add_option('_worker_public_key', base64_encode($public_key));
            return true;
        }
        return false;
    }
    
    function get_master_public_key()
    {
        if (!get_option('_worker_public_key'))
            return false;
        return base64_decode(get_option('_worker_public_key'));
    }
    
       
    function get_random_signature()
    {
        if (!get_option('_worker_nossl_key'))
            return false;
        return base64_decode(get_option('_worker_nossl_key'));
    }
    
    function set_random_signature($random_key = false)
    {
        if ($random_key && !get_option('_worker_nossl_key')) {
            add_option('_worker_nossl_key', base64_encode($random_key));
            return true;
        }
        return false;
    }
    
    
    function authenticate_message($data = false, $signature = false, $message_id = false)
    {
        if (!$data && !$signature) {
            return array(
                'error' => 'Authentication failed.'
            );
        }
        
        $current_message = $this->get_worker_message_id();
        
        if ((int) $current_message > (int) $message_id)
            return array(
                'error' => 'Invalid message recieved. You can try to reinstall worker plugin and re-add the site to your account.'
            );
        
        $pl_key = $this->get_master_public_key();
        if (!$pl_key) {
            return array(
                'error' => 'Authentication failed (public key). You can try to reinstall worker plugin and re-add the site to your account.'
            );
        }
        
        if (function_exists('openssl_verify') && !$this->get_random_signature()) {
            $verify = openssl_verify($data, $signature, $pl_key);
            if ($verify == 1) {
                $message_id = $this->set_worker_message_id( $message_id);
                return true;
            } else if ($verify == 0) {
                return array(
                    'error' => 'Invalid message signature. You can try to reinstall worker plugin and re-add the site to your account.'
                );
            } else {
                return array(
                    'error' => 'Command not successful! Please try again.'
                );
            }
        } else if ($this->get_random_signature()) {
            if (md5($data . $this->get_random_signature()) == $signature) {
                $message_id = $this->set_worker_message_id( $message_id);
                return true;
            }
            return array(
                'error' => 'Invalid message signature. You can try to reinstall the worker plugin and then re-add the site to your dashboard.'
            );
        }
        // no rand key - deleted in get_stat maybe
        else
            return array(
                'error' => 'Invalid message signature, try reinstalling worker plugin and re-adding the site to your dashboard.'
            );
    }
    
    function check_if_user_exists($username = false)
    {
		global $wpdb;
        if ($username) {
            require_once(ABSPATH . WPINC . '/registration.php');
            include_once(ABSPATH . 'wp-includes/pluggable.php');
            
            if (username_exists($username) == null) {
                return false;
            }
            $user = (array)get_userdatabylogin($username);
            if ($user[$wpdb->prefix.'user_level'] == 10 || isset($user[$wpdb->prefix.'capabilities']['administrator'])) {
                define('MMB_USER_CAPABILITIES', $user->wp_user_level);
                return true;
            }
            return false;
        }
        return false;
    }
    
    function refresh_updates()
    {
        if (rand(1, 3) == '2') {
            require_once(ABSPATH . WPINC . '/update.php');
            wp_update_plugins();
            wp_update_themes();
            wp_version_check();
        }
    }
    
    function remove_http($url = '')
    {
        if ($url == 'http://' OR $url == 'https://') {
            return $url;
        }
        return preg_replace('/^(http|https)\:\/\/(www.)?/i', '', $url);
        
    }
    
    function mmb_get_error($error_object)
    {
        if (!is_wp_error($error_object)) {
            return $error_object != '' ? $error_object : ' error occured.';
        } else {
            $errors = array();
            foreach ($error_object->error_data as $error_key => $error_string) {
                $errors[] = str_replace('_', ' ', ucfirst($error_key)) . ' - ' . $error_string;
            }
            return implode('<br />', $errors);
        }
    }
    
    
}
?>