<?php
/*************************************************************
 * 
 * backup.class.php
 * 
 * Manage Backups
 * 
 * 
 * Copyright (c) 2011 Prelovac Media
 * www.prelovac.com
 **************************************************************/

class MMB_Backup extends MMB_Core
{
    function __construct()
    {
        parent::__construct();
    }
    
    function backup($args)
    {		
    		@ini_set('memory_limit', '300M');
				@set_time_limit(300);
        //type like manual, weekly, daily
        $type = $args['type'];
        //what like full or only db
        $what = $args['what'];
        
        if (trim($type) == '')
            $type = 'manual'; //default
        $sec_string = md5('mmb-worker');
        $file       = "/$sec_string/mwp_backups";
        $file_path  = WP_CONTENT_DIR . $file;
        @ini_set('memory_limit', '300M');
				@set_time_limit(300);       
        if (!file_exists($file_path)) {
            if (!mkdir($file_path, 0755, true))
                return array(
                    'error' => 'Permission denied, make sure you have write permission to wp-content folder.'
                );
        }
        file_put_contents($file_path . '/index.php', ''); //safe
       
        if (trim($what) == 'full') {
            //take wp-content backup
            $content_backup = $this->backup_wpcontent($type);
            if (!$content_backup) {
                @unlink($content_backup['path']);
                return array(
                    'error' => 'Failed to backup wp-content.'
                );
            }
        }
        
        if (trim($what) == 'full' || trim($what) == 'db') {
            //take database backup
            $db_backup = $this->backup_db($type);
            if (!$db_backup) {
                if (trim($what) == 'full')
                    @unlink($content_backup['path']);
                
                @unlink($db_backup['path']);
                return array(
                    'error' => 'Failed to backup database.'
                );
            }
        }
        
        include_once ABSPATH . '/wp-admin/includes/class-pclzip.php';
        
        // Get previous backup in tmp
        $worker_options = get_option('mmb-worker');

        $tmp_file       = WP_CONTENT_DIR . '/' . basename($worker_options['backups'][$type]['path']);
        
        if (rename($worker_options['backups'][$type]['path'], $tmp_file)) {
            @unlink($worker_options['backups'][$type]['path']);
        }
        
        //Prepare .zip file name
        $site_name   = $this->remove_http(get_bloginfo('url'));
        $site_name   = str_replace(array(
            "_",
            "/"
        ), array(
            "",
            "-"
        ), $site_name);
        
        $hash = md5(time());
        $backup_file = $file_path . '/' . $site_name . '_' . $type . '_' . $what . '_' . date('Y-m-d') .'_'.$hash. '.zip';
        
        if ($this->mmb_exec('which zip') == false) {
            $archive = new PclZip($backup_file);
        }
        
        if (trim($what) == 'full') {
            $htaccess_path  = ABSPATH . ".htaccess";
            $wp_config_path = ABSPATH . "wp-config.php";
            if ($this->mmb_exec('which zip')) {
                $command = "zip $backup_file -j $content_backup[path] -j $db_backup[path] -j $htaccess_path -j $wp_config_path";
                ob_start();
                $result = $this->mmb_exec($command);
                ob_get_clean();
            } else {
                $result = $archive->add($content_backup['path'], PCLZIP_OPT_REMOVE_ALL_PATH);
                $result = $archive->add($db_backup['path'], PCLZIP_OPT_REMOVE_ALL_PATH);
                $result = $archive->add($htaccess_path, PCLZIP_OPT_REMOVE_ALL_PATH);
                $result = $archive->add($wp_config_path, PCLZIP_OPT_REMOVE_ALL_PATH);
                
            }
            
        } elseif (trim($what) == 'db') {
            if ($this->mmb_exec('which zip')) {
              $command = "zip $backup_file -j $db_backup[path]";
                ob_start();
                $result = $this->mmb_exec($command);
                ob_get_clean();
            } else {
            	 
                $result = $archive->add($db_backup['path'], PCLZIP_OPT_REMOVE_ALL_PATH);
            }
        }
        
        if (!$result) {
            if (rename($tmp_file, $worker_options['backups'][$type]['path'])) {
                @unlink($tmp_file);
            }
            return array(
                'error' => 'Backup failed. Cannot create backup zip file.'
            );
        }
        
        @unlink($tmp_file);
        @unlink($content_backup['path']);
        @unlink($db_backup['path']);
        
        $backup_url     = WP_CONTENT_URL . $file . '/' . $site_name . '_' . $type . '_' . $what . '_' . date('Y-m-d') .'_'.$hash. '.zip';
        
        $worker_options = get_option('mmb-worker');
        //remove old file
        if ($worker_options['backups'][$type]['path'] != $backup_file) {
            @unlink($worker_options['backups'][$type]['path']);
        }
        
        $worker_options['backups'][$type]['path'] = $backup_file;
        $worker_options['backups'][$type]['url']  = $backup_url;
        update_option('mmb-worker', $worker_options);
        
       
        //Everything went fine, return backup url to master
        return $worker_options['backups'][$type]['url'];
    }
    
    function backup_wpcontent($type)
    {
        $sec_string = md5('mmb-worker');
        $file       = '/' . $sec_string . '/mwp_backups/wp-content_' . date('Y-m-d') . '.zip';
        $file_path  = WP_CONTENT_DIR . $file;
        $content_dir = explode("/",WP_CONTENT_DIR);
        $content_dir = $content_dir[(count($content_dir)-1)];
        
        if ($this->mmb_exec('which zip')) {
           	
           	chdir(ABSPATH);
            $command = "zip -r $file_path './' -x '$content_dir/" . $sec_string . "/*'";
            ob_start();
            $result = $this->mmb_exec($command);
            ob_get_clean();
            $file_url = WP_CONTENT_URL . $file;
            
            if($result)
            {
            	return array(
                    'path' => $file_path,
                    'url' => $file_url
            	);
          	}
          	else
          	{
          		return false;
          	}
            
            
        } else {
            require_once ABSPATH . '/wp-admin/includes/class-pclzip.php';
            $archive = new PclZip($file_path);
						$result  = $archive->add(ABSPATH, PCLZIP_OPT_REMOVE_PATH, ABSPATH);           
            $result = $archive->delete(PCLZIP_OPT_BY_NAME, $content_dir.'/'.$sec_string.'/');
            if ($result) {
                $file_url = WP_CONTENT_URL . $file;
                return array(
                    'path' => $file_path,
                    'url' => $file_url
                );
            }
            @unlink($file_path);
            return false;
        }
    }
    
    
    function backup_db($type)
    {
        $mysqldump_exists = $this->check_mysqldump();
        if (is_array($mysqldump_exists)) {
            $result = $this->backup_db_dump($type, $mysqldump_exists);
            
        } else {
            $result = $this->backup_db_php($type);
            
        }
        return $result;
    }
    
    function backup_db_dump($type, $paths)
    {		
        global $wpdb;
        $sec_string = md5('mmb-worker');
        $brace      = (substr(PHP_OS, 0, 3) == 'WIN') ? '"' : '';
        
        $file     = WP_CONTENT_DIR . '/' . DB_NAME . '.sql';
        $file_url = WP_CONTENT_URL . '/' . DB_NAME . '.sql';
        
        $command = $brace . $paths['mysqldump'] . $brace . ' --host="' . DB_HOST . '" --user="' . DB_USER . '" --password="' . DB_PASSWORD . '" --add-drop-table --skip-lock-tables "' . DB_NAME . '" > ' . $brace . $file . $brace;
        ob_start();
        $result = $this->mmb_exec($command);
        ob_get_clean();
        
        if (!$result) {
            $result = $this->backup_db_php($type);
            return $result;
        }
        
        if (filesize($file) == 0 || !is_file($file) || !$result) {
            @unlink($file);
            return false;
        } else {
            return array(
                'path' => $file,
                'url' => $file_url
            );
        }
    }
    
    function backup_db_php($type)
    {
        global $wpdb;
        $tables        = $wpdb->get_results('SHOW TABLES', ARRAY_N);
        $sec_string    = md5('mmb-worker');
        $zip_file      = '/' . $sec_string . '/mwp_backups/db_' . date('Y-m-d') . '.zip';
        $zip_file_path = WP_CONTENT_DIR . $zip_file;
        
        $file     = WP_CONTENT_DIR . '/' . DB_NAME . '.sql';
        $file_url = WP_CONTENT_URL . '/' . DB_NAME . '.sql';
        
        foreach ($tables as $table) {
            //drop exixting table
            $dump_data    = "DROP TABLE IF EXISTS $table[0];";
            //create table
            $create_table = $wpdb->get_row("SHOW CREATE TABLE $table[0]", ARRAY_N);
            $dump_data .= "\n\n" . $create_table[1] . ";\n\n";
            
            $count = $wpdb->get_var("SELECT count(*) FROM $table[0]");
            if ($count > 100)
                $count = ceil($count / 100) - 1;
            else
                $count = 1;
            for ($i = 0; $i < $count; $i++) {
                $low_limit = $i * 100;
                $qry       = "SELECT * FROM $table[0] LIMIT $low_limit, 100";
                $rows      = $wpdb->get_results($qry, ARRAY_A);
                if (is_array($rows)) {
                    foreach ($rows as $row) {
                        //insert single row
                        $dump_data .= "INSERT INTO $table[0] VALUES(";
                        $num_values = count($row);
                        $j          = 1;
                        foreach ($row as $value) {
                            $value = addslashes($value);
                            $value = ereg_replace("\n", "\\n", $value);
                            $num_values == $j ? $dump_data .= "'" . $value . "'" : $dump_data .= "'" . $value . "', ";
                            $j++;
                            unset($value);
                        }
                        $dump_data .= ");\n";
                    }
                }
            }
            $dump_data .= "\n\n\n";
            
            unset($rows);
            file_put_contents($file, $dump_data, FILE_APPEND);
            unset($dump_data);
        }
        if (filesize($file) == 0 || !is_file($file)) {
            @unlink($file);
            return false;
        }
        
        return array(
            'path' => $file,
            'url' => $file_url
        );
        
    }
    
    function restore($args)
    {		
    		
        $type = $args['type'];
        if (trim($type) == '') {
            return false;
        }
        
        // Set paths
        $sec_string  = md5('mmb-worker');
        $file        = "/$sec_string/restore";
        $file_path   = WP_CONTENT_DIR . $file; //restore path - temporary
        $backup_path = WP_CONTENT_DIR;
        @ini_set('memory_limit', '300M');
				@set_time_limit(300);
        
        // Getting file from worker
        $worker_options = get_option('mmb-worker');
        $backup_file = $worker_options['backups'][$type]['path'];
        
        if ($backup_file && file_exists($backup_file)) {
            if ($this->mmb_exec('which unzip')) {
                if (!mkdir($file_path))
                    return array(
                        'error' => 'Failed to create restore folder.'
                    );
                
                chdir($file_path);
                $command = "unzip -o $backup_file";
                ob_start();
                $result = $this->mmb_exec($command);
                ob_get_clean();
                
            } else {
                require_once ABSPATH . '/wp-admin/includes/class-pclzip.php';
                $archive   = new PclZip($backup_file);
                $result = $archive->extract(PCLZIP_OPT_PATH, $file_path, PCLZIP_OPT_REMOVE_ALL_PATH);
                
            }
            
            if (!$result) {
                return array(
                    'error' => 'Error extracting backup file.'
                );
            }
            
            list(, $name, $what) = explode('_', basename($backup_file, '.zip'));
            
            if (trim($what) == 'full' || trim($what) == 'db') {
                if (!$this->restore_db($type, $file_path)) {
                    return array(
                        'error' => 'Error restoring database.'
                    );
                }
            }
            
            if (trim($what) == 'full') {
                if (!$this->restore_wpcontent($type, $file_path)) {
                    return array(
                        'error' => 'Error restoring wp-content.'
                    );
                }
            }
            
            if($del_backup_later)
            {
            	@unlink($backup_file);
            }
            $this->delete_temp_dir($file_path);
        } else {
            return array(
                'error' => 'Error restoring. Cannot find backup file.'
            );
        }
        
        return true;
    }
    
    function restore_wpcontent($type, $file_path)
    {
        require_once ABSPATH . '/wp-admin/includes/class-pclzip.php';
        $content_file   = glob($file_path . "/*.zip");
        $wp_config_file = glob($file_path . "/wp-config.php");
        $htaccess_file  = glob($file_path . "/.htaccess");
        if ($this->mmb_exec('which unzip')) {
            //chdir(WP_CONTENT_DIR);
            chdir(ABSPATH);
            $con_file = $content_file[0];
            $command  = "unzip -o $con_file";
            ob_start();
            $result = $this->mmb_exec($command);
            ob_get_clean();
        } else {
            $archive         = new PclZip($content_file[0]);
            $result = $archive->extract(PCLZIP_OPT_PATH, ABSPATH, PCLZIP_OPT_REPLACE_NEWER);
            
        }
        
        @rename($wp_config_file[0], ABSPATH . "wp-config.php");
        @rename($htaccess_file[0], ABSPATH . ".htaccess");
        @unlink($wp_config_file[0]);
        @unlink($htaccess_file[0]);
        
        if ($result)
            return true;
        else
            return false;
    }
    
    function restore_db($type, $file_path)
    {
        global $wpdb;
        
        $mysqldump = $this->check_mysqldump();
        
        if (is_array($mysqldump)) {
            $brace = (substr(PHP_OS, 0, 3) == 'WIN') ? '"' : '';
            
            foreach (glob($file_path . '/*.sql') as $filename) {
                $command = $brace . $mysqldump['mysql'] . $brace . ' --host="' . DB_HOST . '" --user="' . DB_USER . '" --password="' . DB_PASSWORD . '" ' . DB_NAME . ' < ' . $brace . $filename . $brace;
                ob_start();
                $result = $this->mmb_exec($command);
                ob_get_clean();
                break;
            }
            
            if (!$result) //try php
                {
                foreach (glob($file_path . '/*.sql') as $filename) {
                    $current_query = '';
                    // Read in entire file
                    $lines         = file($filename);
                    // Loop through each line
                    foreach ($lines as $line) {
                        // Skip it if it's a comment
                        if (substr($line, 0, 2) == '--' || $line == '')
                            continue;
                        
                        // Add this line to the current query
                        $current_query .= $line;
                        // If it has a semicolon at the end, it's the end of the query
                        if (substr(trim($line), -1, 1) == ';') {
                            // Perform the query
                            $result = $wpdb->query($current_query);
                            if ($result === false)
                                return FALSE;
                            // Reset temp variable to empty
                            $current_query = '';
                        }
                    }
                }
                return true;
            } else {
                return true;
            }
            
        } else {
            foreach (glob($file_path . '/*.sql') as $filename) {
                // Temporary variable, used to store current query
                $current_query = '';
                // Read in entire file
                $lines         = file($filename);
                // Loop through each line
                foreach ($lines as $line) {
                    // Skip it if it's a comment
                    if (substr($line, 0, 2) == '--' || $line == '')
                        continue;
                    
                    // Add this line to the current query
                    $current_query .= $line;
                    // If it has a semicolon at the end, it's the end of the query
                    if (substr(trim($line), -1, 1) == ';') {
                        // Perform the query
                        $result = $wpdb->query($current_query);
                        if ($result === false)
                            return FALSE;
                        // Reset temp variable to empty
                        $current_query = '';
                    }
                }
            }
            return true;
        }
    }
    
    function optimize_tables()
    {
        global $wpdb;
        $tables = $wpdb->get_col("SHOW TABLES");
        
        foreach ($tables as $table_name) {
            $table_string .= $table_name . ",";
        }
        $table_string = rtrim($table_string);
        $optimize     = $wpdb->query("OPTIMIZE TABLE $table_string");
        return $optimize ? true : false;
    }
    
    ### Function: Auto Detect MYSQL and MYSQL Dump Paths
    function check_mysqldump()
    {
        global $wpdb;
        $paths = array(
            'mysq' => '',
            'mysqldump' => ''
        );
        if (substr(PHP_OS, 0, 3) == 'WIN') {
            $mysql_install = $wpdb->get_row("SHOW VARIABLES LIKE 'basedir'");
            if ($mysql_install) {
                $install_path       = str_replace('\\', '/', $mysql_install->Value);
                $paths['mysql']     = $install_path . 'bin/mysql.exe';
                $paths['mysqldump'] = $install_path . 'bin/mysqldump.exe';
            } else {
                $paths['mysql']     = 'mysql.exe';
                $paths['mysqldump'] = 'mysqldump.exe';
            }
        } else {
            if ($this->check_sys()) {
                $paths['mysql']     = $this->mmb_exec('which mysql',true);
                $paths['mysqldump'] = $this->mmb_exec('which mysqldump',true);
            } else {
                $paths['mysql']     = 'mysql';
                $paths['mysqldump'] = 'mysqldump';
            }
        }
        
        if (!@file_exists(stripslashes($paths['mysqldump']))) {
            return false;
        }
        if (!@file_exists(stripslashes($paths['mysql']))) {
            return false;
        }
        
        return $paths;
    }
    
    //Check if exec, system, shell_exec functions exist
    function check_sys()
    {
        if (function_exists('exec'))
            return 'exec';
        
        if (function_exists('system'))
            return 'system';
        
        if (function_exists('passhtru'))
            return 'passthru';
        
        return false;
        
    }
    
    function mmb_exec($command,$string = false)
    {
    	if($command == '')
    	return false;
    	
  		if (function_exists('exec'))
  		{		
  				$log = @exec($command,$output,$return);
  			
  				if($string) return $log;
  				return $return ? false : true;
  		}	
  		if(function_exists('system'))
      {
        	$log = @system($command,$return);
        	
  				if($string) return $log;
  				return $return ? false : true;
      }
      elseif (function_exists('passthru'))
  		{
  				$log = passthru($command,$return);
  				
  				return $return ? false : true;
  		}
      else
      {
        	return false;
      }
    }
    
    function email_backup($args)
    {
    	 $email = $args['email'];
    	 $what = $args['email'];
    	 $type = $args['type'];
    	 $worker_options = get_option('mmb-worker');
       $backup_file = $worker_options['backups'][$type]['path'];
   		 if(file_exists($backup_file) && $email)
   		 {
   		 	$attachments = array($backup_file);
   		 	$headers = 'From: ManageWP <wordpress@managewp.com>' . "\r\n";
   		 	$subject = "Backup - " . $type ." - " . date('Y-m-d');
   		 	ob_start();
   		 	wp_mail($email, $subject, '', $headers, $attachments);
   		 	ob_end_clean();
   		}
 
    }
    
    function check_backup_compat()
    {				
    				$reqs = array();
			    	if ( strpos($_SERVER['DOCUMENT_ROOT'], '/') === 0 ) {
						$reqs['Server OS']['status'] = 'Linux (or compatible)';
						$reqs['Server OS']['pass'] = true;
						} else {
						$reqs['Server OS']['status'] = 'Windows';
						$reqs['Server OS']['pass'] = false;
						$pass = false;
						}
						$reqs['PHP Version']['status'] = phpversion();
						if ( (float) phpversion() >= 5.1 ) {
						$reqs['PHP Version']['pass'] = true;
						} else {
						$reqs['PHP Version']['pass'] = false;
						$pass = false;
						}
						
						if(is_writable(WP_CONTENT_DIR))
						{
							$reqs['Backup Folder']['status'] = "writable";
							$reqs['Backup Folder']['pass'] = true;
						}
						else
						{
							$reqs['Backup Folder']['status'] = "not writable";
							$reqs['Backup Folder']['pass'] = false;
						}
						
						if($func = $this->check_sys())
						{
							$reqs['Execute Function']['status'] =$func;
							$reqs['Execute Function']['pass'] = true;
						}
						else
						{
							$reqs['Execute Function']['status'] = "not found";
							$reqs['Execute Function']['info'] = "(will try with PHP)";
							$reqs['Execute Function']['pass'] = false;
						}
						
						if($this->mmb_exec('which zip'))
						{
							$reqs['Zip']['status'] = "enabled";
							$reqs['Zip']['pass'] = true;
						}
						else
						{
							$reqs['Zip']['status'] = "not found";
							$reqs['Zip']['info'] = "(will try with PHP pclZip class)";
							$reqs['Zip']['pass'] = false;
						}
						
						if($this->mmb_exec('which unzip'))
						{
							$reqs['Unzip']['status'] = "enabled";
							$reqs['Unzip']['pass'] = true;
						}
						else
						{
							$reqs['Unzip']['status'] = "not found";
							$reqs['Unzip']['info'] = "(will try with PHP pclZip class)";
							$reqs['Unzip']['pass'] = false;
						}
						if(is_array($this->check_mysqldump()))
						{
							$reqs['MySQL Dump']['status'] = "enabled";
							$reqs['MySQL Dump']['pass'] = true;
						}
						else
						{
							$reqs['MySQL Dump']['status'] = "not found";
							$reqs['MySQL Dump']['info'] = "(will try PHP)";
							$reqs['MySQL Dump']['pass'] = false;
						}
						
						
						
    	return $reqs;
    }
    
}
?>