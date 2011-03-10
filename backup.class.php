<?php

class Mmb_Backup extends Mmb_Core {

    function __construct() {
        $upload_dir = wp_upload_dir();
        $sec_string = md5('mwp-worker');
        $file = "/$sec_string/backups";
        $file_path = $upload_dir['basedir'] . $file;
        file_put_contents($file_path . '/index.php', '');

        if(!file_exists($file_path)){
            mkdir($file_path, 0755, true);
        }
        parent::__construct();
    }

    function backup($args) {
    	
        $this->_escape($args);
        $username = $args[0];
        $password = $args[1];
        //type like manual, weekly, daily
        $type = $args[2];
        //what like full, only db, only wp-content
        $what = $args[3];
        if(trim($type) == '')
            $type = 'manual';
        
        if (!$user = $this->login($username, $password))
        {
            return $this->error;
        }

        if(!current_user_can('administrator'))
            return new IXR_Error(401, 'Sorry, you are not allowed to perform backup action on this site.');
        
        $upload_dir = wp_upload_dir();
        $sec_string = md5('mwp-worker');
        $file = "/$sec_string/backups";
        $file_path = $upload_dir['basedir'] . $file;

        if(!file_exists($file_path)){
            mkdir($file_path, 0755, true);
        }

        if(trim($what) == 'full' || trim($what) == 'content' ){
                //take wp-conetnt backup
                $content_backup = $this->backup_wpcontent($type);
                if(!$content_backup){
                    @unlink($content_backup['path']);
                    return FALSE;
                }
        }

        if(trim($what) == 'full' || trim($what) == 'db' ){
                //take batabase backup
                $db_backup = $this->backup_db($type);
                if(!$db_backup){
                		if(trim($what) == 'full')
                    @unlink($content_backup['path']);
                    
                    @unlink($db_backup['path']);
                    return false;
                }
        }
        require_once  ABSPATH.'/wp-admin/includes/class-pclzip.php';
        
        // Get previous backup in tmp 
        $worker_options = get_option('mwp-worker');
        $tmp_file = $upload_dir['basedir'].'/'.basename($worker_options['backups'][$type]['path']);
        
        if(rename($worker_options['backups'][$type]['path'], $tmp_file)){
		                @unlink($worker_options['backups'][$type]['path']);
		    }
        
        $site_name = get_bloginfo('name');
        
        $site_name = str_replace(array(" ","_"),array("",""),$site_name);
        
        $backup_file = $file_path.'/'.$site_name.'_'.$type.'_'.$what.'_'.date('Y-m-d').'.zip';
        
        
        if (!$this->check_zip())
        {
        	$archive = new PclZip($backup_file);
        	
        }
				
        if(trim($what) == 'full'){
        				
        				 if ($this->check_zip())
        				 {
        						$command = "zip $backup_file -j $content_backup[path] -j $db_backup[path]";
               			 ob_start();
        						 passthru($command,$err);
        						 ob_get_clean();
                 }
                 else
                 {
                  $result = $archive->add($content_backup['path'], PCLZIP_OPT_REMOVE_ALL_PATH);
                  $result = $archive->add($db_backup['path'], PCLZIP_OPT_REMOVE_ALL_PATH);
                  $err = !$result;
             			}
                
        }elseif(trim($what) == 'db'){
        	
        				 if ($this->check_zip())
        				 {
        				 	
        					$command = "zip $backup_file -j $db_backup[path]";
        					ob_start();
        					passthru($command,$err);
        					ob_get_clean();
        					
        				}
        				else
        				{
        					
        	        $result = $archive->add($db_backup['path'], PCLZIP_OPT_REMOVE_ALL_PATH);
        	        $err = !$result;
        	      }
               
                
        		}elseif(trim($what) == 'content'){
        				if ($this->check_zip())
        				{
        					$command = "zip $backup_file -j $content_backup[path]";
                	ob_start();
        					passthru($command,$err);
        					ob_get_clean();
        				}
        				else
        				{
        					$result = $archive->add($content_backup['path'], PCLZIP_OPT_REMOVE_ALL_PATH);
        					$err = !$result;
        				}
                
               
       	 	}
				
				
        if($err){
        		
        		if(rename($tmp_file,$worker_options['backups'][$type]['path'])){
		                @unlink($tmp_file);
		    		}
		    		 return false;
        }
				
				@unlink($tmp_file);
				
        @unlink($content_backup['path']);
        @unlink($db_backup['path']);

        $backup_url = $upload_dir['baseurl'].$file.'/'.$site_name.'_'.$type.'_'.$what.'_'.date('Y-m-d').'.zip';
        $worker_options = get_option('mwp-worker');
        
        //remove old file
        if($worker_options['backups'][$type]['path'] != $backup_file)
                @unlink($worker_options['backups'][$type]['path']);

        
        $worker_options['backups'][$type]['path'] = $backup_file;
        $worker_options['backups'][$type]['url'] = $backup_url;
        update_option('mwp-worker', $worker_options);
        
        
        return $worker_options['backups'][$type]['url'];
    }

    function backup_wpcontent($type) {
        
        $upload_dir = wp_upload_dir();
        $sec_string = md5('mwp-worker');
//        $file = "/$sec_string/backups";
        $file = '/'.$sec_string.'/backups/wp-content_'.date('Y-m-d').'.zip';
        $file_path = $upload_dir['basedir'] . $file;
        				if ($this->check_zip())
        				{
        					chdir(WP_CONTENT_DIR);
        					$command = "zip -r $file_path 'plugins/' 'themes/' 'uploads/' -x 'uploads/".$sec_string."/*'";
                	ob_start();
        					passthru($command,$err);
        					ob_get_clean();
        					
        					if(!$err){
            			$file_url = $upload_dir['baseurl'] . $file;
            			return array('path' => $file_path, 'url' => $file_url);
        					}
        					@unlink($file_path);
        					return false;
        					
        				}
        				else
        				{
        					require_once  ABSPATH.'/wp-admin/includes/class-pclzip.php';
        				$archive = new PclZip($file_path);
        				 $result = $archive->add(WP_CONTENT_DIR . '/plugins', PCLZIP_OPT_REMOVE_PATH, WP_CONTENT_DIR);
        				 $result = $archive->add(WP_CONTENT_DIR . '/themes', PCLZIP_OPT_REMOVE_PATH, WP_CONTENT_DIR);
        				 $result = $archive->add(WP_CONTENT_DIR . '/uploads', PCLZIP_OPT_REMOVE_PATH, WP_CONTENT_DIR);
	//        		$this->_log($archive);
        			  $result = $archive->delete(PCLZIP_OPT_BY_NAME, 'uploads/'.$sec_string.'/');
        				if($result){
            			$file_url = $upload_dir['baseurl'] . $file;
            			return array('path' => $file_path, 'url' => $file_url);
        					}
        					@unlink($file_path);
        					return false;
        			}
    }


    function backup_db($type) {
        $mysqldump_exists = $this->check_mysqldump();
				
				
        if(is_array($mysqldump_exists)){
//                $this->_log('backup dump');
								
                $result = $this->backup_db_dump($type, $mysqldump_exists);
                
        }else{
        
                $result = $this->backup_db_php($type);
        }
        return $result;
    }

    function backup_db_dump($type, $paths) {
        global $wpdb;   
        $upload_dir = wp_upload_dir();
        $sec_string = md5('mwp-worker');
    		$brace = (substr(PHP_OS, 0, 3) == 'WIN') ? '"' : '';
				
				$file = $upload_dir['path'] . '/'.DB_NAME.'.sql';
        $file_url = $upload_dir['baseurl']. '/'.DB_NAME.'.sql';
        
       	$command = $brace.$paths['mysqldump'].$brace.' --host="'.DB_HOST.'" --user="'.DB_USER.'" --password="'.DB_PASSWORD.'" --add-drop-table --skip-lock-tables "'.DB_NAME.'" > '.$brace.$file.$brace;
        
                
                passthru($command, $error);
                
                if(filesize($file) == 0 || !is_file($file) || $error) {
                
                        @unlink($file);
                        return false;
                }else{
                        
                      return array('path' => $file, 'url' => $file_url);
                }
				}

    function backup_db_php($type) {
        global $wpdb;
        //require_once  ABSPATH.'/wp-admin/includes/class-pclzip.php';
        $tables = $wpdb->get_results('SHOW TABLES', ARRAY_N);
        $upload_dir = wp_upload_dir();
        $sec_string = md5('mwp-worker');
        $zip_file = '/'.$sec_string.'/backups/db_'.date('Y-m-d').'.zip';
        $zip_file_path = $upload_dir['basedir'] . $zip_file;
        
        $file = $upload_dir['path'] . '/'.DB_NAME.'.sql';
        $file_url = $upload_dir['baseurl']. '/'.DB_NAME.'.sql';
        
       // $archive = new PclZip($zip_file_path);
//        $this->_log($zip_file_path);
//        $this->_log($tables);
        foreach($tables as $table){
            //$file = $upload_dir['path'] . '/'.$table[0].'.sql';
            
            //drop exixting table
            $dump_data = "DROP TABLE IF EXISTS $table[0];";
            //create table
            $create_table = $wpdb->get_row("SHOW CREATE TABLE $table[0]", ARRAY_N);
            $dump_data .= "\n\n".$create_table[1].";\n\n";

            $count = $wpdb->get_var("SELECT count(*) FROM $table[0]");
            if($count > 100)
                $count = ceil($count/100)-1;
            else
                $count = 1;
            for($i=0;$i<$count;$i++){
                $low_limit = $i * 100;
                $qry = "SELECT * FROM $table[0] LIMIT $low_limit, 100";
                $rows = $wpdb->get_results($qry, ARRAY_A);
                if(is_array($rows)){
                    foreach ($rows as $row) {
                        //insert single row
                        $dump_data .= "INSERT INTO $table[0] VALUES(";
                        $num_values = count($row);
                        $j = 1;
                        foreach ($row as $value) {
                            $value = addslashes($value);
                            $value = ereg_replace("\n", "\\n", $value);
//                            $dump_data .= "'".$value . "', ";
                            
//                            if($num_values == $i)
//                                $dump_data .= "'".$value . "'";
//                            else
//                                $dump_data .= "'".$value . "', ";
                            $num_values == $j ? $dump_data .= "'".$value . "'" : $dump_data .= "'".$value . "', ";
                            $j ++;
                            unset ($value);
                        }
//                        $dump_data = substr($dump_data, 0, -2);
                        $dump_data .= ");\n";
                    }
                }
            }
            $dump_data .= "\n\n\n";

            unset ($rows);
            file_put_contents($file, $dump_data, FILE_APPEND);
            unset ($dump_data);
            
           
        }
        
        
        //return false;
        return array('path' => $file, 'url' => $file_url);
				
    }

    function restore($args) {
        $this->_escape($args);
        $username = $args[0];
        $password = $args[1];
        $type = $args[2];
        
        if(trim($type) == ''){
            return false;
        }

        if (!$user = $this->login($username, $password))
        {
            return $this->error;
        }

        if(!current_user_can('administrator'))
            return new IXR_Error(401, 'Sorry, you are not allowed to perform restore action on this site.');
				
				// Set paths
				
				$upload_dir = wp_upload_dir();
        $sec_string = md5('mwp-worker');
        $backup_dir = "/$sec_string/backups";
        $file = "/$sec_string/restore";
        $file_path = $upload_dir['basedir'] . $file; //restore path - temporary
        $backup_path = $upload_dir['basedir'] . $backup_dir; //backup path

        
        // If manual backup - get backup file from master, if not - get backup file from worker
        
       	if($type != 'weekly' && $type != 'daily') 
       	{
	        	// Download backup file from master
	        	$tmp_file = download_url($type);
	        
	        	$backup_file = $backup_path."/". basename($type);
	        
	       
		        if(rename($tmp_file, $backup_file)){
		                @unlink($tmp_file);
		            }else{
		                $backup_file = $tmp_file;
		       	}
	      }
	      else
	      {
	      		// Getting file from worker
	      		$backup_file = $worker_options['backups'][$type]['path'];
	      }
            
        
//        $backup_file = glob($file_path);
//        $backup_file = $backup_file[0];
        if($backup_file){
            
            if($this->check_unzip())
            {
            mkdir($file_path);
            chdir($file_path);
        		$command = "unzip -o $backup_file";
            ob_start();
        		passthru($command,$err);
        		ob_get_clean();
        	  }
        	  else
        	  {
        	  	require_once  ABSPATH.'/wp-admin/includes/class-pclzip.php';
            	$archive = new PclZip($backup_file);
        	  	$extracted = $archive->extract(PCLZIP_OPT_PATH, $file_path, PCLZIP_OPT_REMOVE_ALL_PATH);
        	  	$err = !$extracted;
        	  }
            
            
            if($err){
								
                return false;
            }
            
            list(, $name, $what) = explode('_', basename($backup_file, '.zip'));
						
            $this->_log($what);
            
           
            if(trim($what) == 'full' || trim($what) == 'db' ){
            	
                    if(!$this->restore_db($type, $file_path)){
                            return false;
                    }
            }
            
            
            
            if(trim($what) == 'full' || trim($what) == 'content' ){
                    if(!$this->restore_wpcontent($type, $file_path)){
                        return false;
                    }
            }
            
            
            
            
            $this->_deleteTempDir($file_path);
        }
        //@lk : better error messages
        return true;
        
    }

    function restore_wpcontent($type, $file_path) {
        require_once  ABSPATH.'/wp-admin/includes/class-pclzip.php';
        $content_file = glob($file_path."/wp*");
        
        
//        $this->_log($content_file);
				if($this->check_unzip())
				{
					  
            chdir(WP_CONTENT_DIR);
            $con_file = $content_file[0];
        		$command = "unzip -o $con_file";
            ob_start();
        		passthru($command,$err);
        		ob_get_clean();
        		
				}
				else
				{
        	$archive = new PclZip($content_file[0]);
        	$restore_content = $archive->extract(PCLZIP_OPT_PATH, WP_CONTENT_DIR, PCLZIP_OPT_REPLACE_NEWER);
        	$err = !$restore_content;
        }
//        $this->_log($archive);
//        $this->_log($archive->errorInfo(TRUE));
        if($err)
            return FALSE;
        else
            return TRUE;
    }

    function restore_db($type, $file_path) {
        global $wpdb;
        
        $mysqldump = $this->check_mysqldump();
        
        //$mysqldump = ''; //debug
        if(is_array($mysqldump))
        {
        
	       	$brace = (substr(PHP_OS, 0, 3) == 'WIN') ? '"' : '';
	        
	        foreach(glob($file_path.'/*.sql') as $filename){
	        	
	        	$command = $brace.$mysqldump['mysql'].$brace.' --host="'.DB_HOST.'" --user="'.DB_USER.'" --password="'.DB_PASSWORD.'" '.DB_NAME.' < '.$brace.$filename.$brace;
	        	passthru($command, $error);
	        	
	        	break;
	        }
	        return $error ? false : true;
	      }
	      else
	      {
	      	
	      	
//        $this->_log($content_file);
        
        foreach(glob($file_path.'/*.sql') as $filename){
//                $this->_log($filename);
                // Temporary variable, used to store current query
                $current_query = '';
                // Read in entire file
                $lines = file($filename);
//                $this->_log($lines);
                // Loop through each line
                foreach ($lines as $line){
                    // Skip it if it's a comment
                    if (substr($line, 0, 2) == '--' || $line == '')
                        continue;

                    // Add this line to the current query
                    $current_query .= $line;
                    // If it has a semicolon at the end, it's the end of the query
                    if (substr(trim($line), -1, 1) == ';'){
                            // Perform the query
//                            $this->_log($current_query);
                            $result = $wpdb->query($current_query);
//                            $this->_log($result);
                            if($result === false)
                                    return FALSE;
                            // Reset temp variable to empty
                            $current_query = '';
                    }
                }
        		}
        		return true;
        
        
	      }
        
        
       
    }

    function get_weekly_backup($args) {
        $this->_escape($args);
        $username = $args[0];
        $password = $args[1];
        $type = $args[2];
        if(trim($type) == ''){
            return false;
        }

        if (!$user = $this->login($username, $password))
        {
            return $this->error;
        }

        if(!current_user_can('administrator'))
            return new IXR_Error(401, 'Sorry, you are not allowed to perform this action on the site.');

        $worker_options = get_option('mwp-worker');
        $daily_file = $worker_options['backups']['daily']['path'];

        $upload_dir = wp_upload_dir();
        $sec_string = md5('mwp-worker');
        $file = "/$sec_string/backups";
        $file_path = $upload_dir['basedir'] . $file;
        $backup_file = $file_path.'/weekly_'.date('Y-m-d').'.zip';

        if(!copy($daily_file, $backup_file)){
                return false;
        }
        $backup_path = $upload_dir['baseurl'].$file.'/weekly_'.date('Y-m-d').'.zip';
        $worker_options['backups']['weekly']['path'] = $backup_file;
        $worker_options['backups']['weekly']['url'] = $backup_url;

        return $backup_url;

    }

    function get_backup_details($args) {
        $this->_escape($args);
        $username = $args[0];
        $password = $args[1];
        $type = $args[2];
        if(trim($type) == ''){
            return false;
        }

        if (!$user = $this->login($username, $password))
        {
            return $this->error;
        }

        if(!current_user_can('administrator'))
            return new IXR_Error(401, 'Sorry, you are not allowed to perform this action on the site.');

        $worker_options = get_option('mwp-worker');
        $backup_file = $worker_options['backups'][$type]['url'];

        if(!$backup_file)
                return FALSE;
        else
                return $backup_file;
    }

        ### Function: Auto Detect MYSQL and MYSQL Dump Paths
        function check_mysqldump() {
                global $wpdb;
                $paths = array('mysq' => '', 'mysqldump' => '');
                if(substr(PHP_OS,0,3) == 'WIN') {
                        $mysql_install = $wpdb->get_row("SHOW VARIABLES LIKE 'basedir'");
                        if($mysql_install) {
                                $install_path = str_replace('\\', '/', $mysql_install->Value);
                                $paths['mysql'] = $install_path.'bin/mysql.exe';
                                $paths['mysqldump'] = $install_path.'bin/mysqldump.exe';
                        } else {
                                $paths['mysql'] = 'mysql.exe';
                                $paths['mysqldump'] = 'mysqldump.exe';
                        }
                } else {
                        if(function_exists('exec')) {
                                $paths['mysql'] = @exec('which mysql');
                                $paths['mysqldump'] = @exec('which mysqldump');
                        } else {
                                $paths['mysql'] = 'mysql';
                                $paths['mysqldump'] = 'mysqldump';
                        }
                }

                if(!@file_exists(stripslashes($paths['mysqldump']))) {
                        return false;
                }
                if(!@file_exists(stripslashes($paths['mysql']))) {
                        return false;
                }
                
                $stats_function_disabled = 0;
                
                if(!function_exists('passthru')) {
                        $stats_function_disabled++;
                }
                if(!function_exists('system')) {
                        $stats_function_disabled++;
                }
                if(!function_exists('exec')) {
                        $stats_function_disabled++;
                }

                if($stats_function_disabled == 3){
                        return false;
                }
                
                return $paths;
        }
        
        function check_zip()
        {
        	$zip = @exec('which zip');
        	return $zip ? true : false;
        }
        
        function check_unzip()
        {
        	$zip = @exec('which unzip');
        	return $zip ? true : false;
        }

}
?>
