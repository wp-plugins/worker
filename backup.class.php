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
	var $site_name;
	var $statuses;
	
    function __construct()
    {
        parent::__construct();
		$this->site_name = str_replace("/","-",rtrim($this->remove_http(get_bloginfo('url')),"/"));
		$this->statuses = array(
			'db_dump' => 1,
			'db_zip' => 2,
			'files_zip' => 3,
			's3' => 4,
			'dropbox' => 5,
			'ftp' => 6,
			'email' => 7,
			'finished' => 100
	);
    }
    
    function get_backup_settings()
    {
        $backup_settings = get_option('mwp_backup_tasks');
        if (!empty($backup_settings))
            return $backup_settings;
        else
            return false;
    }
    
    function set_backup_task($params)
    {		
        //$params => [$task_name, $args, $error]
        if (!empty($params)) {
            extract($params);
            
            $before = $this->get_backup_settings();
            if (!$before || empty($before))
                $before = array();
            
            if (isset($args['remove'])) {
                unset($before[$task_name]);
                $return = array(
                    'removed' => true
                );
            } else {
            		if(is_array($params['account_info'])){ //only if sends from master first time(secure data)
                 $args['account_info']            = $account_info;
               	}
               	
                $before[$task_name]['task_args'] = $args;
                if (strlen($args['schedule']))
                    $before[$task_name]['task_args']['next'] = $this->schedule_next($args['type'], $args['schedule']);
                
                $return = $before[$task_name];
            }
            
            //Update with error
            if ($error) {
            	if(is_array($error)){
                $before[$task_name]['task_results'][count($before[$task_name]['task_results'])-1]['error'] = $error['error'];
              } else {
              	$before[$task_name]['task_results'][count($before[$task_name]['task_results'])]['error'] = $error;
              }
            }
            
            if($time){ //set next result time before backup
            	if(is_array($before[$task_name]['task_results'])){
            		$before[$task_name]['task_results'] = array_values($before[$task_name]['task_results']);
            	} 
            	$before[$task_name]['task_results'][count($before[$task_name]['task_results'])]['time'] = $time;
            }
            
            update_option('mwp_backup_tasks', $before);
            
            if ($task_name == 'Backup Now') {
                $result          = $this->backup($args, $task_name);
                $backup_settings = $this->get_backup_settings();
                if (is_array($result) && array_key_exists('error', $result)) {
                    $return = $result;
                } else {
                    $return = $backup_settings[$task_name];
                }
            }
            return $return;
        }
        return false;
    }
    
    //Cron check
    function check_backup_tasks()
    {		
        $settings = $this->get_backup_settings();
        if (is_array($settings) && !empty($settings)) {
            foreach ($settings as $task_name => $setting) {
                //if ($setting['task_args']['next'] && $setting['task_args']['next'] < time()) {
                if ($setting['task_args']['next'] && $_GET['force_backup']) {
                	
                	if($setting['task_args']['url'] && $setting['task_args']['task_id'] && $setting['task_args']['site_key']){
                		//Check orphan task
                		$check_data = array(
                			'task_name' => $task_name,
                			'task_id' => $setting['task_args']['task_id'],
                			'url' => $setting['task_args']['url'],
                			'site_key' => $setting['task_args']['site_key'],
                		);
                		
                		$this->validate_task($check_data);
                	}
                	
                   //Update task with next schedule
                    $this->set_backup_task(array(
                        'task_name' => $task_name,
                        'args' => $settings[$task_name]['task_args'],
                        'time' => time()
                    ));
                    
                    $result = $this->backup($setting['task_args'], $task_name);
                    $error = '';
                    if (is_array($result) && array_key_exists('error', $result)) {
                        $error = $result;
                        $this->set_backup_task(array(
                        'task_name' => $task_name,
                        'args' => $settings[$task_name]['task_args'],
                        'error' => $error 
                    		)); 
                    } else {
                        $error = '';
                    }
                    break; //Only one backup per cron
                }
            }
        }
        
    }
    
    
    
    /*
     * If Task Name not set then it's manual backup
     * Backup args:
     * type -> db, full
     * what -> daily, weekly, monthly
     * account_info -> ftp, amazons3, dropbox
     * exclude-> array of paths to exclude from backup
     */
    
    function backup($args, $task_name = false)
    {   
    	
        if (!$args || empty($args))
            return false;
        
        extract($args); //extract settings

        //try increase memory limit	
        @ini_set('memory_limit', '1000M');
        @set_time_limit(600); //ten minutes
        
        //Remove old backup(s)
        $this->remove_old_backups($task_name);
			 	        
        $new_file_path = WP_CONTENT_DIR . "/managewp/backups";
        
        if (!file_exists($new_file_path)) {
            if (!mkdir($new_file_path, 0755, true))
                return array(
                    'error' => 'Permission denied, make sure you have write permission to wp-content folder.'
                );
        }
        
        @file_put_contents($new_file_path . '/index.php', ''); //safe
        
        //Delete possible breaked previous backups - don't need it anymore (works only for previous wrokers)
        foreach (glob($new_file_path . "/*.zip") as $filename) {
            $short = basename($filename);
            preg_match('/^wp\-content(.*)/Ui', $short, $matches);
            if ($matches)
                @unlink($filename);
        }
        
        //Prepare .zip file name
        $site_name = $this->remove_http(get_bloginfo('url'));
        $site_name = str_replace(array(
            "_",
            "/"
        ), array(
            "",
            "-"
        ), $site_name);
        
        $hash        = md5(time());
        $label       = $type ? $type : 'manual';
        $backup_file = $new_file_path . '/' . $site_name . '_' . $label . '_' . $what . '_' . date('Y-m-d') . '_' . $hash . '.zip';
        $backup_url  = WP_CONTENT_URL . '/managewp/backups/' . $site_name . '_' . $label . '_' . $what . '_' . date('Y-m-d') . '_' . $hash . '.zip';
        
        //What to backup - db or full
        if (trim($what) == 'db') {
            //take database backup
            $this->update_status($task_name,$this->statuses['db_dump']);
            $db_result = $this->backup_db();
            if ($db_result == false) {
                return array(
                    'error' => 'Failed to backup database.'
                );
            } else if (is_array($db_result) && isset($db_result['error'])) {
                return array(
                    'error' => $db_result['error']
                );
                
            } else {
            	
            	$this->update_status($task_name,$this->statuses['db_dump'],true);
            	
            	$this->update_status($task_name,$this->statuses['db_zip']);
       
              if ($this->mmb_exec('which zip')) {
                    chdir(WP_CONTENT_DIR);
                    $command = "zip -r $backup_file 'mwp_db'";
                    
                    ob_start();
                    $result = $this->mmb_exec($command);
                    ob_get_clean();
                } else {
                    require_once ABSPATH . '/wp-admin/includes/class-pclzip.php';
                    $archive = new PclZip($backup_file);
                    $result  = $archive->add($db_result, PCLZIP_OPT_REMOVE_PATH, WP_CONTENT_DIR);
                }
                
                @unlink($db_result);
                @rmdir(WP_CONTENT_DIR.'/mwp_db');
                if (!$result)
                    return array(
                        'error' => 'Failed to zip database.'
                    );
               $this->update_status($task_name,$this->statuses['db_zip'],true);
            }
        } elseif (trim($what) == 'full') {
            $content_backup = $this->backup_full($task_name,$backup_file, $exclude);
            if (is_array($content_backup) && array_key_exists('error', $content_backup)) {
                return array(
                    'error' => $content_backup['error']
                );
            }
        }
        
        //Update backup info
        if ($task_name) {
            //backup task (scheduled)
            $backup_settings = $this->get_backup_settings();
            $paths           = array();
            $size            = ceil(filesize($backup_file) / 1024);
            
            if ($size > 1000) {
                $paths['size'] = ceil($size / 1024)."Mb";
            } else {
                $paths['size'] = $size . 'kb';
            }
            
            if ($task_name != 'Backup Now') {
                if (!$backup_settings[$task_name]['task_args']['del_host_file']) {
                    $paths['server'] = array(
                        'file_path' => $backup_file,
                        'file_url' => $backup_url
                    );
                }
            } else {
                $paths['server'] = array(
                    'file_path' => $backup_file,
                    'file_url' => $backup_url
                );
            }
            
            if (isset($backup_settings[$task_name]['task_args']['account_info']['mwp_ftp'])) {
                $paths['ftp'] = basename($backup_url);
            }
            
            if (isset($backup_settings[$task_name]['task_args']['account_info']['mwp_amazon_s3'])) {
                $paths['amazons3'] = basename($backup_url);
            }
            
            if (isset($backup_settings[$task_name]['task_args']['account_info']['mwp_dropbox'])) {
                $paths['dropbox'] = basename($backup_url);
            }
            
            if ($backup_settings[$task_name]['task_args']['email_backup']) {
                //$paths['email'] = $backup_settings[$task_name]['task_args']['email_backup'];
                $paths['email'] = basename($backup_url);
            }
            
            $temp = $backup_settings[$task_name]['task_results'];
            $temp = array_values($temp);
            $paths['time'] = time();
             
            if($task_name != 'Backup Now'){
            	$paths['status'] = $temp[count($temp)-1]['status'];
          		$temp[count($temp)-1] = $paths;
          		
          	} else {
          		$temp[count($temp)] = $paths;
          	}
            $backup_settings[$task_name]['task_results'] = $temp;
            update_option('mwp_backup_tasks', $backup_settings);
        }
        
        
        //Additional: Email, ftp, amazon_s3, dropbox...
        if (isset($optimize_tables) && !empty($optimize_tables)) {
            $this->optimize_tables();
        }
        
        if ($task_name != 'Backup Now') {
            if (isset($account_info['mwp_ftp']) && !empty($account_info['mwp_ftp'])) {
            	$this->update_status($task_name,$this->statuses['ftp']);
                $account_info['mwp_ftp']['backup_file'] = $backup_file;
                $ftp_result = $this->ftp_backup($account_info['mwp_ftp']);
          			
          			if($ftp_result !== true && $del_host_file){
                	@unlink($backup_file);
                }
                
                if(is_array($ftp_result) && isset($ftp_result['error'])){
                	return $ftp_result;
                }
                $this->update_status($task_name,$this->statuses['ftp'],true);
            }
            
            if (isset($account_info['mwp_amazon_s3']) && !empty($account_info['mwp_amazon_s3'])) {
            	$this->update_status($task_name,$this->statuses['s3']);
                $account_info['mwp_amazon_s3']['backup_file'] = $backup_file;
                $amazons3_result = $this->amazons3_backup($account_info['mwp_amazon_s3']);
                if($amazons3_result !== true && $del_host_file){
                	@unlink($backup_file);
                }
                if(is_array($amazons3_result) && isset($amazons3_result['error'])){
                	return $amazons3_result;
                }
                $this->update_status($task_name,$this->statuses['s3'],true);
            }
            
            if (isset($account_info['mwp_dropbox']) && !empty($account_info['mwp_dropbox'])) {
            	$this->update_status($task_name,$this->statuses['dropbox']);
                $account_info['mwp_dropbox']['backup_file'] = $backup_file;
                 $dropbox_result = $this->dropbox_backup($account_info['mwp_dropbox']);
                 if($dropbox_result !== true && $del_host_file){
                	@unlink($backup_file);
                 }
                 
                 if(is_array($dropbox_result) && isset($dropbox_result['error'])){
                	return $dropbox_result;
                }
              
              $this->update_status($task_name,$this->statuses['dropbox'],true);
            }
            
            if (isset($email_backup) && is_email($email_backup)) {
            	$this->update_status($task_name,$this->statuses['email']);
                $mail_args = array(
                    'email' => $email_backup,
                    'task_name' => $task_name,
                    'file_path' => $backup_file
                );
                $this->email_backup($mail_args);
                $this->update_status($task_name,$this->statuses['email'],true);
            }
            
            if ($del_host_file) {
                @unlink($backup_file);
            }
            
        } //end additional
        
        //$this->update_status($task_name,$this->statuses['finished'],true);
        return $backup_url; //Return url to backup file
    }
    
    function backup_full($task_name,$backup_file, $exclude = array())
    {		
    		$this->update_status($task_name,$this->statuses['db_dump']);
        $db_result = $this->backup_db();
        
        if ($db_result == false) {
            return array(
                'error' => 'Failed to backup database.'
            );
        } else if (is_array($db_result) && isset($db_result['error'])) {
            return array(
                'error' => $db_result['error']
            );
        }
        
        $this->update_status($task_name,$this->statuses['db_dump'],true);
        
        $remove     = array(
            "wp-content/managewp/backups",
            "wp-content/".md5('mmb-worker')."/mwp_backups"
        );
        
        if ($this->mmb_exec('which zip')) {
            $this->update_status($task_name,$this->statuses['db_zip']);
            //Add database file
            chdir(WP_CONTENT_DIR);
            $command = "zip -r $backup_file 'mwp_db'";
            ob_start();
            $result = $this->mmb_exec($command);
            ob_get_clean();
            @unlink($db_result);
            @rmdir(WP_CONTENT_DIR.'/mwp_db');
            
            if (!$result) {
                return array(
                    'error' => 'Failed to zip database.'
                );
            }
            
            $this->update_status($task_name,$this->statuses['db_zip'],true);
            
            chdir(ABSPATH);
            //exclude paths
            $exclude_data = "-x";
            if (!empty($exclude)) {
                foreach ($exclude as $data) {
                    if ($data)
                        $exclude_data .= " '$data/*'";
                }
            }
            
            foreach ($remove as $data) {
                $exclude_data .= " '$data/*'";
            }
            
            $this->update_status($task_name,$this->statuses['files_zip']);
            $command = "zip -r $backup_file './' $exclude_data";
            ob_start();
            $result = $this->mmb_exec($command);
            ob_get_clean();
            
            if ($result) {
            	$this->update_status($task_name,$this->statuses['files_zip'],true);
                return true;
            } else {
                return array(
                    'error' => 'Failed to backup files.'
                );
            }
            
        } else { //php zip
            define('PCLZIP_TEMPORARY_DIR', WP_CONTENT_DIR.'/managewp/backups/');
            require_once ABSPATH . '/wp-admin/includes/class-pclzip.php';
            $archive      = new PclZip($backup_file);
            $this->update_status($task_name,$this->statuses['db_zip']);
            $result_db       = $archive->add($db_result, PCLZIP_OPT_REMOVE_PATH, WP_CONTENT_DIR);
            
            @unlink($db_result);
            @rmdir(WP_CONTENT_DIR.'/mwp_db');
            
            if(!$result_db){
            	return array(
                        'error' => 'Failed to backup site. Try to enable Zip on your server.'
                    );
            }
            $this->update_status($task_name,$this->statuses['db_zip'],true);
            $this->update_status($task_name,$this->statuses['files_zip']);
            $result       = $archive->add(ABSPATH, PCLZIP_OPT_REMOVE_PATH, ABSPATH);
            $exclude_data = array();
            if (!empty($exclude) && $result) {
                $exclude_data = array();
                foreach ($exclude as $data) {
                    if ($data)
                        $exclude_data[] = $data . '/';
                }
            }
            foreach ($remove as $rem) {
                $exclude_data[] = $rem . '/';
            }
            
            $result_excl = $archive->delete(PCLZIP_OPT_BY_NAME, $exclude_data);
            
            
            
            if ($result && $result_excl) {
            		$this->update_status($task_name,$this->statuses['files_zip'],true);
                return true;
            } else {
                if ($archive->error_code == '-10') {
                    return array(
                        'error' => 'Failed to backup site. Try increasing memory limit and/or free space on your server.'
                    );
                } else {
                    return array(
                        'error' => 'Failed to backup site. Try to enable Zip on your server.'
                    );
                }
            }
        }
    }
    
    
    function backup_db()
    {
        $db_folder = WP_CONTENT_DIR.'/mwp_db/';
        if (!file_exists($db_folder)) {
            if (!mkdir($db_folder, 0755, true))
                return array(
                    'error' => 'Error creating database backup folder. Make sure you have write permissions to your site root folder.'
                );
        }
        
        $file      = $db_folder . DB_NAME . '.sql';
        $mysqldump = $this->check_mysqldump();
        if (is_array($mysqldump)) {
            $result = $this->backup_db_dump($file, $mysqldump);
            
        } else {
            $result = $this->backup_db_php($file);
        }
        return $result;
    }
    
    function backup_db_dump($file, $mysqldump)
    {
        global $wpdb;
        $brace   = (substr(PHP_OS, 0, 3) == 'WIN') ? '"' : '';
        $command = $brace . $mysqldump['mysqldump'] . $brace . ' --host="' . DB_HOST . '" --user="' . DB_USER . '" --password="' . DB_PASSWORD . '" --add-drop-table --skip-lock-tables "' . DB_NAME . '" > ' . $brace . $file . $brace;
        
        ob_start();
        $result = $this->mmb_exec($command);
        ob_get_clean();
        if (!$result) {
            $result = $this->backup_db_php($file);
            return $result;
        }
        
        if (filesize($file) == 0 || !is_file($file) || !$result) {
            @unlink($file);
            return false;
        } else {
            return $file;
        }
    }
    
    function backup_db_php($file)
    {
        global $wpdb;
        $tables = $wpdb->get_results('SHOW TABLES', ARRAY_N);
        foreach ($tables as $table) {
            //drop existing table
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
                            $value = preg_replace("/\n/Ui", "\\n", $value);
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
        
        return $file;
        
    }
    
    function restore($args)
    {
        global $wpdb;
        if (empty($args)) {
            return false;
        }
        
        extract($args);
        @ini_set('memory_limit', '300M');
        @set_time_limit(300);
        
        $unlink_file = true; //Delete file after restore
        
        //Detect source
        if ($backup_url) {
            //This is for clone (overwrite)
            include_once ABSPATH . 'wp-admin/includes/file.php';
            $backup_file = download_url($backup_url);
            if (is_wp_error($backup_file)) {
                return array(
                    'error' => $backup_file->get_error_message()
                );
            }
            $what = 'full';
        } else {
            $tasks = $this->get_backup_settings();
            $task  = $tasks[$task_name];
            if (isset($task['task_results'][$result_id]['server'])) {
                $backup_file = $task['task_results'][$result_id]['server']['file_path'];
                $unlink_file = false; //Don't delete file if stored on server
            } elseif (isset($task['task_results'][$result_id]['ftp'])) {
                $ftp_file            = $task['task_results'][$result_id]['ftp'];
                $args                = $task['task_args']['account_info']['mwp_ftp'];
                $args['backup_file'] = $ftp_file;
                $backup_file         = $this->get_ftp_backup($args);
                if ($backup_file == false) {
                    return array(
                        'error' => 'Failed to download file from FTP'
                    );
                }
            } elseif (isset($task['task_results'][$result_id]['amazons3'])) {
                $amazons3_file       = $task['task_results'][$result_id]['amazons3'];
                $args                = $task['task_args']['account_info']['mwp_amazon_s3'];
                $args['backup_file'] = $ftp_file;
                $backup_file         = $this->get_amazons3_backup($args);
                if ($backup_file == false) {
                    return array(
                        'error' => 'Failed to download file from Amazon S3'
                    );
                }
            }
            
            $what = $tasks[$task_name]['task_args']['what'];
        }
        
        if ($backup_file && file_exists($backup_file)) {
            if ($overwrite) {
                //Keep old db credentials before overwrite
                if (!copy(ABSPATH . 'wp-config.php', ABSPATH . 'mwp-temp-wp-config.php')) {
                		@unlink($backup_file);
                    return array(
                        'error' => 'Error creating wp-config. Please check your write permissions.'
                    );
                }
                
                $db_host     = DB_HOST;
                $db_user     = DB_USER;
                $db_password = DB_PASSWORD;
                $home        = rtrim(get_option('home'),"/");
                $site_url    = get_option('site_url');
            }
            
            if ($this->mmb_exec('which unzip')) {
                chdir(ABSPATH);
                $command = "unzip -o $backup_file";
                ob_start();
                $result = $this->mmb_exec($command);
                ob_get_clean();
                
            } else {
                require_once ABSPATH . '/wp-admin/includes/class-pclzip.php';
                $archive = new PclZip($backup_file);
                $result  = $archive->extract(PCLZIP_OPT_PATH, ABSPATH);
            }
            
            if ($unlink_file) {
                @unlink($backup_file);
            }
            
            if (!$result) {
                return array(
                    'error' => 'Error extracting backup file.'
                );
            }
            
            $db_result = $this->restore_db();
            
            if (!$db_result) {
                return array(
                    'error' => 'Error restoring database.'
                );
            }

        } else {
            return array(
                'error' => 'Error restoring. Cannot find backup file.'
            );
        }
        
        //Replace options and content urls
        if ($overwrite) {
        	
            //Get New Table prefix
            $new_table_prefix = trim($this->get_table_prefix());
            
            //Retrieve old wp_config
            @unlink(ABSPATH . 'wp-config.php');
             
            //Replace table prefix
            $lines = file(ABSPATH . 'mwp-temp-wp-config.php');
            foreach ($lines as $line) {
                if (strstr($line, '$table_prefix')) {
                    $line = '$table_prefix = "' . $new_table_prefix . '";' . PHP_EOL;
                }
                file_put_contents(ABSPATH . 'wp-config.php', $line, FILE_APPEND);
            }
            
            @unlink(ABSPATH . 'mwp-temp-wp-config.php');
            
            //Replace options
            $query = "SELECT option_value FROM " . $new_table_prefix . "options WHERE option_name = 'home'";
            $old   = $wpdb->get_var($wpdb->prepare($query));
            $old = rtrim($old,"/");
            $query = "UPDATE " . $new_table_prefix . "options SET option_value = '$home' WHERE option_name = 'home'";
            $wpdb->query($wpdb->prepare($query));
            $query = "UPDATE " . $new_table_prefix . "options  SET option_value = '$home' WHERE option_name = 'siteurl'";
            $wpdb->query($wpdb->prepare($query));
            //Replace content urls
            $query = "UPDATE " . $new_table_prefix . "posts SET post_content = REPLACE (post_content, '$old','$home') WHERE post_content REGEXP 'src=\"(.*)$old(.*)\"' OR post_content REGEXP 'href=\"(.*)$old(.*)\"'";
            $wpdb->query($wpdb->prepare($query));
        }
        
        return true;
    }
    
    function restore_db()
    {
        global $wpdb;
        $mysqldump = $this->check_mysqldump();
        $file_path = ABSPATH . 'mwp_db';
        $file_name = glob($file_path . '/*.sql');
        $file_name = $file_name[0];
        
        if (is_array($mysqldump)) {
            $brace   = (substr(PHP_OS, 0, 3) == 'WIN') ? '"' : '';
            $command = $brace . $mysqldump['mysql'] . $brace . ' --host="' . DB_HOST . '" --user="' . DB_USER . '" --password="' . DB_PASSWORD . '" ' . DB_NAME . ' < ' . $brace . $file_name . $brace;
            
            ob_start();
            $result = $this->mmb_exec($command);
            ob_get_clean();
            if (!$result) {
                //try php
                $this->restore_db_php($file_name);
            }
            
        } else {
            $this->restore_db_php($file_name);
        }
        
        @unlink($file_name);
        return true;
    }
    
    function restore_db_php($file_name)
    {
        $current_query = '';
        // Read in entire file
        $lines         = file($file_name);
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
        
        @unlink($file_name);
        return true;
    }
    
    function get_table_prefix()
    {
        $lines = file(ABSPATH . 'wp-config.php');
        foreach ($lines as $line) {
            if (strstr($line, '$table_prefix')) {
                $pattern = "/(\'|\")[^(\'|\")]*/";
                preg_match($pattern, $line, $matches);
                $prefix = substr($matches[0], 1);
                return $prefix;
                break;
            }
        }
        return 'wp_'; //default
    }
    
    function optimize_tables()
    {
        global $wpdb;
        $query  = 'SHOW TABLE STATUS FROM ' . DB_NAME;
        $tables = $wpdb->get_results($wpdb->prepare($query), ARRAY_A);
        foreach ($tables as $table) {
            if (in_array($table['Engine'], array(
                'MyISAM',
                'ISAM',
                'HEAP',
                'MEMORY',
                'ARCHIVE'
            )))
                $table_string .= $table['Name'] . ",";
            elseif ($table['Engine'] == 'InnoDB') {
                $optimize = $wpdb->query("ALTER TABLE {$table['Name']} ENGINE=InnoDB");
            }
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
                $paths['mysql']     = $this->mmb_exec('which mysql', true);
                $paths['mysqldump'] = $this->mmb_exec('which mysqldump', true);
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
    
    //Check if exec, system, passthru functions exist
    function check_sys()
    {
        if ($this->mmb_function_exists('exec'))
            return 'exec';
        
        if ($this->mmb_function_exists('system'))
            return 'system';
        
        if ($this->mmb_function_exists('passhtru'))
            return 'passthru';
        
        return false;
        
    }
    
    function mmb_exec($command, $string = false)
    {
        if ($command == '')
            return false;
        
        if ($this->mmb_function_exists('exec')) {
            $log = @exec($command, $output, $return);
            
            if ($string)
                return $log;
            return $return ? false : true;
        } elseif ($this->mmb_function_exists('system')) {
            $log = @system($command, $return);
            
            if ($string)
                return $log;
            return $return ? false : true;
        } elseif ($this->mmb_function_exists('passthru')) {
            $log = passthru($command, $return);
            
            return $return ? false : true;
        } else {
            return false;
        }
    }
    
    function check_backup_compat()
    {
        $reqs = array();
        if (strpos($_SERVER['DOCUMENT_ROOT'], '/') === 0) {
            $reqs['Server OS']['status'] = 'Linux (or compatible)';
            $reqs['Server OS']['pass']   = true;
        } else {
            $reqs['Server OS']['status'] = 'Windows';
            $reqs['Server OS']['pass']   = false;
            $pass                        = false;
        }
        $reqs['PHP Version']['status'] = phpversion();
        if ((float) phpversion() >= 5.1) {
            $reqs['PHP Version']['pass'] = true;
        } else {
            $reqs['PHP Version']['pass'] = false;
            $pass                        = false;
        }
        
        
        if (is_writable(WP_CONTENT_DIR)) {
            $reqs['Backup Folder']['status'] = "writable";
            $reqs['Backup Folder']['pass']   = true;
        } else {
            $reqs['Backup Folder']['status'] = "not writable";
            $reqs['Backup Folder']['pass']   = false;
        }
        
        
        $file_path  = WP_CONTENT_DIR . "managewp/backups";
        $reqs['Backup Folder']['status'] .= ' (' . $file_path . ')';
        
        if ($func = $this->check_sys()) {
            $reqs['Execute Function']['status'] = $func;
            $reqs['Execute Function']['pass']   = true;
        } else {
            $reqs['Execute Function']['status'] = "not found";
            $reqs['Execute Function']['info']   = "(will try with PHP)";
            $reqs['Execute Function']['pass']   = false;
        }
        
        if ($this->mmb_exec('which zip')) {
            $reqs['Zip']['status'] = "enabled";
            $reqs['Zip']['pass']   = true;
        } else {
            $reqs['Zip']['status'] = "not found";
            $reqs['Zip']['info']   = "(will try with PHP pclZip class)";
            $reqs['Zip']['pass']   = false;
        }
        
        if ($this->mmb_exec('which unzip')) {
            $reqs['Unzip']['status'] = "enabled";
            $reqs['Unzip']['pass']   = true;
        } else {
            $reqs['Unzip']['status'] = "not found";
            $reqs['Unzip']['info']   = "(will try with PHP pclZip class)";
            $reqs['Unzip']['pass']   = false;
        }
        if (is_array($this->check_mysqldump())) {
            $reqs['MySQL Dump']['status'] = "enabled";
            $reqs['MySQL Dump']['pass']   = true;
        } else {
            $reqs['MySQL Dump']['status'] = "not found";
            $reqs['MySQL Dump']['info']   = "(will try PHP)";
            $reqs['MySQL Dump']['pass']   = false;
        }
        
        return $reqs;
    }
    
    function email_backup($args)
    {
        $email       = $args['email'];
        $backup_file = $args['file_path'];
        $task_name   = isset($args['task_name']) ? $args['task_name'] . ' on ' : '';
        if (file_exists($backup_file) && $email) {
            $attachments = array(
                $backup_file
            );
            $headers     = 'From: ManageWP <no-reply@managewp.com>' . "\r\n";
            $subject     = "ManageWP - " . $task_name . " - ".$this->site_name;
            ob_start();
            wp_mail($email, $subject, $subject, $headers, $attachments);
            ob_end_clean();
            
        }
        
        return true;
        
    }
    
    function ftp_backup($args)
    {
        extract($args);
        //Args: $ftp_username, $ftp_password, $ftp_hostname, $backup_file, $ftp_remote_folder, $ftp_site_folder
        if($ftp_ssl ){
        	if (function_exists('ftp_ssl_connect')) {
            $conn_id = ftp_ssl_connect($ftp_hostname);
        	} else {
        		return array(
                'error' => 'Your server doesn\'t support SFTP',
                'partial' => 1
            );
        	}
        } else { 
        if (function_exists('ftp_connect')) {
            $conn_id = ftp_connect($ftp_hostname);
            if ($conn_id === false) {
                return array(
                    'error' => 'Failed to connect to ' . $ftp_hostname,
                    'partial' => 1
                );
            }
        } else {
            return array(
                'error' => 'Your server doesn\'t support FTP',
                'partial' => 1
            );
        }
      }
        $login = @ftp_login($conn_id, $ftp_username, $ftp_password);
        if ($login === false) {
            return array(
                'error' => 'FTP login failed for ' . $ftp_username . ', ' . $ftp_password,
                'partial' => 1
            );
        }        
        
        @ftp_mkdir($conn_id, $ftp_remote_folder);
        if($ftp_site_folder){
					$ftp_remote_folder .= '/'.$this->site_name;
				}
				@ftp_mkdir($conn_id, $ftp_remote_folder);
     		
        $upload = @ftp_put($conn_id, $ftp_remote_folder . '/' . basename($backup_file), $backup_file, FTP_BINARY);
        
        if($upload === false){ //Try ascii
        	$upload = @ftp_put($conn_id, $ftp_remote_folder . '/' . basename($backup_file), $backup_file, FTP_ASCII);
        }
        ftp_close($conn_id);
        
        if ($upload === false) {
            return array(
                'error' => 'Failed to upload file to FTP. Please check your specified path.',
                'partial' => 1
            );
        }
        
        return true;
    }
    
    function remove_ftp_backup($args)
    {
        extract($args);
        //Args: $ftp_username, $ftp_password, $ftp_hostname, $backup_file, $ftp_remote_folder
        if ($ftp_ssl && function_exists('ftp_ssl_connect')) {
            $conn_id = ftp_ssl_connect($ftp_hostname);
        } else if (function_exists('ftp_connect')) {
            $conn_id = ftp_connect($ftp_hostname);
        }
        
        if($conn_id){
        	$login = @ftp_login($conn_id, $ftp_username, $ftp_password);
         if($ftp_site_folder)
					$ftp_remote_folder .= '/'.$this->site_name;
        
        	$delete = ftp_delete($conn_id, $ftp_remote_folder . '/' . $backup_file);
        
        	ftp_close($conn_id);
      	}
        
    }
    
    function get_ftp_backup($args)
    {
        extract($args);
        //Args: $ftp_username, $ftp_password, $ftp_hostname, $backup_file, $ftp_remote_folder
        if ($ftp_ssl && function_exists('ftp_ssl_connect')) {
            $conn_id = ftp_ssl_connect($ftp_hostname);
            
        } else if (function_exists('ftp_connect')) {
            $conn_id = ftp_connect($ftp_hostname);
            if ($conn_id === false) {
                return false;
            }
        } else {
        }
        $login = @ftp_login($conn_id, $ftp_username, $ftp_password);
        if ($login === false) {
            return false;
        } else {
        }
        
         if($ftp_site_folder)
					$ftp_remote_folder .= '/'.$this->site_name;
					
        $temp = ABSPATH . 'mwp_temp_backup.zip';
        $get  = ftp_get($conn_id, $temp, $ftp_remote_folder . '/' . $backup_file, FTP_BINARY);
        if ($get === false) {
            return false;
        } else {
        }
        ftp_close($conn_id);
        
        return $temp;
    }
    
    function dropbox_backup($args)
    { 
        require_once('lib/dropbox.php');
        extract($args);
        
     //$email, $password, $backup_file, $destination, $dropbox_site_folder
		
		$size            = ceil(filesize($backup_file) / 1024);
		if($size > 300000){
			return array(
                'error' => 'Cannot upload file to Dropbox. Dropbox has upload limit of 300Mb per file.',
                'partial' => 1
            );
		}
		
		if($dropbox_site_folder == true)
			$dropbox_destination .= '/'.$this->site_name;
		    
		try {
            $uploader = new DropboxUploader($dropbox_username, $dropbox_password);
            $uploader->upload($backup_file, $dropbox_destination);
        }
        catch (Exception $e) {
            return array(
                'error' => $e->getMessage(),
                'partial' => 1
            );
        }
        
        return true;
        
    }
    
    function amazons3_backup($args)
    {
        require_once('lib/s3.php');
        extract($args);
        
		if($as3_site_folder == true)
			$as3_directory .= '/'.$this->site_name;
		
        
        $s3         = new S3($as3_access_key, str_replace(' ', '+', $as3_secure_key));
        
        $s3->putBucket($as3_bucket, S3::ACL_PUBLIC_READ);
        
        if ($s3->putObjectFile($backup_file, $as3_bucket, $as3_directory . '/' . basename($backup_file), S3::ACL_PRIVATE)) {
            return true;
        } else {
            return array(
                'error' => 'Failed to upload to Amazon S3. Please check your details and set upload/delete permissions on your bucket.',
                'partial' => 1
            );
        }
        
    }
    
    function remove_amazons3_backup($args)
    {
        require_once('lib/s3.php');
        extract($args);
        if($as3_site_folder == true)
					$as3_directory .= '/'.$this->site_name;
					
        $s3 = new S3($as3_access_key, str_replace(' ', '+', $as3_secure_key));
        $s3->deleteObject($as3_bucket, $as3_directory . '/' . $backup_file);
    }
    
    function get_amazons3_backup($args)
    {
        require_once('lib/s3.php');
        extract($args);
        $s3 = new S3($as3_access_key, str_replace(' ', '+', $as3_secure_key));
         if($as3_site_folder == true)
					$as3_directory .= '/'.$this->site_name;

        $s3->getObject($as3_bucket, $as3_directory . '/' . $backup_file, $temp);
        $temp = ABSPATH . 'mwp_temp_backup.zip';
        return $temp;
    }
    
    function schedule_next($type, $schedule)
    {		
        $schedule = explode("|", $schedule);
        if (empty($schedule))
            return false;
        switch ($type) {
            
            case 'daily':
                
                if ($schedule[1]) {
                    $delay_time = $schedule[1] * 60;
                }
                
                $current_hour  = date("H");
                $schedule_hour = $schedule[0];
                if ($current_hour >= $schedule_hour)
                    $time = mktime($schedule_hour, 0, 0, date("m"), date("d") + 1, date("Y"));
                else
                    $time = mktime($schedule_hour, 0, 0, date("m"), date("d"), date("Y"));
                break;
            
            
            case 'weekly':
                if ($schedule[2]) {
                    $delay_time = $schedule[2] * 60;
                }
                $current_weekday  = date('w');
                $schedule_weekday = $schedule[1];
                $current_hour     = date("H");
                $schedule_hour    = $schedule[0];
                
                if ($current_weekday > $schedule_weekday)
                    $weekday_offset = 7 - ($week_day - $task_schedule[1]);
                else
                    $weekday_offset = $schedule_weekday - $current_weekday;
                
                
                if (!$weekday_offset) { //today is scheduled weekday
                    if ($current_hour >= $schedule_hour)
                        $time = mktime($schedule_hour, 0, 0, date("m"), date("d") + 7, date("Y"));
                    else
                        $time = mktime($schedule_hour, 0, 0, date("m"), date("d"), date("Y"));
                } else {
                    $time = mktime($schedule_hour, 0, 0, date("m"), date("d") + $weekday_offset, date("Y"));
                }
                
                break;
            
            case 'monthly':
                if ($schedule[2]) {
                    $delay_time = $schedule[2] * 60;
                }
                $current_monthday  = date('j');
                $schedule_monthday = $schedule[1];
                $current_hour      = date("H");
                $schedule_hour     = $schedule[0];
                
                if ($current_monthday > $schedule_monthday) {
                    $time = mktime($schedule_hour, 0, 0, date("m") + 1, $schedule_monthday, date("Y"));
                } else if ($current_monthday < $schedule_monthday) {
                    $time = mktime($schedule_hour, 0, 0, date("m"), $schedule_monthday, date("Y"));
                } else if ($current_monthday == $schedule_monthday) {
                    if ($current_hour >= $schedule_hour)
                        $time = mktime($schedule_hour, 0, 0, date("m") + 1, $schedule_monthday, date("Y"));
                    else
                        $time = mktime($schedule_hour, 0, 0, date("m"), $schedule_monthday, date("Y"));
                    break;
                }
                
                break;
            default:
                break;
        }
        
        if ($delay_time) {
            $time += $delay_time;
        }
        
        
        return $time;
        
    }
    
    //Parse task arguments for info on master
    function get_backup_stats()
    {
        $stats = array();
        $tasks = get_option('mwp_backup_tasks');
        if (is_array($tasks) && !empty($tasks)) {
            foreach ($tasks as $task_name => $info) {
            		
            		if(is_array($info['task_results']) && !empty($info['task_results']))
            		{
            			foreach($info['task_results'] as $key => $result){
            				if(isset($result['server']) && !isset($result['error'])){
            					if(!file_exists($result['server']['file_path'])){
            						$info['task_results'][$key]['error'] = 'Backup created but manually removed from server.';
            					}
            				}
            			}
            		}
            		
                $stats[$task_name] = array_values($info['task_results']);
                
            }
        }
        return $stats;
    }
    
    function get_next_schedules(){
    	$stats = array();
        $tasks = get_option('mwp_backup_tasks');
        if (is_array($tasks) && !empty($tasks)) {
            foreach ($tasks as $task_name => $info) {
              $stats[$task_name] = $info['task_args']['next'];
            }
        }
        return $stats;
    }
    
    function remove_old_backups($task_name)
    {
    	
        $backups = $this->get_backup_settings();
        
        if($task_name == 'Backup Now'){
        	$num = 0;	
        } else {
        	$num = 1;
        }
        
        
        if ((count($backups[$task_name]['task_results']) - $num) >= $backups[$task_name]['task_args']['limit']) {
            //how many to remove ?
            $remove_num = (count($backups[$task_name]['task_results']) - $num - $backups[$task_name]['task_args']['limit']) + 1;
            for ($i = 0; $i < $remove_num; $i++) {
                    //Remove from the server
                    if (isset($backups[$task_name]['task_results'][$i]['server'])) {
                        @unlink($backups[$task_name]['task_results'][$i]['server']['file_path']);
                    }
                    
                    //Remove from ftp
                    if (isset($backups[$task_name]['task_results'][$i]['ftp'])) {
                        $ftp_file            = $backups[$task_name]['task_results'][$i]['ftp'];
                        $args                = $backups[$task_name]['task_args']['account_info']['mwp_ftp'];
                        $args['backup_file'] = $ftp_file;
                        $this->remove_ftp_backup($args);
                    }
                    
                    if (isset($backups[$task_name]['task_results'][$i]['amazons3'])) {
                        $amazons3_file       = $backups[$task_name]['task_results'][$i]['amazons3'];
                        $args                = $backups[$task_name]['task_args']['account_info']['mwp_amazon_s3'];
                        $args['backup_file'] = $amazons3_file;
                        $this->remove_amazons3_backup($args);
                    }
                    
                    if (isset($backups[$task_name]['task_results'][$i]['dropbox'])) {
                    	//To do: dropbox remove
                    }
                    
                //Remove database backup info
                unset($backups[$task_name]['task_results'][$i]);
                
            } //end foreach
            
            $backups[$task_name]['task_results'] = array_values($backups[$task_name]['task_results']);
            update_option('mwp_backup_tasks', $backups);
        }
    }
    
    /**
     * Delete specified backup
     * Args: $task_name, $result_id
     */
    
    function delete_backup($args)
    {
        if (empty($args))
            return false;
        extract($args);
        
        $tasks   = $this->get_backup_settings();
        $task    = $tasks[$task_name];
        $backups = $task['task_results'];
        $backup  = $backups[$result_id];
        
        if (isset($backup['server'])) {
            @unlink($backup['server']['file_path']);
        }
        
        //Remove from ftp
        if (isset($backup['ftp'])) {
            $ftp_file            = $backup['ftp'];
            $args                = $tasks[$task_name]['task_args']['account_info']['mwp_ftp'];
            $args['backup_file'] = $ftp_file;
            $this->remove_ftp_backup($args);
        }
        
        if (isset($backup['amazons3'])) {
            $amazons3_file       = $backup['amazons3'];
            $args                = $tasks[$task_name]['task_args']['account_info']['mwp_amazon_s3'];
            $args['backup_file'] = $amazons3_file;
            $this->remove_amazons3_backup($args);
        }
        
        if (isset($backup['dropbox'])) {
        }
        
        unset($backups[$result_id]);
        
        if (count($backups)) {
            $tasks[$task_name]['task_results'] = $backups;
        } else {
            unset($tasks[$task_name]['task_results']);
        }
        
        update_option('mwp_backup_tasks', $tasks);
        return true;
        
    }
    
    function cleanup()
    {
        $tasks = $this->get_backup_settings();
        $backup_folder = WP_CONTENT_DIR . '/' . md5('mmb-worker') . '/mwp_backups/';
        $backup_folder_new = WP_CONTENT_DIR . '/managewp/backups/';
        $files         = glob($backup_folder . "*");
        $new = glob($backup_folder_new . "*");
        
        //clean_old folder?
        if((basename($files[0]) == 'index.php' && count($files) == 1) || (empty($files))){
        	foreach($files as $file){
        		@unlink($file);
        	}
        		@rmdir(WP_CONTENT_DIR . '/' . md5('mmb-worker'). '/mwp_backups');
        		@rmdir(WP_CONTENT_DIR . '/' . md5('mmb-worker'));
        }	
        		
        
        foreach($new as $b){
        	$files[] = $b;
        }
        $deleted       = array();
        $clone_backup = get_option('mwp_manual_backup');
        if(isset($clone_backup['file_path'])){
        	$clone_backup = $clone_backup['file_path'];
        }
        
        if (is_array($files) && count($files)) {
            $results = array();
            if (!empty($tasks)) {
                foreach ((array) $tasks as $task) {
                    if (isset($task['task_results']) && count($task['task_results'])) {
                        foreach ($task['task_results'] as $backup) {
                            if (isset($backup['server'])) {
                                $results[] = $backup['server']['file_path'];
                            }
                        }
                    }
                }
            }
            
            $num_deleted = 0;
            foreach ($files as $file) {
                if (!in_array($file, $results) && basename($file) != 'index.php' && basename($file) != basename($clone_backup)) {
                    @unlink($file);
                    $deleted[] = basename($file);
                    $num_deleted++;
                }
            }
        }
        
        //Failed db files?
        $db_folder = WP_CONTENT_DIR . '/mwp_db/';
        $files     = glob($db_folder . "*.*");
        if (is_array($files) && count($files)) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
        
        return $deleted;
    }
    
    
    function remote_backup_now($args)
    {
        if (!empty($args))
            extract($args);
        $tasks = $this->get_backup_settings();
        $task  = $tasks['Backup Now'];
        
        if (!empty($task)) {
            extract($task['task_args']);
        }
        
        $results = $task['task_results'];
        
        if (is_array($results) && count($results)) {
            $backup_file = $results[count($results) - 1]['server']['file_path'];
        }
        
        if ($backup_file && file_exists($backup_file)) {
            if ($email) {
                $mail_args = array(
                    'email' => $email_backup,
                    'task_name' => 'Backup Now',
                    'file_path' => $backup_file
                );

                $return = $this->email_backup($mail_args);
                
                //delete from server?
                if ($return == true && $del_host_file) {
                    @unlink($backup_file);
                    
                    unset($tasks['Backup Now']['task_results'][count($results) - 1]['server']);
                    update_option('mwp_backup_tasks', $tasks);
                    
                }
            } else {
                //FTP, Amazon S3 or Dropbox
                if (isset($account_info['mwp_ftp']) && !empty($account_info['mwp_ftp'])) {
                    $account_info['mwp_ftp']['backup_file'] = $backup_file;
                    $return                                 = $this->ftp_backup($account_info['mwp_ftp']);
                }
                
                if (isset($account_info['mwp_amazon_s3']) && !empty($account_info['mwp_amazon_s3'])) {
                    $account_info['mwp_amazon_s3']['backup_file'] = $backup_file;
                    $return                                       = $this->amazons3_backup($account_info['mwp_amazon_s3']);
                }
                
                if (isset($account_info['mwp_dropbox']) && !empty($account_info['mwp_dropbox'])) {
                    $account_info['mwp_dropbox']['backup_file'] = $backup_file;
                    $return                                     = $this->dropbox_backup($account_info['mwp_dropbox']);
                }
                
                if ($return == true && $del_host_file && !$email_backup) {
                    @unlink($backup_file);
                    unset($tasks['Backup Now']['task_results'][count($results) - 1]['server']);
                    update_option('mwp_backup_tasks', $tasks);
                }
                
            }
            
        } else {
            $return = array(
                'error' => 'Backup file not found on your server. Please try again.'
            );
        }
        
        return $return;
        
    }
    
    function validate_task($args){
    	if( !class_exists( 'WP_Http' ) ){
    		include_once( ABSPATH . WPINC. '/class-http.php' );
    	}
				$params = array();
				$params['body'] = $args;
				$result= wp_remote_post($args['url'], $params);
			if(is_array($result) && $result['body'] == 'mwp_delete_task'){
				$tasks = $this->get_backup_settings();
				unset($tasks[$args['task_name']]);
				update_option('mwp_backup_tasks',$tasks);
				exit;
			}
    }
    
    function update_status($task_name, $status, $completed = false){
    	/* Statuses:
    	0 - Backup started
    	1 - DB dump
    	2 - DB ZIP
    	3 - Files ZIP
    	4 - Amazon S3
    	5 - Dropbox
    	6 - FTP
    	7 - Email
    	100 - Finished
    	*/
    	if($task_name != 'Backup Now'){
	    	$tasks = $this->get_backup_settings();
	    	$index = count($tasks[$task_name]['task_results']) - 1;
	    	if(!is_array($tasks[$task_name]['task_results'][$index]['status'])){
	    		$tasks[$task_name]['task_results'][$index]['status'] = array();
	    	}
	    	if(!$completed){
	    		$tasks[$task_name]['task_results'][$index]['status'][] = (int)$status * (-1);
	    	} else {
	    		$status_index = count($tasks[$task_name]['task_results'][$index]['status']) - 1;
	    		$tasks[$task_name]['task_results'][$index]['status'][$status_index] = abs($tasks[$task_name]['task_results'][$index]['status'][$status_index]);
	    	}
	    	
	    	update_option('mwp_backup_tasks',$tasks);
    	}
    }
     
}
 
?>