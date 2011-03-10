<?php

class Mmb_Helper
{
    /**
    * Creates an ajax response
    * 
    * @param mixed $status
    * @param mixed $msg
    */
    function _create_ajax_response($msg, $success = TRUE, $other_html = '')
    {
        $msg = base64_encode($msg);
        $success = $success ? 'true' : 'false';
        echo <<<EOT
<script type="text/javascript">
    mmbAjaxSuccess = $success;
    mmbAjaxMessage = "$msg";
</script>
$other_html
EOT;
        die();
    }
    
    /**
    * A helper function to log data
    * 
    * @param mixed $mixed
    */
    function _log($mixed)
    {
        if (is_array($mixed))
        {
            $mixed = print_r($mixed, 1);
        }
        else if (is_object($mixed))
        {
            ob_start();
            var_dump($mixed);
            $mixed = ob_get_clean();
        }
        
        $handle = fopen(dirname(__FILE__) . '/log', 'a');
        fwrite($handle, $mixed . PHP_EOL);
        fclose($handle);
    }
    
    /**
    * Strips WP disallowed HTML and PHP tags from a string
    * 
    * @param mixed $str
    * @return string
    */
    function _strip_tags($str)
    {
        return strip_tags($str, '<address><a><abbr><acronym><area><b><big><blockquote><br><caption><cite><class><code><col><del><dd><div><dl><dt><em><font><h1><h2><h3><h4><h5><h6><hr><i><img><ins><kbd><li><map><ol><p><pre><q><s><span><strike><strong><sub><sup><table><tbody><td><tfoot><tr><tt><ul><var>');
    }
    
    /**
    * Filters a WordPress content (being comments, pages, posts etc)
    * 
    * @param mixed $str
    * @return string
    */
    function _filter_content($str)
    {
        return nl2br($this->_strip_tags($str));
    }
    
    
    
    function _escape(&$array) {
        global $wpdb;

        if(!is_array($array)) {
            return($wpdb->escape($array));
        }
        else {
            foreach ( (array) $array as $k => $v ) {
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
    
    function _base64_encode($str)
    {
        // a plus sign can break the encoded string 
        // if sent via URL
        return str_replace('+', '|', base64_encode($str));
    }
    
    function _base64_decode($str)
    {
        return base64_decode(str_replace('|', '+', $str));
    }
    
    function _print_r($arr)
    {
        if (is_string($arr)) $arr = array($arr);
        echo '<pre>';
        print_r($arr);
        echo '</pre>';
    }
    
    /**
    * Initializes the file system
    * 
    */
    function _init_filesystem()
    { 
        global $wp_filesystem;
        
        if (!$wp_filesystem || !is_object($wp_filesystem))
        {
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

        if(trim($option_name) == ''){
            return FALSE;
        }
        
         global $wp_version;

        if (version_compare($wp_version, '2.8', '<'))
         return get_option($option_name);

      else if (version_compare($wp_version, '3.0', '<'))
           return get_transient($option_name);

      else
           return get_site_transient($option_name);

    }

    function mmb_null_op_buffer($buffer) {
        //do nothing
        if(!ob_get_level())
            ob_start(array($this, 'mmb_null_op_buffer'));
        return '';
    }

    function _deleteTempDir($directory) {
        if(substr($directory,-1) == "/") {
            $directory = substr($directory,0,-1);
        }
//            $this->_log($directory);
        if(!file_exists($directory) || !is_dir($directory)) {
            return false;
        } elseif(!is_readable($directory)) {
            return false;
        } else {
            $directoryHandle = opendir($directory);

            while ($contents = readdir($directoryHandle)) {
                if($contents != '.' && $contents != '..') {
                    $path = $directory . "/" . $contents;

                    if(is_dir($path)) {
                        $this->_deleteTempDir($path);
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
	function _last_worker_message($message){
		add_option('_worker_last_massage', serialize($message)) or update_option('_worker_last_massage', serialize($message));
	}
	function _get_last_worker_message(){
		$message = get_option('_worker_last_massage', array());
		delete_option('_worker_last_massage');
		return (array)maybe_unserialize($message);
	}
	function _is_ftp_writable_mmb(){
		if(defined('FTP_PASS')){
			return true;
		}
		else return false;
	}
}
