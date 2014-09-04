<?php
/*************************************************************
 * backup.class.php
 * Manage Backups
 * Copyright (c) 2011-2014 Prelovac Media
 * www.prelovac.com
 **************************************************************/

/**
 * The main class for processing database and full backups on ManageWP worker.
 *
 * @copyright     2011-2014 Prelovac Media
 * @version       3.9.30
 * @package       ManageWP
 * @subpackage    backup
 */
class MMB_Backup extends MMB_Core
{
    public $site_name;
    public $statuses;
    public $tasks;
    public $s3;
    public $ftp;
    public $dropbox;
    public $google_drive;

    private static $zip_errors = array(
        0   => 'No error',
        1   => 'No error',
        2   => 'Unexpected end of zip file',
        3   => 'A generic error in the zipfile format was detected',
        4   => 'zip was unable to allocate itself memory',
        5   => 'A severe error in the zipfile format was detected',
        6   => 'Entry too large to be split with zipsplit',
        7   => 'Invalid comment format',
        8   => 'zip -T failed or out of memory',
        9   => 'The user aborted zip prematurely',
        10  => 'zip encountered an error while using a temp file. Please check if there is enough disk space',
        11  => 'Read or seek error',
        12  => 'zip has nothing to do',
        13  => 'Missing or empty zip file',
        14  => 'Error writing to a file. Please check if there is enough disk space',
        15  => 'zip was unable to create a file to write to',
        16  => 'bad command line parameters',
        17  => 'no error',
        18  => 'zip could not open a specified file to read',
        159 => 'File size limit exceeded',
    );

    private static $unzip_errors = array(
        0  => 'No error',
        1  => 'One or more warning errors were encountered, but processing completed successfully anyway',
        2  => 'A generic error in the zipfile format was detected',
        3  => 'A severe error in the zipfile format was detected.',
        4  => 'unzip was unable to allocate itself memory.',
        5  => 'unzip was unable to allocate memory, or encountered an encryption error',
        6  => 'unzip was unable to allocate memory during decompression to disk',
        7  => 'unzip was unable allocate memory during in-memory decompression',
        8  => 'unused',
        9  => 'The specified zipfiles were not found',
        10 => 'Bad command line parameters',
        11 => 'No matching files were found',
        50 => 'The disk is (or was) full during extraction',
        51 => 'The end of the ZIP archive was encountered prematurely.',
        80 => 'The user aborted unzip prematurely.',
        81 => 'Testing or extraction of one or more files failed due to unsupported compression methods or unsupported decryption.',
        82 => 'No files were found due to bad decryption password(s)'
    );

    /**
     * Initializes site_name, statuses, and tasks attributes.
     */
    function __construct()
    {
        parent::__construct();
        $this->site_name = str_replace(array("_", "/", "~", ":",), array("", "-", "-", "-",), rtrim($this->remove_http(get_bloginfo('url')), "/"));
        $this->statuses  = array(
            'db_dump'      => 1,
            'db_zip'       => 2,
            'files_zip'    => 3,
            's3'           => 4,
            'dropbox'      => 5,
            'ftp'          => 6,
            'email'        => 7,
            'google_drive' => 8,
            'sftp'         => 9,
            'finished'     => 100
        );

        $this->w3tc_flush();

        $this->tasks = get_option('mwp_backup_tasks');
    }

    /**
     * Tries to increase memory limit to 384M and execution time to 600s.
     *
     * @return    array    an array with two keys for execution time and memory limit (0 - if not changed, 1 - if succesfully)
     */
    function set_memory()
    {
        $changed = array('execution_time' => 0, 'memory_limit' => 0);
        ignore_user_abort(true);
        $tryLimit = 384;

        $limit = mwp_format_memory_limit(ini_get('memory_limit'));

        $matched = preg_match('/^(\d+) ([KMG]?B)$/', $limit, $match);

        if ($matched
            && (
                ($match[2] === 'GB')
                || ($match[2] === 'MB' && (int) $match[1] >= $tryLimit)
            )
        ) {
            // Memory limits are satisfied.
        } else {
            ini_set('memory_limit', $tryLimit.'M');
            $changed['memory_limit'] = 1;
        }
        if (!mwp_is_safe_mode() && ((int) ini_get('max_execution_time') < 4000) && (ini_get('max_execution_time') !== '0')) {
            ini_set('max_execution_time', 4000);
            set_time_limit(4000);
            $changed['execution_time'] = 1;
        }

        return $changed;
    }

    /**
     * Returns backup settings from local database for all tasks
     *
     * @return    mixed|boolean
     */
    function get_backup_settings()
    {
        $backup_settings = get_option('mwp_backup_tasks');

        if (!empty($backup_settings)) {
            return $backup_settings;
        } else {
            return false;
        }
    }

    /**
     * Sets backup task defined from master, if task name is "Backup Now" this function fires processing backup.
     *
     * @param    mixed $params parameters sent from master
     *
     * @return    mixed|boolean    $this->tasks variable if success, array with error message if error has ocurred, false if $params are empty
     */
    function set_backup_task($params)
    {
        //$params => [$task_name, $args, $error]
        if (!empty($params)) {

            //Make sure backup cron job is set
            /*if (!wp_next_scheduled('mwp_backup_tasks')) {
                wp_schedule_event(time(), 'tenminutes', 'mwp_backup_tasks');
            }*/

            extract($params);

            //$before = $this->get_backup_settings();
            $before = $this->tasks;
            if (!$before || empty($before)) {
                $before = array();
            }

            if (isset($args['remove'])) {
                unset($before[$task_name]);
                $return = array(
                    'removed' => true
                );
            } else {
                if (isset($params['account_info']) && is_array($params['account_info'])) { //only if sends from master first time(secure data)
                    $args['account_info'] = $account_info;
                }

                $before[$task_name]['task_args'] = $args;
                if (!empty($args['schedule']) && strlen($args['schedule'])) {
                    $before[$task_name]['task_args']['next'] = $this->schedule_next($args['type'], $args['schedule']);
                }

                $return = $before[$task_name];
            }

            //Update with error
            if (isset($error)) {
                if (is_array($error)) {
                    $before[$task_name]['task_results'][count($before[$task_name]['task_results']) - 1]['error'] = $error['error'];
                } else {
                    $before[$task_name]['task_results'][count($before[$task_name]['task_results']) - 1]['error'] = $error;
                }
            }

            if (isset($time) && $time) { //set next result time before backup
                if (is_array($before[$task_name]['task_results'])) {
                    $before[$task_name]['task_results'] = array_values($before[$task_name]['task_results']);
                }
                $before[$task_name]['task_results'][count($before[$task_name]['task_results'])]['time'] = $time;
            }

            $this->update_tasks($before);
            //update_option('mwp_backup_tasks', $before);

            if ($task_name == 'Backup Now') {
                $result          = $this->backup($args, $task_name);
                $backup_settings = $this->tasks;

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

    /**
     * Checks if scheduled task is ready for execution,
     * if it is ready master sends google_drive_token, failed_emails, success_emails if are needed.
     * @deprecated deprecated since version 3.9.29
     * @return void
     */
    function check_backup_tasks()
    {
        $this->check_cron_remove();

        $failed_emails = array();
        $settings      = $this->tasks;
        if (is_array($settings) && !empty($settings)) {
            foreach ($settings as $task_name => $setting) {
                if (isset($setting['task_args']['next']) && $setting['task_args']['next'] < time()) {
                    //if ($setting['task_args']['next'] && $_GET['force_backup']) {
                    if ($setting['task_args']['url'] && $setting['task_args']['task_id'] && $setting['task_args']['site_key']) {
                        //Check orphan task
                        $check_data = array(
                            'task_name'      => $task_name,
                            'task_id'        => $setting['task_args']['task_id'],
                            'site_key'       => $setting['task_args']['site_key'],
                            'worker_version' => $GLOBALS['MMB_WORKER_VERSION']
                        );

                        if (isset($setting['task_args']['account_info']['mwp_google_drive']['google_drive_token'])) {
                            $check_data['mwp_google_drive_refresh_token'] = true;
                        }

                        $check = $this->validate_task($check_data, $setting['task_args']['url']);
                        if ($check == 'paused' || $check == 'deleted') {
                            continue;
                        }
                        $worker_upto_3_9_22 = ($GLOBALS['MMB_WORKER_VERSION'] <= '3.9.22'); // worker version is less or equals to 3.9.22

                        // This is the patch done in worker 3.9.22 because old worked provided message in the following format:
                        // token - not found or token - {...json...}
                        // The new message is a serialized string with google_drive_token or message.
                        if ($worker_upto_3_9_22) {
                            $potential_token = substr($check, 8);
                            if (substr($check, 0, 8) == 'token - ' && $potential_token != 'not found') {
                                $this->tasks[$task_name]['task_args']['account_info']['mwp_google_drive']['google_drive_token'] = $potential_token;
                                $settings[$task_name]['task_args']['account_info']['mwp_google_drive']['google_drive_token']    = $potential_token;
                                $setting['task_args']['account_info']['mwp_google_drive']['google_drive_token']                 = $potential_token;
                            }
                        } else {
                            $potential_token = isset($check['google_drive_token']) ? $check['google_drive_token'] : false;
                            if ($potential_token) {
                                $this->tasks[$task_name]['task_args']['account_info']['mwp_google_drive']['google_drive_token'] = $potential_token;
                                $settings[$task_name]['task_args']['account_info']['mwp_google_drive']['google_drive_token']    = $potential_token;
                                $setting['task_args']['account_info']['mwp_google_drive']['google_drive_token']                 = $potential_token;
                            }
                        }

                    }

                    $update = array(
                        'task_name' => $task_name,
                        'args'      => $settings[$task_name]['task_args']
                    );

                    if ($check != 'paused') {
                        $update['time'] = time();
                    }

                    //Update task with next schedule
                    $this->set_backup_task($update);

                    if ($check == 'paused') {
                        continue;
                    }


                    $result = $this->backup($setting['task_args'], $task_name);
                    $error  = '';

                    if (is_array($result) && array_key_exists('error', $result)) {
                        $error = $result;
                        $this->set_backup_task(
                            array(
                                'task_name' => $task_name,
                                'args'      => $settings[$task_name]['task_args'],
                                'error'     => $error
                            ));
                    } else {
                        if (@count($setting['task_args']['account_info'])) {
                            $this->mwp_remote_upload($task_name);
                        }
                    }

                    break; //Only one backup per cron
                }
            }
        }

    }

    /**
     * Runs backup task invoked from ManageWP master.
     *
     * @param string $task_name name of backup task
     * @param string|bool[optional]    $google_drive_token    false if backup destination is not Google Drive, json of Google Drive token if it is remote destination (default: false)
     *
     * @return mixed                                        array with backup statistics if successful, array with error message if not
     */
    function task_now($task_name, $google_drive_token = false)
    {
        if ($google_drive_token) {
            $this->tasks[$task_name]['task_args']['account_info']['mwp_google_drive']['google_drive_token'] = $google_drive_token;
        }

        $settings = $this->tasks;
        if (!array_key_exists($task_name, $settings)) {
            return array('error' => $task_name." does not exist.");
        } else {
            $setting = $settings[$task_name];
        }

        $this->set_backup_task(array(
            'task_name' => $task_name,
            'args'      => $settings[$task_name]['task_args'],
            'time'      => time()
        ));

        //Run backup
        $result = $this->backup($setting['task_args'], $task_name);

        //Check for error
        if (is_array($result) && array_key_exists('error', $result)) {
            $this->set_backup_task(array(
                'task_name' => $task_name,
                'args'      => $settings[$task_name]['task_args'],
                'error'     => $result['error']
            ));

            return $result;
        } else {
            return $this->get_backup_stats();
        }
    }

    /**
     * Backup a full wordpress instance, including a database dump, which is placed in mwp_db dir in root folder.
     * All backups are compressed by zip and placed in wp-content/managewp/backups folder.
     *
     * @param    string $args arguments passed from master
     *                        [type] -> db, full
     *                        [what] -> daily, weekly, monthly
     *                        [account_info] -> remote destinations ftp, amazons3, dropbox, google_drive, email with their parameters
     *                        [include] -> array of folders from site root which are included to backup (wp-admin, wp-content, wp-includes are default)
     *                        [exclude] -> array of files of folders to exclude, relative to site's root
     * @param    bool|string[optional]    $task_name        the name of backup task, which backup is done (default: false)
     *
     * @return    bool|array                                false if $args are missing, array with error if error has occured, ture if is successful
     */
    function backup($args, $task_name = false)
    {
        if (!$args || empty($args)) {
            return false;
        }

        extract($args); //extract settings

        if (!empty($account_info)) {
            $found        = false;
            $destinations = array('mwp_ftp', 'mwp_sftp', 'mwp_amazon_s3', 'mwp_dropbox', 'mwp_google_drive', 'mwp_email');
            foreach ($destinations as $dest) {
                $found = $found || (isset($account_info[$dest]));
            }
            if (!$found) {
                $error_message = 'Remote destination is not supported, please update your client plugin.';

                return array(
                    'error' => $error_message
                );
            }
        }

        //Try increase memory limit	and execution time
        $this->set_memory();

        //Remove old backup(s)
        $removed = $this->remove_old_backups($task_name);
        if (is_array($removed) && isset($removed['error'])) {
            $error_message = $removed['error'];

            return $removed;
        }

        $new_file_path = MWP_BACKUP_DIR;

        if (!file_exists($new_file_path)) {
            if (!mkdir($new_file_path, 0755, true)) {
                return array(
                    'error' => 'Permission denied, make sure you have write permissions to the wp-content folder.'
                );
            }
        }

        @file_put_contents($new_file_path.'/index.php', ''); //safe

        //Prepare .zip file name
        $hash        = md5(time());
        $label       = !empty($type) ? $type : 'manual';
        $backup_file = $new_file_path.'/'.$this->site_name.'_'.$label.'_'.$what.'_'.date('Y-m-d').'_'.$hash.'.zip';
        $backup_url  = WP_CONTENT_URL.'/managewp/backups/'.$this->site_name.'_'.$label.'_'.$what.'_'.date('Y-m-d').'_'.$hash.'.zip';

        $begin_compress = microtime(true);

        //Optimize tables?
        if (isset($optimize_tables) && !empty($optimize_tables)) {
            $this->optimize_tables();
        }

        //What to backup - db or full?
        if (trim($what) == 'db') {
            $db_backup = $this->backup_db_compress($task_name, $backup_file);
            if (is_array($db_backup) && array_key_exists('error', $db_backup)) {
                $error_message = $db_backup['error'];

                return array(
                    'error' => $error_message
                );
            }
        } elseif (trim($what) == 'full') {
            if (!$exclude) {
                $exclude = array();
            }
            if (!$include) {
                $include = array();
            }
            $content_backup = $this->backup_full($task_name, $backup_file, $exclude, $include);
            if (is_array($content_backup) && array_key_exists('error', $content_backup)) {
                $error_message = $content_backup['error'];

                return array(
                    'error' => $error_message
                );
            }
        }

        $end_compress = microtime(true);

        //Update backup info
        if ($task_name) {
            //backup task (scheduled)
            $backup_settings = $this->tasks;
            $paths           = array();
            $size            = ceil(filesize($backup_file) / 1024);
            $duration        = round($end_compress - $begin_compress, 2);

            if ($size > 1000) {
                $paths['size'] = ceil($size / 1024)."MB";
            } else {
                $paths['size'] = $size.'KB';
            }

            $paths['duration'] = $duration.'s';

            if ($task_name != 'Backup Now') {
                $paths['server'] = array(
                    'file_path' => $backup_file,
                    'file_url'  => $backup_url
                );
            } else {
                $paths['server'] = array(
                    'file_path' => $backup_file,
                    'file_url'  => $backup_url
                );
            }

            if (isset($backup_settings[$task_name]['task_args']['account_info']['mwp_ftp'])) {
                $paths['ftp'] = basename($backup_url);
            }

            if (isset($backup_settings[$task_name]['task_args']['account_info']['mwp_sftp'])) {
                $paths['sftp'] = basename($backup_url);
            }
            if (isset($backup_settings[$task_name]['task_args']['account_info']['mwp_amazon_s3'])) {
                $paths['amazons3'] = basename($backup_url);
            }

            if (isset($backup_settings[$task_name]['task_args']['account_info']['mwp_dropbox'])) {
                $paths['dropbox'] = basename($backup_url);
            }

            if (isset($backup_settings[$task_name]['task_args']['account_info']['mwp_email'])) {
                $paths['email'] = basename($backup_url);
            }

            if (isset($backup_settings[$task_name]['task_args']['account_info']['mwp_google_drive'])) {
                $paths['google_drive'] = basename($backup_url);
            }

            $temp          = $backup_settings[$task_name]['task_results'];
            $temp          = @array_values($temp);
            $paths['time'] = time();

            if ($task_name != 'Backup Now') {
                $paths['status']        = $temp[count($temp) - 1]['status'];
                $temp[count($temp) - 1] = $paths;

            } else {
                $temp[count($temp)] = $paths;
            }

            $backup_settings[$task_name]['task_results'] = $temp;
            $this->update_tasks($backup_settings);
            //update_option('mwp_backup_tasks', $backup_settings);
        }

        // If there are not remote destination, set up task status to finished
        if (@count($backup_settings[$task_name]['task_args']['account_info']) == 0) {
            $this->update_status($task_name, $this->statuses['finished'], true);
        }

        return true;
    }

    /**
     * Backup a full wordpress instance, including a database dump, which is placed in mwp_db dir in root folder.
     * All backups are compressed by zip and placed in wp-content/managewp/backups folder.
     *
     * @param    string $task_name   the name of backup task, which backup is done
     * @param    string $backup_file relative path to file which backup is stored
     * @param           array        [optional]    $exclude        the list of files and folders, which are excluded from backup (default: array())
     * @param           array        [optional]    $include        the list of folders in wordpress root which are included to backup, expect wp-admin, wp-content, wp-includes, which are default (default: array())
     *
     * @return    bool|array                        true if backup is successful, or an array with error message if is failed
     */
    function backup_full($task_name, $backup_file, $exclude = array(), $include = array())
    {
        $this->update_status($task_name, $this->statuses['db_dump']);
        $db_result = $this->backup_db();

        if ($db_result == false) {
            return array(
                'error' => 'Failed to backup database.'
            );
        } else {
            if (is_array($db_result) && isset($db_result['error'])) {
                return array(
                    'error' => $db_result['error']
                );
            }
        }

        $this->update_status($task_name, $this->statuses['db_dump'], true);
        $this->update_status($task_name, $this->statuses['db_zip']);

        @file_put_contents(MWP_BACKUP_DIR.'/mwp_db/index.php', '');
        $zip_db_result = $this->zip_backup_db($task_name, $backup_file);

        if (!$zip_db_result) {
            $zip_archive_db_result = false;
            if (class_exists("ZipArchive")) {
                mwp_logger()->debug('DB zip, fallback to ZipArchive');
                $zip_archive_db_result = $this->zip_archive_backup_db($task_name, $db_result, $backup_file);
            }

            if (!$zip_archive_db_result) {
                mwp_logger()->debug('DB zip, fallback to PclZip');
                $pclzip_db_result = $this->pclzip_backup_db($task_name, $backup_file);
                if (!$pclzip_db_result) {
                    @unlink(MWP_BACKUP_DIR.'/mwp_db/index.php');
                    @unlink(MWP_BACKUP_DIR.'/mwp_db/info.json');
                    @unlink($db_result);
                    @rmdir(MWP_DB_DIR);

                    if ($archive->error_code != '') {
                        $archive->error_code = 'pclZip error ('.$archive->error_code.'): .';
                    }

                    return array(
                        'error' => 'Failed to zip database. '.$archive->error_code.$archive->error_string
                    );
                }
            }
        }

        @unlink(MWP_BACKUP_DIR.'/mwp_db/index.php');
        @unlink(MWP_BACKUP_DIR.'/mwp_db/info.json');
        @unlink($db_result);
        @rmdir(MWP_DB_DIR);

        $remove  = array(
            trim(basename(WP_CONTENT_DIR))."/managewp/backups",
            trim(basename(WP_CONTENT_DIR))."/".md5('mmb-worker')."/mwp_backups",
            trim(basename(WP_CONTENT_DIR))."/cache",
            trim(basename(WP_CONTENT_DIR))."/w3tc",
        );
        $exclude = array_merge($exclude, $remove);

        $this->update_status($task_name, $this->statuses['db_zip'], true);
        $this->update_status($task_name, $this->statuses['files_zip']);

        if (function_exists('proc_open') && $this->zipExists()) {
            $zip_result = $this->zip_backup($task_name, $backup_file, $exclude, $include);
        } else {
            $zip_result = false;
        }

        if (isset($zip_result['error'])) {
            return $zip_result;
        }

        if (!$zip_result) {
            $zip_archive_result = false;
            if (class_exists("ZipArchive")) {
                mwp_logger()->debug('Files zip fallback to ZipArchive');
                $zip_archive_result = $this->zip_archive_backup($task_name, $backup_file, $exclude, $include);
            }

            if (!$zip_archive_result) {
                mwp_logger()->debug('Files zip fallback to PclZip');
                $pclzip_result = $this->pclzip_backup($task_name, $backup_file, $exclude, $include);
                if (!$pclzip_result) {
                    @unlink(MWP_BACKUP_DIR.'/mwp_db/index.php');
                    @unlink($db_result);
                    @rmdir(MWP_DB_DIR);

                    if (!$pclzip_result) {
                        @unlink($backup_file);

                        return array(
                            'error' => 'Failed to zip files. pclZip error ('.$archive->error_code.'): .'.$archive->error_string
                        );
                    }
                }
            }
        }

        //Reconnect
        $this->wpdb_reconnect();

        $this->update_status($task_name, $this->statuses['files_zip'], true);

        return true;
    }

    /**
     * Zipping database dump and index.php in folder mwp_db by system zip command, requires zip installed on OS.
     *
     * @param string $taskName   the name of backup task
     * @param string $backupFile absolute path to zip file
     *
     * @return bool is compress successful or not
     * @todo report errors back to the user
     * @todo report error if DB dump is not found
     */
    function zip_backup_db($taskName, $backupFile)
    {
        $disableCompression = $this->tasks[$taskName]['task_args']['disable_comp'];

        $compressionLevel = $disableCompression ? '-0' : '-1'; // -0 - store files (no compression); -1 to -9  compress fastest to compress best (default is 6)

        $zip = mwp_container()->getExecutableFinder()->find('zip', 'zip');

        $processBuilder = Symfony_Process_ProcessBuilder::create()
            ->setWorkingDirectory(untrailingslashit(MWP_BACKUP_DIR))
            ->setTimeout(3600)
            ->setPrefix($zip)
            ->add('-q') // quiet operation
            ->add('-r') // recurse paths, include files in subdirs:  zip -r a path path ...
            ->add($compressionLevel)
            ->add($backupFile) // zipfile to write to
            ->add('mwp_db') // file/directory list
        ;

        try {
            if (!mwp_is_shell_available()) {
                throw new MMB_Exception("Shell is not available");
            }
            $process = $processBuilder->getProcess();
            mwp_logger()->debug('Database compression process started', array(
                'executable_location' => $zip,
                'command_line'        => $process->getCommandLine(),
            ));
            $process->start();
            while ($process->isRunning()) {
                sleep(1);
                echo ".";
                flush();
                mwp_logger()->debug('Compressing...');
            }

            if (!$process->isSuccessful()) {
                throw new Symfony_Process_Exception_ProcessFailedException($process);
            }
            mwp_logger()->info('Database compression process finished');

            return true;
        } catch (Symfony_Process_Exception_ProcessFailedException $e) {
            mwp_logger()->error('Database compression process failed', array(
                'process' => $e->getProcess(),
            ));
        } catch (Exception $e) {
            mwp_logger()->error('Error while trying to execute database compression process', array(
                'exception' => $e,
            ));
        }

        return false;
    }

    /**
     * Zipping database dump and index.php in folder mwp_db by ZipArchive class, requires php zip extension.
     *
     * @param    string $task_name   the name of backup task
     * @param    string $db_result   relative path to database dump file
     * @param    string $backup_file absolute path to zip file
     *
     * @return    bool                    is compress successful or not
     */
    function zip_archive_backup_db($task_name, $db_result, $backup_file)
    {
        $disable_comp = $this->tasks[$task_name]['task_args']['disable_comp'];
        $zip          = new ZipArchive();
        $result       = $zip->open($backup_file, ZIPARCHIVE::OVERWRITE); // Tries to open $backup_file for acrhiving
        if ($result === true) {
            $result = $result && $zip->addFile(MWP_BACKUP_DIR.'/mwp_db/index.php', "mwp_db/index.php"); // Tries to add mwp_db/index.php to $backup_file
            $result = $result && $zip->addFile($db_result, "mwp_db/".basename($db_result)); // Tries to add db dump form mwp_db dir to $backup_file
            $result = $result && $zip->close(); // Tries to close $backup_file
        } else {
            $result = false;
        }
        if ($result) {
            mwp_logger()->info('ZipArchive database compression process finished');
        } else {
            mwp_logger()->error('Error while trying to zip DB with ZipArchive');
        }

        return $result; // true if $backup_file iz zipped successfully, false if error is occured in zip process
    }

    /**
     * Zipping database dump and index.php in folder mwp_db by PclZip library.
     *
     * @param    string $task_name   the name of backup task
     * @param    string $backup_file absolute path to zip file
     *
     * @return    bool                    is compress successful or not
     */
    function pclzip_backup_db($task_name, $backup_file)
    {
        $disable_comp = $this->tasks[$task_name]['task_args']['disable_comp'];
        define('PCLZIP_TEMPORARY_DIR', MWP_BACKUP_DIR.'/');
        require_once ABSPATH.'/wp-admin/includes/class-pclzip.php';
        $zip = new PclZip($backup_file);

        if ($disable_comp) {
            $result = $zip->add(MWP_BACKUP_DIR."/mwp_db/", PCLZIP_OPT_REMOVE_PATH, MWP_BACKUP_DIR, PCLZIP_OPT_NO_COMPRESSION) !== 0;
        } else {
            $result = $zip->add(MWP_BACKUP_DIR."/mwp_db/", PCLZIP_OPT_REMOVE_PATH, MWP_BACKUP_DIR) !== 0;
        }

        return $result;
    }

    /**
     * Zipping whole site root folder and append to backup file with database dump
     * by system zip command, requires zip installed on OS.
     *
     * @param    string $task_name  the name of backup task
     * @param    string $backupFile absolute path to zip file
     * @param    array  $exclude    array of files of folders to exclude, relative to site's root
     * @param    array  $include    array of folders from site root which are included to backup (wp-admin, wp-content, wp-includes are default)
     *
     * @return    array|bool                true if successful or an array with error message if not
     */
    function zip_backup($task_name, $backupFile, $exclude, $include)
    {
        $compressionLevel = $this->tasks[$task_name]['task_args']['disable_comp'] ? 0 : 1;

        try {
            $this->backupRootFiles($compressionLevel, $backupFile, $exclude);
        } catch (Exception $e) {
            return array(
                'error' => $e->getMessage(),
            );
        }
        try {
            $this->backupDirectories($compressionLevel, $backupFile, $exclude, $include);
        } catch (Exception $e) {
            return array(
                'error' => $e->getMessage(),
            );
        }

        return true;
    }

    private function backupRootFiles($compressionLevel, $backupFile, $exclude)
    {
        $zip            = mwp_container()->getExecutableFinder()->find('zip', 'zip');
        $arguments      = array($zip, '-q', '-j', '-'.$compressionLevel, $backupFile);
        $fileExclusions = array('../', 'error_log');
        foreach ($exclude as $exclusion) {
            if (is_file(ABSPATH.$exclusion)) {
                $fileExclusions[] = $exclusion;
            }
        }

        $parentWpConfig = '';
        if (!file_exists(ABSPATH.'wp-config.php')
            && file_exists(dirname(ABSPATH).'/wp-config.php')
            && !file_exists(dirname(ABSPATH).'/wp-settings.php')
        ) {
            $parentWpConfig = '../wp-config.php';
        }

        $command = implode(' ', array_map(array('Symfony_Process_ProcessUtils', 'escapeArgument'), $arguments))." .* ./* $parentWpConfig";

        if ($fileExclusions) {
            $command .= ' '.implode(' ', array_map(array('Symfony_Process_ProcessUtils', 'escapeArgument'), array_merge(array('-x'), $fileExclusions)));
        }

        try {
            if (!mwp_is_shell_available()) {
                throw new MMB_Exception("Shell is not available");
            }
            $process = new Symfony_Process_Process($command, untrailingslashit(ABSPATH), null, null, 3600);
            mwp_logger()->debug('Root files compression process started', array(
                'executable_location' => $zip,
                'command_line'        => $process->getCommandLine(),
            ));
            $process->start();
            while ($process->isRunning()) {
                sleep(1);
                echo ".";
                flush();
                mwp_logger()->debug('Compressing...');
            }

            if ($process->isSuccessful()) {
                mwp_logger()->info('Root files compression process finished');
            } elseif ($process->getExitCode() === 18) {
                mwp_logger()->notice('Root files compression process finished with warnings; some files could not be read', array(
                    'process' => $process,
                ));
            } else {
                throw new Symfony_Process_Exception_ProcessFailedException($process);
            }
        } catch (Symfony_Process_Exception_ProcessFailedException $e) {
            mwp_logger()->error('Root files compression process failed', array(
                'process' => $e->getProcess(),
            ));
            throw $e;
        } catch (Exception $e) {
            mwp_logger()->error('Error while trying to execute root files compression process', array(
                'exception' => $e,
            ));
            throw $e;
        }
    }

    private function backupDirectories($compressionLevel, $backupFile, $exclude, $include)
    {
        $zip = mwp_container()->getExecutableFinder()->find('zip', 'zip');

        $processBuilder = Symfony_Process_ProcessBuilder::create()
            ->setWorkingDirectory(untrailingslashit(ABSPATH))
            ->setTimeout(3600)
            ->setPrefix($zip)
            ->add('-q')
            ->add('-r')
            ->add('-'.$compressionLevel)
            ->add($backupFile)
            ->add('.');

        $uploadDir = wp_upload_dir();

        $inclusions = array(
            WPINC,
            basename(WP_CONTENT_DIR),
            'wp-admin',
        );

        $path = wp_upload_dir();
        $path = $path['path'];
        if (strpos($path, WP_CONTENT_DIR) === false && strpos($path, ABSPATH) === 0) {
            $inclusions[] = ltrim(substr($path, strlen(ABSPATH)), ' /');
        }

        $include = array_merge($include, $inclusions);
        $include = array_map('untrailingslashit', $include);
        foreach ($include as $inclusion) {
            if (is_dir(ABSPATH.$inclusion)) {
                $inclusions[] = $inclusion.'/*';
            } else {
                $inclusions[] = $inclusion;
            }
        }

        $processBuilder->add('-i');
        foreach ($inclusions as $inclusion) {
            $processBuilder->add($inclusion);
        }

        $exclusions = array();
        $exclude    = array_map('untrailingslashit', $exclude);
        foreach ($exclude as $exclusion) {
            if (is_dir(ABSPATH.$exclusion)) {
                $exclusions[] = $exclusion.'/*';
            } else {
                $exclusions[] = $exclusion;
            }
        }

        if ($exclusions) {
            $processBuilder->add('-x');
            foreach ($exclusions as $exclusion) {
                $processBuilder->add($exclusion);
            }
        }

        try {
            if (!mwp_is_shell_available()) {
                throw new MMB_Exception("Shell is not available");
            }
            $process = $processBuilder->getProcess();
            mwp_logger()->info('Directory compression process started', array(
                'executable_location' => $zip,
                'command_line'        => $process->getCommandLine(),
            ));
            $process->start();
            while ($process->isRunning()) {
                sleep(1);
                echo ".";
                flush();
                mwp_logger()->debug('Compressing...');
            }

            if ($process->isSuccessful()) {
                mwp_logger()->info('Directory compression process successfully completed');
            } elseif ($process->getExitCode() === 18) {
                mwp_logger()->notice('Directory compression process finished with warnings; some files could not be read', array(
                    'process' => $process,
                ));
            } else {
                throw new Symfony_Process_Exception_ProcessFailedException($process);
            }
        } catch (Symfony_Process_Exception_ProcessFailedException $e) {
            mwp_logger()->error('Directory compression process failed', array(
                'process' => $e->getProcess(),
            ));
            throw $e;
        } catch (Exception $e) {
            mwp_logger()->error('Error while trying to execute directory compression process', array(
                'exception' => $e,
            ));
            throw $e;
        }
    }

    /**
     * Zipping whole site root folder and append to backup file with database dump
     * by ZipArchive class, requires php zip extension.
     *
     * @param    string $task_name   the name of backup task
     * @param    string $backup_file absolute path to zip file
     * @param    array  $exclude     array of files of folders to exclude, relative to site's root
     * @param    array  $include     array of folders from site root which are included to backup (wp-admin, wp-content, wp-includes are default)
     *
     * @return    array|bool                true if successful or an array with error message if not
     */
    function zip_archive_backup($task_name, $backup_file, $exclude, $include, $overwrite = false)
    {
        $filelist     = $this->get_backup_files($exclude, $include);
        $disable_comp = $this->tasks[$task_name]['task_args']['disable_comp'];
        if (!$disable_comp) {
            mwp_logger()->warning('Compression is not supported by ZipArchive');
        }

        $zip = new ZipArchive();
        if ($overwrite) {
            $result = $zip->open($backup_file, ZipArchive::OVERWRITE); // Tries to open $backup_file for archiving
        } else {
            $result = $zip->open($backup_file); // Tries to open $backup_file for archiving
        }
        if ($result === true) {
            foreach ($filelist as $file) {
                $pathInZip = strpos($file, ABSPATH) === false ? basename($file) : str_replace(ABSPATH, '', $file);
                $result    = $result && $zip->addFile($file, $pathInZip); // Tries to add a new file to $backup_file
            }
            $result = $result && $zip->close(); // Tries to close $backup_file
        } else {
            $result = false;
        }
        if ($result) {
            mwp_logger()->info('ZipArchive files compression process finished');
        } else {
            mwp_logger()->error('Error while trying to zip files with ZipArchive');
        }

        return $result; // true if $backup_file iz zipped successfully, false if error is occured in zip process
    }

    /**
     * Zipping whole site root folder and append to backup file with database dump
     * by PclZip library.
     *
     * @param    string $task_name   the name of backup task
     * @param    string $backup_file absolute path to zip file
     * @param    array  $exclude     array of files of folders to exclude, relative to site's root
     * @param    array  $include     array of folders from site root which are included to backup (wp-admin, wp-content, wp-includes are default)
     *
     * @return    array|bool                true if successful or an array with error message if not
     */
    function pclzip_backup($task_name, $backup_file, $exclude, $include)
    {
        define('PCLZIP_TEMPORARY_DIR', MWP_BACKUP_DIR.'/');
        require_once ABSPATH.'/wp-admin/includes/class-pclzip.php';
        $zip = new PclZip($backup_file);
        $add = array(
            trim(WPINC),
            trim(basename(WP_CONTENT_DIR)),
            'wp-admin'
        );

        if (!file_exists(ABSPATH.'wp-config.php')
            && file_exists(dirname(ABSPATH).'/wp-config.php')
            && !file_exists(dirname(ABSPATH).'/wp-settings.php')
        ) {
            $include[] = '../wp-config.php';
        }

        $path = wp_upload_dir();
        $path = $path['path'];
        if (strpos($path, WP_CONTENT_DIR) === false && strpos($path, ABSPATH) === 0) {
            $add[] = ltrim(substr($path, strlen(ABSPATH)), ' /');
        }

        $include_data = array();
        if (!empty($include)) {
            foreach ($include as $data) {
                if ($data && file_exists(ABSPATH.$data)) {
                    $include_data[] = ABSPATH.$data.'/';
                }
            }
        }
        $include_data = array_merge($add, $include_data);

        if ($handle = opendir(ABSPATH)) {
            while (false !== ($file = readdir($handle))) {
                if ($file != "." && $file != ".." && !is_dir($file) && file_exists(ABSPATH.$file)) {
                    $include_data[] = ABSPATH.$file;
                }
            }
            closedir($handle);
        }

        $disable_comp = $this->tasks[$task_name]['task_args']['disable_comp'];

        if ($disable_comp) {
            $result = $zip->add($include_data, PCLZIP_OPT_REMOVE_PATH, ABSPATH, PCLZIP_OPT_NO_COMPRESSION) !== 0;
        } else {
            $result = $zip->add($include_data, PCLZIP_OPT_REMOVE_PATH, ABSPATH) !== 0;
        }

        $exclude_data = array();
        if (!empty($exclude)) {
            foreach ($exclude as $data) {
                if (file_exists(ABSPATH.$data)) {
                    if (is_dir(ABSPATH.$data)) {
                        $exclude_data[] = $data.'/';
                    } else {
                        $exclude_data[] = $data;
                    }
                }
            }
        }
        $result = $result && $zip->delete(PCLZIP_OPT_BY_NAME, $exclude_data);

        return $result;
    }

    /**
     * Gets an array of relative paths of all files in site root recursively.
     * By default, there are all files from root folder, all files from folders wp-admin, wp-content, wp-includes recursively.
     * Parameter $include adds other folders from site root, and excludes any file or folder by relative path to site's root.
     *
     * @param    array $exclude array of files of folders to exclude, relative to site's root
     * @param    array $include array of folders from site root which are included to backup (wp-admin, wp-content, wp-includes are default)
     *
     * @return    array                array with all files in site root dir
     */
    function get_backup_files($exclude, $include)
    {
        $add = array(
            trim(WPINC),
            trim(basename(WP_CONTENT_DIR)),
            "wp-admin"
        );

        $include = array_merge($add, $include);
        foreach ($include as &$value) {
            $value = rtrim($value, '/');
        }

        $filelist = array();
        if ($handle = opendir(ABSPATH)) {
            while (false !== ($file = readdir($handle))) {
                if ($file !== '..' && is_dir($file) && file_exists(ABSPATH.$file) && !(in_array($file, $include))) {
                    $exclude[] = $file;
                }
            }
            closedir($handle);
        }
        $exclude[] = 'error_log';

        $filelist = get_all_files_from_dir(ABSPATH, $exclude);

        if (!file_exists(ABSPATH.'wp-config.php')
            && file_exists(dirname(ABSPATH).'/wp-config.php')
            && !file_exists(dirname(ABSPATH).'/wp-settings.php')
        ) {
            $filelist[] = dirname(ABSPATH).'/wp-config.php';
        }

        $path = wp_upload_dir();
        $path = $path['path'];
        if (strpos($path, WP_CONTENT_DIR) === false && strpos($path, ABSPATH) === 0) {
            $mediaDir = ABSPATH.ltrim(substr($path, strlen(ABSPATH)), ' /');
            if (is_dir($mediaDir)) {
                $allMediaFiles = get_all_files_from_dir($mediaDir);
                $filelist      = array_merge($filelist, $allMediaFiles);
            }
        }

        return $filelist;
    }

    /**
     * Backup a database dump of WordPress site.
     * All backups are compressed by zip and placed in wp-content/managewp/backups folder.
     *
     * @param    string $task_name   the name of backup task, which backup is done
     * @param    string $backup_file relative path to file which backup is stored
     *
     * @return    bool|array                        true if backup is successful, or an array with error message if is failed
     */
    function backup_db_compress($task_name, $backup_file)
    {
        $this->update_status($task_name, $this->statuses['db_dump']);
        $db_result = $this->backup_db();

        if ($db_result == false) {
            return array(
                'error' => 'Failed to backup database.'
            );
        } else {
            if (is_array($db_result) && isset($db_result['error'])) {
                return array(
                    'error' => $db_result['error']
                );
            }
        }

        $this->update_status($task_name, $this->statuses['db_dump'], true);
        $this->update_status($task_name, $this->statuses['db_zip']);
        @file_put_contents(MWP_BACKUP_DIR.'/mwp_db/index.php', '');
        $zip_db_result = $this->zip_backup_db($task_name, $backup_file);

        if (!$zip_db_result) {
            $zip_archive_db_result = false;
            if (class_exists("ZipArchive")) {
                $this->_log("DB zip, fallback to ZipArchive");
                $zip_archive_db_result = $this->zip_archive_backup_db($task_name, $db_result, $backup_file);
            }

            if (!$zip_archive_db_result) {
                $this->_log("DB zip, fallback to PclZip");
                $pclzip_db_result = $this->pclzip_backup_db($task_name, $backup_file);
                if (!$pclzip_db_result) {
                    @unlink(MWP_BACKUP_DIR.'/mwp_db/index.php');
                    @unlink($db_result);
                    @rmdir(MWP_DB_DIR);

                    return array(
                        'error' => 'Failed to zip database. pclZip error ('.$archive->error_code.'): .'.$archive->error_string
                    );
                }
            }
        }

        @unlink(MWP_BACKUP_DIR.'/mwp_db/index.php');
        @unlink($db_result);
        @rmdir(MWP_DB_DIR);

        $this->update_status($task_name, $this->statuses['db_zip'], true);

        return true;
    }

    /**
     * Creates database dump and places it in mwp_db folder in site's root.
     * This function dispatches if OS mysql command does not work calls a php alternative.
     *
     * @return    string|array    path to dump file if successful, or an array with error message if is failed
     */
    function backup_db()
    {
        $db_folder = MWP_DB_DIR.'/';
        if (!file_exists($db_folder)) {
            if (!mkdir($db_folder, 0755, true)) {
                return array(
                    'error' => 'Error creating database backup folder ('.$db_folder.'). Make sure you have correct write permissions.'
                );
            }
        }

        $file   = $db_folder.DB_NAME.'.sql';
        $result = $this->backup_db_dump($file); // try mysqldump always then fallback to php dump
        return $result;
    }

    function file_get_size($file)
    {
        if (!extension_loaded('bcmath')) {
            return filesize($file);
        }

        //open file
        $fh = fopen($file, "r");
        //declare some variables
        $size = "0";
        $char = "";
        //set file pointer to 0; I'm a little bit paranoid, you can remove this
        fseek($fh, 0, SEEK_SET);
        //set multiplicator to zero
        $count = 0;
        while (true) {
            //jump 1 MB forward in file
            fseek($fh, 1048576, SEEK_CUR);
            //check if we actually left the file
            if (($char = fgetc($fh)) !== false) {
                //if not, go on
                $count++;
            } else {
                //else jump back where we were before leaving and exit loop
                fseek($fh, -1048576, SEEK_CUR);
                break;
            }
        }
        //we could make $count jumps, so the file is at least $count * 1.000001 MB large
        //1048577 because we jump 1 MB and fgetc goes 1 B forward too
        $size = bcmul("1048577", $count);
        //now count the last few bytes; they're always less than 1048576 so it's quite fast
        $fine = 0;
        while (false !== ($char = fgetc($fh))) {
            $fine++;
        }
        //and add them
        $size = bcadd($size, $fine);
        fclose($fh);

        return $size;
    }

    /**
     * Creates database dump by system mysql command.
     *
     * @param string $file absolute path to file in which dump should be placed
     *
     * @return string|array path to dump file if successful, or an array with error message if is failed
     */
    function backup_db_dump($file)
    {
        $mysqldump = mwp_container()->getExecutableFinder()->find('mysqldump', 'mysqldump');

        $processBuilder = Symfony_Process_ProcessBuilder::create()
            ->setWorkingDirectory(untrailingslashit(ABSPATH))
            ->setTimeout(3600)
            ->setPrefix($mysqldump)
            ->add('--force') // Continue even if we get an SQL error.
            ->add('--user='.DB_USER) // User for login if not current user.
            ->add('--password='.DB_PASSWORD) //  Password to use when connecting to server. If password is not given it's solicited on the tty.
            ->add('--add-drop-table') // Add a DROP TABLE before each create.
            ->add('--lock-tables=false') // Don't lock all tables for read.
            ->add(DB_NAME)
            ->add('--result-file='.$file);

        $port = 0;
        $host = DB_HOST;

        if (strpos($host, ':') !== false) {
            list($host, $port) = explode(':', $host);
        }
        $socket = false;

        if (strpos(DB_HOST, '/') !== false || strpos(DB_HOST, '\\') !== false) {
            $socket = true;
            $host   = end(explode(':', DB_HOST));
        }

        if ($socket) {
            $processBuilder->add('--socket='.$host);
        } else {
            $processBuilder->add('--host='.$host);
            if(!empty($port)){
                $processBuilder->add('--port='.$port);
            }
        }

        try {
            if (!mwp_is_shell_available()) {
                throw new MMB_Exception("Shell is not available");
            }
            $process = $processBuilder->getProcess();
            mwp_logger()->info('Database dumping process started', array(
                'executable_location' => $mysqldump,
                'command_line'        => $process->getCommandLine(),
            ));
            $process->run();

            if (!$process->isSuccessful()) {
                throw new Symfony_Process_Exception_ProcessFailedException($process);
            }
        } catch (Exception $e) {
            if ($e instanceof Symfony_Process_Exception_ProcessFailedException) {
                mwp_logger()->error('Database dumping process failed', array(
                    'process' => $e->getProcess(),
                ));
            } else {
                mwp_logger()->error('Error while trying to execute database dumping process', array(
                    'exception' => $e,
                ));
            }

            if (class_exists('PDO')) {
                mwp_logger()->info('Using PHP dumper v2');
                try {
                    $config = array(
                        'username' => DB_USER,
                        'password' => DB_PASSWORD,
                        'database' => DB_NAME,
                    );

                    if ($socket) {
                        $config['socket'] = $host;
                    } else {
                        $config['host'] = $host;

                        if ($port) {
                            $config['port'] = $port;
                        }
                    }
                    MWP_Backup_Database::dump($config, array(
                        'force_method' => 'sequential',
                        'save_path'    => $file,
                    ));
                } catch (Exception $e) {
                    mwp_logger()->error('PHP dumper v2 has failed', array(
                        'exception' => $e,
                    ));

                    return false;
                }
            } else {
                mwp_logger()->info('Using PHP dumper v1');
                $result = $this->backup_db_php($file);

                if (!$result) {
                    mwp_logger()->error('PHP dumper v1 has failed');

                    return false;
                }
            }
        }


        if (filesize($file) === 0) {
            unlink($file);
            mwp_logger()->error('Database dumping process failed with unknown reason', array(
                'database_file' => $file,
            ));

            return false;
        } else {
            mwp_logger()->info('Database dumping process finished, file size is {backup_size}', array(
                'backup_size' => mwp_format_bytes(filesize($file)),
            ));

            file_put_contents(dirname($file).'/info.json', json_encode(array('table-prefix' => $GLOBALS['wpdb']->prefix, 'site-url' => get_option('siteurl'))));

            return $file;
        }
    }

    /**
     * Creates database dump by php functions.
     *
     * @param    string $file absolute path to file in which dump should be placed
     *
     * @return    string|array    path to dump file if successful, or an array with error message if is failed
     */
    function backup_db_php($file)
    {
        global $wpdb;
        $tables = $wpdb->get_results('SHOW TABLES', ARRAY_N);
        foreach ($tables as $table) {
            //drop existing table
            $dump_data = "DROP TABLE IF EXISTS $table[0];";
            file_put_contents($file, $dump_data, FILE_APPEND);
            //create table
            $create_table = $wpdb->get_row("SHOW CREATE TABLE $table[0]", ARRAY_N);
            $dump_data    = "\n\n".$create_table[1].";\n\n";
            file_put_contents($file, $dump_data, FILE_APPEND);

            $count = $wpdb->get_var("SELECT count(*) FROM $table[0]");
            if ($count > 100) {
                $count = ceil($count / 100);
            } else {
                if ($count > 0) {
                    $count = 1;
                }
            }

            for ($i = 0; $i < $count; $i++) {
                $low_limit = $i * 100;
                $qry       = "SELECT * FROM $table[0] LIMIT $low_limit, 100";
                $rows      = $wpdb->get_results($qry, ARRAY_A);
                if (is_array($rows)) {
                    foreach ($rows as $row) {
                        //insert single row
                        $dump_data  = "INSERT INTO $table[0] VALUES(";
                        $num_values = count($row);
                        $j          = 1;
                        foreach ($row as $value) {
                            $value = addslashes($value);
                            $value = preg_replace("/\n/Ui", "\\n", $value);
                            $num_values == $j ? $dump_data .= "'".$value."'" : $dump_data .= "'".$value."', ";
                            $j++;
                            unset($value);
                        }
                        $dump_data .= ");\n";
                        file_put_contents($file, $dump_data, FILE_APPEND);
                    }
                }
            }
            $dump_data = "\n\n\n";
            file_put_contents($file, $dump_data, FILE_APPEND);

            unset($rows);
            unset($dump_data);
        }

        if (filesize($file) == 0 || !is_file($file)) {
            @unlink($file);

            return array(
                'error' => 'Database backup failed. Try to enable MySQL dump on your server.'
            );
        }

        return $file;
    }

    function restore($params)
    {
        global $wpdb;
        if (empty($params)) {
            return false;
        }

        if (isset($params['google_drive_token'])) {
            $this->tasks[$params['task_name']]['task_args']['account_info']['mwp_google_drive']['google_drive_token'] = $params['google_drive_token'];
        }
        if (!empty($params['backup_url']) || !isset($this->tasks[$params['task_name']]['task_results'][$params['result_id']]['server'])) {
            /* If it is on server don't delete zipped file file after restore */
            $deleteBackupAfterRestore = true;
        }

        $this->set_memory();
        /* Get backup file*/
        try {
            $backupFile = $this->getBackup(stripslashes($params['task_name']), $params['result_id'], $params['backup_url']);
        } catch (Exception $e) {
            return array(
                'error' => $e->getMessage(),
            );
        }

        try {
            $oldCredentialsAndOptions = $this->keepOldCredentialsAndOptions($params['overwrite'], $params['clone_from_url'], $params['mwp_clone']);
            $home                     = untrailingslashit(get_option('home'));
        } catch (Exception $e) {
            $this->deleteTempBackupFile($backupFile, $deleteBackupAfterRestore);

            return array(
                'error' => $e->getMessage(),
            );
        }

        $unzipFailed = false;
        try {
            $this->unzipBackup($backupFile);
        } catch (Exception $e) {
            $unzipFailed = true;
        }

        if($unzipFailed &&  class_exists("ZipArchive")){
            $unzipFailed = false;
            try {
                $this->unzipWithZipArchive($backupFile);
            } catch (Exception $e) {
                $unzipFailed = true;
            }
        }

        if ($unzipFailed) {
            try {
                /* Fallback to PclZip Module */
                $this->pclUnzipIt($backupFile);
            } catch (Exception $e) {
                $this->deleteTempBackupFile($backupFile, $deleteBackupAfterRestore);

                return array(
                    'error' => $e->getMessage(),
                );
            }
        }

        $this->deleteTempBackupFile($backupFile, $deleteBackupAfterRestore);
        $filePath = ABSPATH.'mwp_db';

        @chmod($filePath, 0755);
        $fileName = glob($filePath.'/*.sql');
        $fileName = $fileName[0];

        $restoreDbFailed = false;

        try {
            $this->restore_db($fileName);
        } catch (Exception $e) {
            mwp_logger()->notice('Shell restore failed, error: {message} file was: {file_name}', array(
                'message'   => $e->getMessage(),
                'file_name' => $fileName,
            ));
            $restoreDbFailed = true;
        }
        if ($restoreDbFailed) {
            try {
                $this->restore_db_php($fileName);
            } catch (Exception $e) {
                @unlink($filePath.'/index.php');
                @unlink($filePath.'/info.json');
                @rmdir($filePath);

                return array(
                    'error' => $e->getMessage(),
                );
            }
        } else {
            @unlink($fileName);
        }
        @unlink($filePath.'/index.php');
        @rmdir($filePath);
        mwp_logger()->info('Restore successfully completed');

        // Try to fetch old home and site url, as well as new ones for usage later in database updates
        // Take fresh options
        $homeOpt = $wpdb->get_row($wpdb->prepare("SELECT option_value FROM $wpdb->options WHERE option_name = %s LIMIT 1", 'home'));
        $siteUrlOpt = $wpdb->get_row($wpdb->prepare("SELECT option_value FROM $wpdb->options WHERE option_name = %s LIMIT 1", 'siteurl'));
        global $restoreParams;
        $restoreParams = array (
            'oldUrl'     => is_object($homeOpt) ? $homeOpt->option_value : null,
            'oldSiteUrl'  => is_object($siteUrlOpt) ? $siteUrlOpt->option_value : null,
            'tablePrefix' => $this->get_table_prefix(),
            'newUrl'      => ''
        );

        /* Replace options and content urls */
        $this->replaceOptionsAndUrls($params['overwrite'], $params['new_user'], $params['new_password'], $params['old_user'], $params['clone_from_url'], $params['admin_email'], $params['mwp_clone'], $oldCredentialsAndOptions, $home, $params['current_tasks_tmp']);

        $newUrl = $wpdb->get_row($wpdb->prepare("SELECT option_value FROM $wpdb->options WHERE option_name = %s LIMIT 1", 'home'));
        $restoreParams['newUrl'] = is_object($newUrl) ? $newUrl->option_value : null;
        restore_migrate_urls();
        restore_htaccess();
        $this->w3tc_flush(true);
        global $configDiff;
        $result = array(
            'status' => true,
            'admins' => $this->getAdminUsers()
        );
        if (isset($configDiff)
            && is_array($configDiff)
        ) {
            $result['configDiff'] = $configDiff;
        }

        return $result;
    }

    private function getAdminUsers(){
        global $wpdb;
        $users = get_users(array(
                'role' => array('administrator'),
                'fields' => array('user_login')
            ));
        return $users;

    }

    private function getBackup($taskName, $resultId, $backupUrl = null)
    {
        if (!empty($backupUrl)) {
            /* When cloning (overwrite) */
            include_once ABSPATH.'wp-admin/includes/file.php';
            /* Use WordPress function to download file that is used to replace current WP installation */
            $backupFile = download_url($backupUrl);
            if (is_wp_error($backupFile)) {
                throw new Exception('Unable to download backup file ('.$backupFile->get_error_message().')');
            }
        } else {
            /* When doing restore of MWP Backup*/
            $task = $this->tasks[$taskName];
            if (isset($task['task_results'][$resultId]['server'])) {
                $backupFile = $task['task_results'][$resultId]['server']['file_path'];
            } elseif (isset($task['task_results'][$resultId]['ftp'])) {
                $ftp_file              = $task['task_results'][$resultId]['ftp'];
                $params                = $task['task_args']['account_info']['mwp_ftp'];
                $params['backup_file'] = $ftp_file;
                $backupFile            = $this->get_ftp_backup($params);

                if ($backupFile == false) {
                    throw new Exception('Failed to download file from FTP.');
                }
            } elseif (isset($task['task_results'][$resultId]['sftp'])) {
                $ftp_file              = $task['task_results'][$resultId]['sftp'];
                $params                = $task['task_args']['account_info']['mwp_sftp'];
                $params['backup_file'] = $ftp_file;
                $backupFile            = $this->get_sftp_backup($params);

                if ($backupFile == false) {
                    throw new Exception('Failed to download file from SFTP.');
                }
            } elseif (isset($task['task_results'][$resultId]['amazons3'])) {
                $amazons3File          = $task['task_results'][$resultId]['amazons3'];
                $params                = $task['task_args']['account_info']['mwp_amazon_s3'];
                $params['backup_file'] = $amazons3File;
                $backupFile            = $this->get_amazons3_backup($params);

                if ($backupFile == false) {
                    throw new Exception('Failed to download file from Amazon S3.');
                }
            } elseif (isset($task['task_results'][$resultId]['dropbox'])) {
                $dropboxFile           = $task['task_results'][$resultId]['dropbox'];
                $params                = $task['task_args']['account_info']['mwp_dropbox'];
                $params['backup_file'] = $dropboxFile;
                $backupFile            = $this->get_dropbox_backup($params);

                if ($backupFile == false) {
                    throw new Exception('Failed to download file from Dropbox.');
                }
            } elseif (isset($task['task_results'][$resultId]['google_drive'])) {
                $googleDriveFile       = $task['task_results'][$resultId]['google_drive'];
                $params                = $task['task_args']['account_info']['mwp_google_drive'];
                $params['backup_file'] = $googleDriveFile;
                $backupFile            = $this->get_google_drive_backup($params);

                if (is_array($backupFile) && isset($backupFile['error'])) {
                    throw new Exception('Failed to download file from Google Drive, reason: '.$backupFile['error']);
                } elseif ($backupFile == false) {
                    throw new Exception('Failed to download file from Google Drive.');
                }
            }
        }

        if (is_array($backupFile) && isset($backupFile['error'])) {
            throw new Exception('Error restoring: '.$backupFile['error']);
        }

        if (!($backupFile && file_exists($backupFile))) {
            throw new Exception('Error restoring. Cannot find backup file.');
        }
        mwp_logger()->info('Download of backup file successfully completed.');

        return $backupFile;
    }

    private function keepOldCredentialsAndOptions($overwrite = false, $cloneFromUrl, $mwpClone)
    {
        $oldOptions                    = array();
        $oldOptions['clone_options']   = array();
        $oldOptions['restore_options'] = array();
        $this->wpdb_reconnect();

        if ($overwrite) {
            /* Keep old db credentials before overwrite */
            if (!copy(ABSPATH.'wp-config.php', ABSPATH.'mwp-temp-wp-config.php')) {
                throw new Exception('Error creating wp-config file.
                                    Please check if your WordPress installation folder has correct permissions to allow  writing files.
                                    In most cases permissions should be 755 but occasionally it\'s required to put 777.
                                    If you are unsure on how to do this yourself, you can ask your hosting provider for help.');
            }
            if (trim($cloneFromUrl) || trim($mwpClone)) {
                $oldOptions['clone_options']['_worker_nossl_key']  = get_option('_worker_nossl_key');
                $oldOptions['clone_options']['_worker_public_key'] = get_option('_worker_public_key');
                $oldOptions['clone_options']['_action_message_id'] = get_option('_action_message_id');
            }
            $oldOptions['clone_options']['upload_path']     = get_option('upload_path');
            $oldOptions['clone_options']['upload_url_path'] = get_option('upload_url_path');


            $oldOptions['clone_options']['mwp_backup_tasks']    = maybe_serialize(get_option('mwp_backup_tasks'));
            $oldOptions['clone_options']['mwp_notifications']   = maybe_serialize(get_option('mwp_notifications'));
            $oldOptions['clone_options']['mwp_pageview_alerts'] = maybe_serialize(get_option('mwp_pageview_alerts'));
        } else {
            $oldOptions['restore_options']['mwp_notifications']   = get_option('mwp_notifications');
            $oldOptions['restore_options']['mwp_pageview_alerts'] = get_option('mwp_pageview_alerts');
            $oldOptions['restore_options']['user_hit_count']      = get_option('user_hit_count');
            $oldOptions['restore_options']['mwp_backup_tasks']    = get_option('mwp_backup_tasks');
        }

        return $oldOptions;
    }

    private function unzipBackup($backupFile)
    {
        $unzip          = mwp_container()->getExecutableFinder()->find('unzip', 'unzip');
        $processBuilder = Symfony_Process_ProcessBuilder::create()
            ->setWorkingDirectory(untrailingslashit(ABSPATH))
            ->setTimeout(3600)
            ->setPrefix($unzip)
            ->add('-o')
            ->add($backupFile);
        try {
            if (!mwp_is_shell_available()) {
                throw new MMB_Exception("Shell is not available");
            }
            $process = $processBuilder->getProcess();
            mwp_logger()->info('Backup extraction process started', array(
                'executable_location' => $unzip,
                'command_line'        => $process->getCommandLine(),
            ));
            $process->run();
            if (!$process->isSuccessful()) {
                throw new Symfony_Process_Exception_ProcessFailedException($process);
            }
            mwp_logger()->info('Backup extraction process finished');
        } catch (Symfony_Process_Exception_ProcessFailedException $e) {
            mwp_logger()->error('Backup extraction process failed', array(
                'process' => $e->getProcess(),
            ));
            throw $e;
        } catch (Exception $e) {
            mwp_logger()->error('Error while trying to execute backup extraction process', array(
                'exception' => $e,
            ));
            throw $e;
        }
    }

    private function unzipWithZipArchive($backupFile)
    {
        mwp_logger()->info('Falling back to ZipArchive Module');
        $result = false;
        $zipArchive = new ZipArchive();
        $zipOpened = $zipArchive->open($backupFile);
        if($zipOpened === true){
            $result = $zipArchive->extractTo(ABSPATH);
            $zipArchive->close();
        }
        if($result === false){
            throw new Exception('Failed to unzip files with ZipArchive. Message: '. $zipArchive->getStatusString());
        }
    }

    private function pclUnzipIt($backupFile)
    {
        mwp_logger()->info('Falling back to PclZip Module');
        define('PCLZIP_TEMPORARY_DIR', MWP_BACKUP_DIR.'/');
        require_once ABSPATH.'/wp-admin/includes/class-pclzip.php';
        $archive = new PclZip($backupFile);
        $result  = $archive->extract(PCLZIP_OPT_PATH, ABSPATH, PCLZIP_OPT_REPLACE_NEWER);

        if (!$result) {
            throw new Exception('Failed to unzip files. pclZip error ('.$archive->error_code.'): .'.$archive->error_string);
        }
    }

    private function deleteTempBackupFile($backupFile, $deleteBackupAfterRestore)
    {
        if ($deleteBackupAfterRestore) {
            @unlink($backupFile);
        }
    }

    private function replaceOptionsAndUrls($overwrite, $newUser, $newPassword, $oldUser, $cloneFromUrl, $adminEmail, $mwpClone, $oldCredentialsAndOptions, $home, $currentTasksTmp)
    {
        global $wpdb;
        $this->wpdb_reconnect();

        if ($overwrite) {
            /* Get New Table prefix */
            $new_table_prefix = trim($this->get_table_prefix());

            $configPath            = ABSPATH . 'wp-config.php';
            $sourceConfigCopyPath  = ABSPATH . 'wp-config.source.php';
            $destinationConfigPath = ABSPATH . 'mwp-temp-wp-config.php';

            @rename($configPath, $sourceConfigCopyPath);

            /* Config keys diff */
            $tokenizer                = new MWP_Parser_DefinitionTokenizer();
            $destinationConfigContent = @file_get_contents($destinationConfigPath);
            $sourceConfigContent      = @file_get_contents($sourceConfigCopyPath);

            if (is_string($destinationConfigContent) && is_string($sourceConfigContent)) {
                $sourceTokens      = $tokenizer->getDefinitions($sourceConfigContent);
                $destinationTokens = $tokenizer->getDefinitions($destinationConfigContent);

                if (is_array($sourceTokens) && is_array($destinationTokens)) {
                    // First declaration of $configDiff
                    global $configDiff;
                    $configDiff = array(
                        'additions'    => array_values(array_diff($sourceTokens, $destinationTokens)),
                        'subtractions' => array_values(array_diff($destinationTokens, $sourceTokens))
                    );
                }
            }
            @unlink($sourceConfigCopyPath);

            /* Retrieve old wp_config */
            $lines = file($destinationConfigPath);

            /* Replace table prefix */
            foreach ($lines as $line) {
                if (strstr($line, '$table_prefix')) {
                    $line = '$table_prefix = "'.$new_table_prefix.'";'.PHP_EOL;
                }
                file_put_contents($configPath, $line, FILE_APPEND);
            }

            @unlink($destinationConfigPath);

            /* Replace options */
            $query = "SELECT option_value FROM ".$new_table_prefix."options WHERE option_name = 'home'";
            $old   = $wpdb->get_var($query);
            $old   = rtrim($old, "/");
            $query = "UPDATE ".$new_table_prefix."options SET option_value = %s WHERE option_name = 'home'";
            $wpdb->query($wpdb->prepare($query, $home));
            $query = "UPDATE ".$new_table_prefix."options  SET option_value = %s WHERE option_name = 'siteurl'";
            $wpdb->query($wpdb->prepare($query, $home));

            /* Replace content urls */
            $regexp1 = 'src="(.*)$old(.*)"';
            $regexp2 = 'href="(.*)$old(.*)"';
            $query   = "UPDATE ".$new_table_prefix."posts SET post_content = REPLACE (post_content, %s,%s) WHERE post_content REGEXP %s OR post_content REGEXP %s";
            $wpdb->query($wpdb->prepare($query, array($old, $home, $regexp1, $regexp2)));

            if (trim($newPassword)) {
                $newPassword = wp_hash_password($newPassword);
            }
            if (!trim($newPassword) && !trim($mwpClone)) {
                if ($newUser && $newPassword) {
                    $query = "UPDATE ".$new_table_prefix."users SET user_login = %s, user_pass = %s WHERE user_login = %s";
                    $wpdb->query($wpdb->prepare($query, $newUser, $newPassword, $oldUser));
                }
            } else {
                if ($cloneFromUrl) {
                    if ($newUser && $newPassword) {
                        $query = "UPDATE ".$new_table_prefix."users SET user_pass = %s WHERE user_login = %s";
                        $wpdb->query($wpdb->prepare($query, $newPassword, $newUser));
                    }
                }

                if ($mwpClone) {
                    if ($adminEmail) {
                        /* Clean Install */
                        $query = "UPDATE ".$new_table_prefix."options SET option_value = %s WHERE option_name = 'admin_email'";
                        $wpdb->query($wpdb->prepare($query, $adminEmail));
                        $query     = "SELECT * FROM ".$new_table_prefix."users LIMIT 1";
                        $temp_user = $wpdb->get_row($query);
                        if (!empty($temp_user)) {
                            $query = "UPDATE ".$new_table_prefix."users SET user_email=%s, user_login = %s, user_pass = %s WHERE user_login = %s";
                            $wpdb->query($wpdb->prepare($query, $adminEmail, $newUser, $newPassword, $temp_user->user_login));
                        }

                    }
                }
            }

            if (is_array($oldCredentialsAndOptions['clone_options']) && !empty($oldCredentialsAndOptions['clone_options'])) {
                foreach ($oldCredentialsAndOptions['clone_options'] as $key => $option) {
                    if (!empty($key)) {
                        $query = "SELECT option_value FROM ".$new_table_prefix."options WHERE option_name = %s";
                        $res   = $wpdb->get_var($wpdb->prepare($query, $key));
                        if ($res === false) {
                            $query = "INSERT INTO ".$new_table_prefix."options  (option_value,option_name) VALUES(%s,%s)";
                            $wpdb->query($wpdb->prepare($query, $option, $key));
                        } else {
                            $query = "UPDATE ".$new_table_prefix."options  SET option_value = %s WHERE option_name = %s";
                            $wpdb->query($wpdb->prepare($query, $option, $key));
                        }
                    }
                }
            }

            /* Remove hit count */
            $query = "DELETE FROM ".$new_table_prefix."options WHERE option_name = 'user_hit_count'";
            $wpdb->query($query);

            /* Restore previous backups */
            $wpdb->query("UPDATE ".$new_table_prefix."options SET option_value = '".serialize($currentTasksTmp)."' WHERE option_name = 'mwp_backup_tasks'");

            /* Check for .htaccess permalinks update */
            $this->replace_htaccess($home);
        } else {
            /* restore worker options */
            if (is_array($oldCredentialsAndOptions['restore_options']) && !empty($oldCredentialsAndOptions['restore_options'])) {
                foreach ($oldCredentialsAndOptions['restore_options'] as $key => $option) {
                    $wpdb->update($wpdb->options, array('option_value' => maybe_serialize($option)), array('option_name' => $key));
                }
            }
        }
    }

    function restore_db($fileName)
    {
        if (!$fileName) {
            throw new Exception('Cannot access database file.');
        }

        $port = 0;
        $host = DB_HOST;

        if (strpos(DB_HOST, ':') !== false) {
            list($host, $port) = explode(':', DB_HOST);
        }
        $socket = false;

        if (strpos(DB_HOST, '/') !== false || strpos(DB_HOST, '\\') !== false) {
            $socket = true;
            $host   = end(explode(':', DB_HOST));
        }

        if ($socket) {
            $connection = array('--socket='.$host);
        } else {
            $connection = array('--host='.$host);
            if (!empty($port)) {
                $connection[] = '--port='.$port;
            }
        }

        $mysql     = mwp_container()->getExecutableFinder()->find('mysql', 'mysql');
        $arguments = array_merge(array($mysql, '--user='.DB_USER, '--password='.DB_PASSWORD, '--default-character-set=utf8', DB_NAME), $connection);
        $command   = implode(' ', array_map(array('Symfony_Process_ProcessUtils', 'escapeArgument'), $arguments)).' < '.Symfony_Process_ProcessUtils::escapeArgument($fileName);

        try {
            if (!mwp_is_shell_available()) {
                throw new MMB_Exception("Shell is not available");
            }
            $process = new Symfony_Process_Process($command, untrailingslashit(ABSPATH), null, null, 3600);
            mwp_logger()->info('Database import process started', array(
                'executable_location' => $mysql,
                'command_line'        => $process->getCommandLine(),
            ));
            $process->run();

            if (!$process->isSuccessful()) {
                throw new Symfony_Process_Exception_ProcessFailedException($process);
            }
        } catch (Symfony_Process_Exception_ProcessFailedException $e) {
            //unlink($fileName);
            mwp_logger()->error('Database import process failed', array(
                'process' => $e->getProcess(),
            ));
            throw $e;
        } catch (Exception $e) {
            //unlink($fileName);
            mwp_logger()->error('Error while trying to execute database import process', array(
                'exception' => $e,
            ));
            throw $e;
        }
        mwp_logger()->info('Database import process finished');
//        unlink($fileName);
    }


    /**
     * Restores database dump by php functions.
     *
     * @param    string $file_name relative path to database dump, which should be restored
     *
     * @return    bool                is successful or not
     */
    function restore_db_php($file_name)
    {
        global $wpdb;

        $current_query = '';
        mwp_logger()->info('PHP DB import process started');
        // Read in entire file
//        $lines = file($file_name);
        $fp = @fopen($file_name, 'r');
        if (!$fp) {
            throw new Exception("Failed restoring database: could not open dump file ($file_name)");
        }
        while (!feof($fp)) {
            $line = fgets($fp);

            // Skip it if it's a comment
            if (substr($line, 0, 2) == '--' || $line == '') {
                continue;
            }

            // Add this line to the current query
            $current_query .= $line;
            // If it has a semicolon at the end, it's the end of the query
            if (substr(trim($line), -1, 1) == ';') {
                // Perform the query
                $trimmed = trim($current_query, " ;\n");
                if (!empty($trimmed)) {
                    $result = $wpdb->query($current_query);
                    if ($result === false) {
                        @fclose($fp);
                        @unlink($file_name);
                        throw new Exception("Error while restoring database on ($current_query) $wpdb->last_error");
                    }
                }
                // Reset temp variable to empty
                $current_query = '';
            }
        }
        @fclose($fp);
        @unlink($file_name);
    }

    /**
     * Retruns table_prefix for this WordPress installation.
     * It is used by restore.
     *
     * @return    string    table prefix from wp-config.php file, (default: wp_)
     */
    function get_table_prefix()
    {
        $lines = file(ABSPATH.'wp-config.php');
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

    /**
     * Change all tables to InnoDB engine, and executes mysql OPTIMIZE TABLE for each table.
     *
     * @return    bool    optimized successfully or not
     */
    function optimize_tables()
    {
        global $wpdb;
        $query        = 'SHOW TABLE STATUS';
        $tables       = $wpdb->get_results($query, ARRAY_A);
        $table_string = '';
        foreach ($tables as $table) {
            $table_string .= $table['Name'].",";
        }
        $table_string = rtrim($table_string, ",");
        $optimize     = $wpdb->query("OPTIMIZE TABLE $table_string");

        return (bool) $optimize;

    }

    public function getServerInformationForStats()
    {
        $serverInfo              = array();
        $serverInfo['zip']       = $this->zipExists();
        $serverInfo['unzip']     = $this->unzipExists();
        $serverInfo['proc']      = $this->procOpenExists();
        $serverInfo['mysql']     = $this->mySqlExists();
        $serverInfo['mysqldump'] = $this->mySqlDumpExists();
        $serverInfo['curl']      = false;
        $serverInfo['shell']     = mwp_is_shell_available();

        if (function_exists('curl_init') && function_exists('curl_exec')) {
            $serverInfo['curl'] = true;
        }

        return $serverInfo;
    }

    /**
     * Check if proc_open exists
     *
     * @return    string|bool    exec if exists, then system, then passthru, then false if no one exist
     */
    private function procOpenExists()
    {
        if ($this->mmb_function_exists('proc_open') && $this->mmb_function_exists('escapeshellarg')) {
            return true;
        }

        return false;
    }

    private function zipExists()
    {
        $zip            = mwp_container()->getExecutableFinder()->find('zip', 'zip');
        $processBuilder = Symfony_Process_ProcessBuilder::create()
            ->setWorkingDirectory(untrailingslashit(ABSPATH))
            ->setPrefix($zip);
        try {
            if (!mwp_is_shell_available()) {
                throw new MMB_Exception("Shell is not available");
            }
            $process = $processBuilder->getProcess();
            $process->run();

            return $process->isSuccessful();
        } catch (Exception $e) {
            return false;
        }
    }

    private function unzipExists()
    {
        $unzip          = mwp_container()->getExecutableFinder()->find('unzip', 'unzip');
        $processBuilder = Symfony_Process_ProcessBuilder::create()
            ->setWorkingDirectory(untrailingslashit(ABSPATH))
            ->setPrefix($unzip)
            ->add('-h');
        try {
            if (!mwp_is_shell_available()) {
                throw new MMB_Exception("Shell is not available");
            }
            $process = $processBuilder->getProcess();
            $process->run();

            return $process->isSuccessful();
        } catch (Exception $e) {
            return false;
        }
    }

    private function mySqlDumpExists()
    {
        $mysqldump      = mwp_container()->getExecutableFinder()->find('mysqldump', 'mysqldump');
        $processBuilder = Symfony_Process_ProcessBuilder::create()
            ->setWorkingDirectory(untrailingslashit(ABSPATH))
            ->setPrefix($mysqldump)
            ->add('--version');
        try {
            if (!mwp_is_shell_available()) {
                throw new MMB_Exception("Shell is not available");
            }
            $process = $processBuilder->getProcess();
            $process->run();

            return $process->isSuccessful();
        } catch (Exception $e) {
            return false;
        }
    }

    private function mySqlExists()
    {
        $mysql          = mwp_container()->getExecutableFinder()->find('mysql', 'mysql');
        $processBuilder = Symfony_Process_ProcessBuilder::create()
            ->setWorkingDirectory(untrailingslashit(ABSPATH))
            ->setPrefix($mysql)
            ->add('--version');
        try {
            if (!mwp_is_shell_available()) {
                throw new MMB_Exception("Shell is not available");
            }
            $process = $processBuilder->getProcess();
            $process->run();

            return $process->isSuccessful();
        } catch (Exception $e) {
            return false;
        }
    }

    private function is32Bits()
    {
        return strlen(decbin(~0)) == 32;
    }

    /**
     * Returns all important information of worker's system status to master.
     *
     * @return    mixed    associative array with information of server OS, php version, is backup folder writable, execute function, zip and unzip command, execution time, memory limit and path to error log if exists
     */
    function check_backup_compat()
    {
        $reqs = array();
        if (strpos($_SERVER['DOCUMENT_ROOT'], '/') === 0) {
            $reqs['Server OS']['status'] = 'Linux (or compatible)';
            $reqs['Server OS']['pass']   = true;
        } else {
            $reqs['Server OS']['status'] = 'Windows';
            $reqs['Server OS']['pass']   = true;
            $pass                        = false;
        }
        $reqs['Process architecture']['status'] = '64bit';
        $reqs['Process architecture']['pass']   = true;
        if ($this->is32Bits()) {
            $reqs['Process architecture']['status'] = '';
            $reqs['Process architecture']['info']   = '32bit';
            $reqs['Process architecture']['pass']   = false;
        }
        $reqs['PHP Version']['status'] = PHP_VERSION;
        if (version_compare(PHP_VERSION, '5.3', '>=')) {
            $reqs['PHP Version']['pass'] = true;
        } else {
            $reqs['PHP Version']['status'] = '';
            $reqs['PHP Version']['info']   = PHP_VERSION;
            $reqs['PHP Version']['pass']   = false;
            $pass                          = false;
        }

        if (mwp_is_safe_mode()) {
            $reqs['Safe Mode']['status'] = 'on';
            $reqs['Safe Mode']['pass']   = false;
        } else {
            $reqs['Safe Mode']['status'] = 'off';
            $reqs['Safe Mode']['pass']   = true;
        }

        if (is_writable(WP_CONTENT_DIR)) {
            $reqs['Backup Folder']['status'] = "writable";
            $reqs['Backup Folder']['pass']   = true;
        } else {
            $reqs['Backup Folder']['status'] = "not writable";
            $reqs['Backup Folder']['pass']   = false;
        }

        if (is_writable(ABSPATH)) {
            $reqs['Root Folder']['status'] = "writable";
            $reqs['Root Folder']['pass']   = true;
        } else {
            $reqs['Root Folder']['status'] = "not writable";
            $reqs['Root Folder']['pass']   = false;
        }

        $file_path = MWP_BACKUP_DIR;
        $reqs['Backup Folder']['status'] .= ' ('.$file_path.')';

        $reqs['Function `proc_open`']['status'] = 'exists';
        $reqs['Function `proc_open`']['pass']   = true;
        if (!$this->procOpenExists()) {
            $reqs['Function `proc_open`']['status'] = "not found";
            $reqs['Function `proc_open`']['pass']   = false;
        }

        $reqs['Zip']['status'] = 'exists';
        $reqs['Zip']['pass']   = true;
        if (!$this->zipExists()) {
            $reqs['Zip']['status'] = 'not found';
            //$reqs['Zip']['info']   = 'We\'ll use ZipArchive replacement';
            $reqs['Zip']['pass'] = false;

            $reqs['ZipArchive']['status'] = 'exists';
            $reqs['ZipArchive']['pass']   = true;
            if (!class_exists('ZipArchive')) {
                $reqs['ZipArchive']['status'] = 'not found';
                $reqs['ZipArchive']['info']   = 'We\'ll use PclZip replacement (PclZip takes up the memory that is equal to size of your site)';
                $reqs['ZipArchive']['pass']   = false;
            }
        }

        $reqs['Unzip']['status'] = 'exists';
        $reqs['Unzip']['pass']   = true;
        if (!$this->unzipExists()) {
            $reqs['Unzip']['status'] = 'not found';
            $reqs['Unzip']['info']   = 'We\'ll use PclZip replacement (PclZip takes up the memory that is equal to size of your site)';
            $reqs['Unzip']['pass']   = false;
        }

        $reqs['MySQL Dump']['status'] = 'exists';
        $reqs['MySQL Dump']['pass']   = true;
        if (!$this->mySqlDumpExists()) {
            $reqs['MySQL Dump']['status'] = "not found";
            $reqs['MySQL Dump']['info']   = "(we'll use PHP replacement)";
            $reqs['MySQL Dump']['pass']   = false;
        }

        $reqs['MySQL']['status'] = 'exists';
        $reqs['MySQL']['pass']   = true;
        if (!$this->mySqlExists()) {
            $reqs['MySQL']['status'] = "not found";
            $reqs['MySQL']['info']   = "(we'll use PHP replacement)";
            $reqs['MySQL']['pass']   = false;
        }
        $reqs['Curl']['status'] = 'not found';
        $reqs['Curl']['pass']   = false;
        if (function_exists('curl_init') && function_exists('curl_exec')) {
            $reqs['Curl']['status'] = 'exists';
            $reqs['Curl']['pass']   = true;
        }
        $exec_time                            = ini_get('max_execution_time');
        $exec_unlimited                       = ($exec_time === '0');
        $reqs['PHP Execution time']['status'] = ($exec_unlimited ? 'unlimited' : ($exec_time ? $exec_time."s" : 'unknown'));
        $reqs['PHP Execution time']['pass']   = true;

        $mem_limit                          = ini_get('memory_limit');
        $mem_limit                          = mwp_format_memory_limit($mem_limit);
        $reqs['PHP Memory limit']['status'] = $mem_limit ? $mem_limit : 'unknown';
        $reqs['PHP Memory limit']['pass']   = true;

        $changed = $this->set_memory();
        if ($changed['execution_time']) {
            $exec_time = ini_get('max_execution_time');
            $reqs['PHP Execution time']['status'] .= $exec_time ? ' (will try '.$exec_time.'s)' : ' (unknown)';
        }
        if ($changed['memory_limit']) {
            $mem_limit = ini_get('memory_limit');
            $mem_limit = mwp_format_memory_limit($mem_limit);
            $reqs['PHP Memory limit']['status'] .= $mem_limit ? ' (will try '.$mem_limit.')' : ' (unknown)';
        }

        $reqs['Worker Version']['status'] = $GLOBALS['MMB_WORKER_VERSION'];
        $reqs['Worker Version']['pass']   = true;

        $reqs['Worker Revision']['status'] = $GLOBALS['MMB_WORKER_REVISION'];
        $reqs['Worker Revision']['pass']   = true;

        return $reqs;
    }

    /**
     * Uploads backup file from server to email.
     * A lot of email service have limitation to 10mb.
     *
     * @param    array $args arguments passed to the function
     *                       [email] -> email address which backup should send to
     *                       [task_name] -> name of backup task
     *                       [file_path] -> absolute path of backup file on local server
     *
     * @return    bool|array        true is successful, array with error message if not
     */
    function email_backup($args)
    {
        $email = $args['email'];

        if (!is_email($email)) {
            return array(
                'error' => 'Your email ('.$email.') is not correct'
            );
        }
        $backup_file = $args['file_path'];
        $task_name   = isset($args['task_name']) ? $args['task_name'] : '';
        if (file_exists($backup_file)) {
            $attachments = array(
                $backup_file
            );
            $headers     = 'From: ManageWP <no-reply@managewp.com>'."\r\n";
            $subject     = "ManageWP - ".$task_name." - ".$this->site_name;
            ob_start();
            $result = wp_mail($email, $subject, $subject, $headers, $attachments);
            ob_end_clean();
        } else {
            return array(
                'error' => 'The backup file ('.$backup_file.') does not exist.'
            );
        }

        if (!$result) {
            return array(
                'error' => 'Email not sent. Maybe your backup is too big for email or email server is not available on your website.'
            );
        }

        return true;
    }

    /**
     * Uploads backup file from server to remote sftp server.
     *
     * @param    array $args arguments passed to the function
     *                       [sftp_username] -> sftp username on remote server
     *                       [sftp_password] -> sftp password on remote server
     *                       [sftp_hostname] -> sftp hostname of remote host
     *                       [sftp_remote_folder] -> folder on remote site which backup file should be upload to
     *                       [sftp_site_folder] -> subfolder with site name in ftp_remote_folder which backup file should be upload to
     *                       [sftp_passive] -> passive mode or not
     *                       [sftp_ssl] -> ssl or not
     *                       [sftp_port] -> number of port for ssl protocol
     *                       [backup_file] -> absolute path of backup file on local server
     *
     * @return    bool|array        true is successful, array with error message if not
     */
    function sftp_backup($args)
    {
        $port               = $args['sftp_port'] ? (int) $args['sftp_port'] : 22; //default port is 22
        $sftp_hostname      = $args['sftp_hostname'];
        $sftp_username      = $args['sftp_username'];
        $sftp_password      = $args['sftp_password'];
        $sftp_remote_folder = untrailingslashit($args['sftp_remote_folder']);
        $sftp_site_folder   = (bool) $args['sftp_site_folder'];
        $backup_file        = $args['backup_file'];
        $errorCatcher       = new MWP_Debug_ErrorCatcher();

        if ($sftp_site_folder) {
            $sftp_remote_folder .= ($sftp_remote_folder ? '/' : '').$this->site_name;
        }

        try {
            $sftp = $this->sftpFactory($sftp_username, $sftp_password, $sftp_hostname, $port);
        } catch (Exception $e) {
            return array(
                'error'   => $e->getMessage(),
                'partial' => 1,
            );
        }

        mwp_logger()->info('Creating backup directory {sftp_directory}', array(
            'sftp_directory' => $sftp_remote_folder,
        ));

        $errorCatcher->register();
        $directoryCreated = $sftp->mkdir($sftp_remote_folder, -1, true);
        $errorCatcher->unRegister();

        if (!$directoryCreated && ($caughtError = $errorCatcher->yieldErrorMessage())) {
            mwp_logger()->error('Unable to create SFTP directory {sftp_directory} (error message: {error_message})', array(
                'sftp_directory' => $sftp_remote_folder,
                'error_message'  => $caughtError,
            ));

            return array(
                'error'   => sprintf('Could not create backup directory (%s). Error message: %s.', $sftp_remote_folder, $caughtError),
                'partial' => 1,
            );
        }

        mwp_logger()->info('Uploading backup file "{backup_file}" (size: {backup_size}) to SFTP server', array(
            'backup_file' => $backup_file,
            'backup_size' => mwp_format_bytes(filesize($backup_file)),
        ));

        $started = microtime(true);

        $errorCatcher->register();
        $fileUploaded = $sftp->put($sftp_remote_folder.'/'.basename($backup_file), $backup_file, NET_SFTP_LOCAL_FILE);
        $errorCatcher->unRegister();

        if (!$fileUploaded) {
            if ($caughtError = $errorCatcher->yieldErrorMessage()) {
                $errorMessage = sprintf(' Error message: %s.', $caughtError);
            } else {
                $errorMessage = sprintf(' Are you sure you have permissions to write to the directory "%s"?', $sftp_remote_folder);
            }
            mwp_logger()->error('Error while uploading the backup file to SFTP (error message: {error_message})', array(
                'error_message' => empty($caughtError) ? 'empty' : $caughtError,
            ));

            return array(
                'error' => 'Unable to upload backup file.'.$errorMessage,
            );
        }
        mwp_logger()->info('SFTP upload successfully completed; average speed is {speed}/s', array(
            'speed' => mwp_format_bytes(round(filesize($backup_file) / (microtime(true) - $started))),
        ));

        $sftp->disconnect();

        return true;
    }

    private function ftpErrorMessage($message, $additionalMessage = null)
    {
        if ($additionalMessage) {
            $message .= ' Message: '.$additionalMessage.'.';
        }

        return $message;
    }

    private function ftpFactory($username, $password, $host, $port = 21, $ssl = false, $passive = false, $timeout = 10)
    {
        $errorCatcher = new MWP_Debug_ErrorCatcher();
        if ($ssl) {
            if (!function_exists('ftp_ssl_connect')) {
                throw new Exception('FTPS disabled: Please enable ftp_ssl_connect in PHP.');
            }
            $errorCatcher->register('ftp_ssl_connect');
            $ftp = ftp_ssl_connect($host, $port, $timeout);
            $errorCatcher->unRegister();
        } else {
            if (!function_exists('ftp_connect')) {
                throw new Exception('FTP disabled: Please enable ftp_connect in PHP.');
            }
            $errorCatcher->register('ftp_connect');
            $ftp = ftp_connect($host, $port, $timeout);
            $errorCatcher->unRegister();
        }

        if ($ftp === false) {
            throw new Exception($this->ftpErrorMessage('Failed connecting to the FTP server, please check FTP host and port.', $errorCatcher->yieldErrorMessage()));
        }

        $errorCatcher->register('ftp_login');
        $login = ftp_login($ftp, $username, $password);
        $errorCatcher->unRegister();

        if ($login === false) {
            throw new Exception($this->ftpErrorMessage('FTP login failed, please check your FTP login details.', $errorCatcher->yieldErrorMessage()));
        }

        if ($passive) {
            ftp_pasv($ftp, true);
        }

        return $ftp;
    }

    /**
     * Uploads backup file from server to remote ftp server.
     *
     * @param    array $args arguments passed to the function
     *                       [ftp_username] -> ftp username on remote server
     *                       [ftp_password] -> ftp password on remote server
     *                       [ftp_hostname] -> ftp hostname of remote host
     *                       [ftp_remote_folder] -> folder on remote site which backup file should be upload to
     *                       [ftp_site_folder] -> subfolder with site name in ftp_remote_folder which backup file should be upload to
     *                       [ftp_passive] -> passive mode or not
     *                       [ftp_ssl] -> ssl or not
     *                       [ftp_port] -> number of port for ssl protocol
     *                       [backup_file] -> absolute path of backup file on local server
     *
     * @return    bool|array        true is successful, array with error message if not
     */
    function ftp_backup($args)
    {
        $port              = $args['ftp_port'] ? $args['ftp_port'] : 21;
        $ftp_hostname      = $args['ftp_hostname'];
        $ftp_password      = $args['ftp_password'];
        $ftp_username      = $args['ftp_username'];
        $ftp_ssl           = (bool) $args['ftp_ssl'];
        $ftp_passive       = (bool) $args['ftp_passive'];
        $ftp_site_folder   = (bool) $args['ftp_site_folder'];
        $backup_file       = $args['backup_file'];
        $ftp_remote_folder = untrailingslashit($args['ftp_remote_folder']);

        if ($ftp_site_folder) {
            $ftp_remote_folder .= ($ftp_remote_folder ? '/' : '').$this->site_name;
        }

        $errorCatcher = new MWP_Debug_ErrorCatcher();

        try {
            $ftp = $this->ftpFactory($ftp_username, $ftp_password, $ftp_hostname, $port, $ftp_ssl, $ftp_passive);
        } catch (Exception $e) {
            return array(
                'error' => $e->getMessage(),
            );
        }

        try {
            $this->ftpMkdir($ftp, $ftp_remote_folder);
        } catch (Exception $e) {
            return array(
                'error' => $e->getMessage(),
            );
        }

        $errorCatcher->register('ftp_put');
        $uploaded = ftp_put($ftp, $ftp_remote_folder.'/'.basename($backup_file), $backup_file, FTP_BINARY);
        $errorCatcher->unRegister();

        if (!$uploaded) {
            $errorMessage = 'Failed to upload the backup file.';
            $caughtError  = $errorCatcher->yieldErrorMessage();

            if (!(bool) $args['ftp_passive'] && $caughtError && (strpos($caughtError, 'I won\'t open a connection to') !== false)) {
                $errorMessage .= ' Have you tried enabling the passive mode?';
            }

            return array(
                'error' => $this->ftpErrorMessage($errorMessage, $errorCatcher->yieldErrorMessage()),
            );
        }

        ftp_close($ftp);

        return true;
    }

    private function ftpMkdir($ftp, $path)
    {
        $errorCatcher = new MWP_Debug_ErrorCatcher();
        $path         = str_replace('\\', '/', $path);
        $path         = str_replace('//', '/', $path);

        if (substr($path, 0, 1) === '/') {
            $currentPath = '/';
            $path        = substr($path, 1);
        } else {
            $currentPath = '';
        }

        $path  = rtrim($path, '/');
        $paths = explode('/', $path);
        while ($directory = array_shift($paths)) {
            $errorCatcher->register('ftp_nlist');
            $dirList = ftp_nlist($ftp, $currentPath);
            $errorCatcher->unRegister();
            $currentPath .= $directory;

            if ($dirList === false) {
                throw new Exception($this->ftpErrorMessage(sprintf('Unable to list FTP directory content (directory: "%s").', $currentPath), $errorCatcher->yieldErrorMessage()));
            }

            $dirList = array_map('basename', $dirList);

            $dirExists = in_array($directory, $dirList);

            if (!$dirExists) {
                $errorCatcher->register('ftp_mkdir');
                $dirMade = ftp_mkdir($ftp, $currentPath);
                $errorCatcher->unRegister();

                if (!$dirMade) {
                    throw new Exception($this->ftpErrorMessage(sprintf('Unable to make directory %s.', $currentPath), $errorCatcher->yieldErrorMessage()));
                }
            }

            $currentPath .= '/';
        }
    }

    /**
     * Deletes backup file from remote ftp server.
     *
     * @param    array $args arguments passed to the function
     *                       [ftp_username] -> ftp username on remote server
     *                       [ftp_password] -> ftp password on remote server
     *                       [ftp_hostname] -> ftp hostname of remote host
     *                       [ftp_remote_folder] -> folder on remote site which backup file should be deleted from
     *                       [ftp_site_folder] -> subfolder with site name in ftp_remote_folder which backup file should be deleted from
     *                       [backup_file] -> absolute path of backup file on local server
     *
     * @return    void
     */
    function remove_ftp_backup($args)
    {
        $port              = $args['ftp_port'] ? (int) $args['ftp_port'] : 21;
        $ftp_remote_folder = untrailingslashit($args['ftp_remote_folder']);
        $errorCatcher      = new MWP_Debug_ErrorCatcher();

        if ($args['ftp_site_folder']) {
            $ftp_remote_folder .= ($ftp_remote_folder ? '/' : '').$this->site_name;
        }

        try {
            $ftp = $this->ftpFactory($args['ftp_username'], $args['ftp_password'], $args['ftp_hostname'], $port, (bool) $args['ftp_ssl'], (bool) $args['ftp_passive']);
        } catch (Exception $e) {
            mwp_logger()->error('Unable to connect to FTP server at {ftp_user}@{ftp_host}:{ftp_port}', array(
                'ftp_user'      => $args['ftp_username'],
                'ftp_host'      => $args['ftp_hostname'],
                'ftp_port'      => $port,
                'error_message' => $e->getMessage(),
            ));

            return;
        }


        $errorCatcher->register('ftp_delete');
        $delete = ftp_delete($ftp, $ftp_remote_folder.'/'.$args['backup_file']);
        $errorCatcher->unRegister();

        if (!$delete) {
            $caughtError = $errorCatcher->yieldErrorMessage();
            mwp_logger()->error('Error while deleting backup file from FTP; error message: {error_message}', array(
                'error_message' => empty($caughtError) ? 'empty' : $caughtError,
            ));
        }

        ftp_close($ftp);
    }

    /**
     * Deletes backup file from remote sftp server.
     *
     * @param    array $args arguments passed to the function
     *                       [sftp_username] -> sftp username on remote server
     *                       [sftp_password] -> sftp password on remote server
     *                       [sftp_hostname] -> sftp hostname of remote host
     *                       [sftp_remote_folder] -> folder on remote site which backup file should be deleted from
     *                       [sftp_site_folder] -> subfolder with site name in ftp_remote_folder which backup file should be deleted from
     *                       [backup_file] -> absolute path of backup file on local server
     *
     * @return    void
     */
    function remove_sftp_backup($args)
    {
        $port          = $args['sftp_port'] ? (int) $args['sftp_port'] : 22; //default port is 22
        $sftp_hostname = $args['sftp_hostname'];
        $sftp_username = $args['sftp_username'];
        $sftp_password = $args['sftp_password'];
        $backup_file   = $args['backup_file'];

        $sftp_remote_folder = untrailingslashit($args['sftp_remote_folder']);

        if ($args['sftp_site_folder']) {
            $sftp_remote_folder .= ($sftp_remote_folder ? '/' : '').$this->site_name;
        }

        try {
            $sftp = $this->sftpFactory($sftp_username, $sftp_password, $sftp_hostname, $port);
        } catch (Exception $e) {
            return;
        }

        $sftp->delete($sftp_remote_folder.'/'.$backup_file, false);

        $sftp->disconnect();
    }


    /**
     * Downloads backup file from server from remote ftp server to root folder on local server.
     *
     * @param    array $args arguments passed to the function
     *                       [ftp_username] -> ftp username on remote server
     *                       [ftp_password] -> ftp password on remote server
     *                       [ftp_hostname] -> ftp hostname of remote host
     *                       [ftp_remote_folder] -> folder on remote site which backup file should be downloaded from
     *                       [ftp_site_folder] -> subfolder with site name in ftp_remote_folder which backup file should be downloaded from
     *                       [backup_file] -> absolute path of backup file on local server
     *
     * @return    string|array    absolute path to downloaded file is successful, array with error message if not
     */
    function get_ftp_backup($args)
    {
        $port              = $args['ftp_port'] ? (int) $args['ftp_port'] : 21;
        $ftp_remote_folder = untrailingslashit($args['ftp_remote_folder']);
        $backup_file       = $args['backup_file'];

        if ($args['ftp_site_folder']) {
            $ftp_remote_folder .= ($ftp_remote_folder ? '/' : '').$this->site_name;
        }

        try {
            $ftp = $this->ftpFactory($args['ftp_username'], $args['ftp_password'], $args['ftp_hostname'], $port, (bool) $args['ftp_ssl'], $args['ftp_passive']);
        } catch (Exception $e) {
            mwp_logger()->error('Unable to connect to FTP server at {ftp_user}@{ftp_host}:{ftp_port}', array(
                'ftp_user'      => $args['ftp_username'],
                'ftp_host'      => $args['ftp_hostname'],
                'ftp_port'      => $port,
                'error_message' => $e->getMessage(),
            ));

            return array(
                'error' => $e->getMessage(),
            );
        }

        $errorCatcher = new MWP_Debug_ErrorCatcher();

        $temp = ABSPATH.'mwp_temp_backup.zip';

        $errorCatcher->register('ftp_get');
        $fileDownloaded = ftp_get($ftp, $temp, $ftp_remote_folder.'/'.$backup_file, FTP_BINARY);
        $errorCatcher->unRegister();

        if ($fileDownloaded === false) {
            $caughtError = $errorCatcher->yieldErrorMessage();
            mwp_logger()->error('Error while deleting backup file from FTP; error message: {error_message}', array(
                'error_message' => empty($caughtError) ? 'empty' : $caughtError,
            ));

            return array(
                'error' => $this->ftpErrorMessage('Error while downloading the backup file.', $caughtError),
            );
        }

        ftp_close($ftp);

        return $temp;
    }


    /**
     * Downloads backup file from server from remote ftp server to root folder on local server.
     *
     * @param    array $args arguments passed to the function
     *                       [sftp_username] -> ftp username on remote server
     *                       [sftp_password] -> ftp password on remote server
     *                       [sftp_hostname] -> ftp hostname of remote host
     *                       [sftp_remote_folder] -> folder on remote site which backup file should be downloaded from
     *                       [sftp_site_folder] -> subfolder with site name in ftp_remote_folder which backup file should be downloaded from
     *                       [backup_file] -> absolute path of backup file on local server
     *
     * @return    string|array    absolute path to downloaded file is successful, array with error message if not
     */
    function get_sftp_backup($args)
    {
        $port          = $args['sftp_port'] ? (int) $args['sftp_port'] : 22;
        $sftp_hostname = $args['sftp_hostname'];
        $sftp_username = $args['sftp_username'];
        $sftp_password = $args['sftp_password'];
        $backup_file   = $args['backup_file'];
        $errorCatcher  = new MWP_Debug_ErrorCatcher();

        $sftp_remote_folder = untrailingslashit($args['sftp_remote_folder']);

        if ($args['sftp_site_folder']) {
            $sftp_remote_folder .= ($sftp_remote_folder ? '/' : '').$this->site_name;
        }

        try {
            $sftp = $this->sftpFactory($sftp_username, $sftp_password, $sftp_hostname, $port);
        } catch (Exception $e) {
            return array(
                'error' => $e->getMessage(),
            );
        }

        $temp    = ABSPATH.'mwp_temp_backup.zip';
        $started = microtime(true);

        mwp_logger()->info('Attempting to download the backup file from SFTP to a temporary location', array(
            'backup_file'    => $sftp_remote_folder.'/'.$backup_file,
            'temporary_path' => $temp,
        ));

        $errorCatcher->register();
        $fileDownloaded = $sftp->get($sftp_remote_folder.'/'.$backup_file, $temp);
        $errorCatcher->unRegister();

        if (!$fileDownloaded) {
            $caughtError = $errorCatcher->yieldErrorMessage();
            mwp_logger()->error('Error while attempting to download backup file; error message: {error_message}', array(
                'error_message' => empty($caughtError) ? 'empty' : $caughtError,
            ));

            return array(
                'error' => $this->ftpErrorMessage('Error while attempting to download the backup file.', $caughtError),
            );
        }

        $speed = mwp_format_bytes(filesize($temp) / (round(microtime(true) - $started)));
        mwp_logger()->info('Backup file successfully downloaded from SFTP; average speed is {speed}/s', array(
            'speed' => $speed,
        ));

        $sftp->disconnect();

        return $temp;
    }

    private function sftpFactory($username, $password, $host, $port = 22)
    {
        $errorCatcher = new MWP_Debug_ErrorCatcher();

        mwp_logger()->info('Connecting to SFTP host {sftp_host}:{sftp_port}', array(
            'sftp_host' => $host,
            'sftp_port' => $port,
        ));

        require_once dirname(__FILE__).'/../PHPSecLib/Net/SFTP.php';
        $errorCatcher->register();
        $sftp = new Net_SFTP($host, $port);
        $errorCatcher->unRegister();

        if ($caughtError = $errorCatcher->yieldErrorMessage()) {
            mwp_logger()->error('Error while connecting to SFTP: {error_message}', array(
                'error_message' => $caughtError,
            ));

            throw new Exception('Host did not respond to the SFTP connection request. Error message: '.$caughtError);
        }

        mwp_logger()->info('Logging in to SFTP host {sftp_user}@{sftp_host}:{sftp_port} (using password: {using_password})', array(
            'sftp_user'      => $username,
            'sftp_host'      => $host,
            'sftp_port'      => $port,
            'using_password' => empty($password) ? 'no' : 'yes',
        ));

        $errorCatcher->register();
        $login = $sftp->login($username, $password);
        $errorCatcher->unRegister();

        if (!$login) {
            $errorMessage = '';

            if ($caughtError = $errorCatcher->yieldErrorMessage()) {
                $errorMessage = sprintf(' Error message: %s.', $caughtError);
            }

            mwp_logger()->error('Unable to login to SFTP host {sftp_host}:{sftp_port} (error message: {error_message})', array(
                'sftp_host'     => $host,
                'sftp_port'     => $port,
                'error_message' => empty($caughtError) ? 'empty' : $caughtError,
            ));

            throw new Exception('SFTP server has rejected the provided credentials.'.$errorMessage);
        }

        return $sftp;
    }

    /**
     * Uploads backup file from server to Dropbox.
     *
     * @param    array $args arguments passed to the function
     *                       [consumer_key] -> consumer key of ManageWP Dropbox application
     *                       [consumer_secret] -> consumer secret of ManageWP Dropbox application
     *                       [oauth_token] -> oauth token of user on ManageWP Dropbox application
     *                       [oauth_token_secret] -> oauth token secret of user on ManageWP Dropbox application
     *                       [dropbox_destination] -> folder on user's Dropbox account which backup file should be upload to
     *                       [dropbox_site_folder] -> subfolder with site name in dropbox_destination which backup file should be upload to
     *                       [backup_file] -> absolute path of backup file on local server
     *
     * @return    bool|array        true is successful, array with error message if not
     */
    function dropbox_backup($args)
    {
        mwp_logger()->info('Acquiring Dropbox token to start uploading the backup file');
        try {
            $dropbox = mwp_dropbox_oauth1_factory($args['consumer_key'], $args['consumer_secret'], $args['oauth_token'], $args['oauth_token_secret']);
        } catch (Exception $e) {
            mwp_logger()->error('Error while acquiring Dropbox token', array(
                'exception' => $e,
            ));

            return array(
                'error'   => $e->getMessage(),
                'partial' => 1
            );
        }

        $args['dropbox_destination'] = '/'.ltrim($args['dropbox_destination'], '/');

        if ($args['dropbox_site_folder'] == true) {
            $args['dropbox_destination'] .= '/'.$this->site_name.'/'.basename($args['backup_file']);
        } else {
            $args['dropbox_destination'] .= '/'.basename($args['backup_file']);
        }

        $fileSize = filesize($args['backup_file']);
        $start    = microtime(true);

        try {
            mwp_logger()->info('Uploading backup file to Dropbox; file size is {backup_size} (progress support: {progress_support})', array(
                'backup_file'      => $args['backup_file'],
                'backup_size'      => mwp_format_bytes($fileSize),
                'directory'        => $args['dropbox_destination'],
                'progress_support' => version_compare(PHP_VERSION, '5.3', '>=') ? 'enabled' : 'disabled',
            ));
            $callback = null;

            if (version_compare(PHP_VERSION, '5.3', '>=')) {
                $progress = new MWP_Progress_Upload($fileSize, 3, mwp_logger());
                $callback = $progress->getCallback();
            }
            $dropbox->uploadFile($args['dropbox_destination'], Dropbox_WriteMode::force(), fopen($args['backup_file'], 'r'), $fileSize, $callback);
        } catch (Exception $e) {
            mwp_logger()->error('Error while uploading the file to Dropbox', array(
                'exception' => $e,
            ));

            return array(
                'error'   => $e->getMessage(),
                'partial' => 1
            );
        }

        mwp_logger()->info('Backup to Dropbox completed; average speed is {speed}/s', array(
            'speed' => mwp_format_bytes($fileSize / (microtime(true) - $start)),
        ));

        return true;
    }

    /**
     * Deletes backup file from Dropbox to root folder on local server.
     *
     * @param    array $args arguments passed to the function
     *                       [consumer_key] -> consumer key of ManageWP Dropbox application
     *                       [consumer_secret] -> consumer secret of ManageWP Dropbox application
     *                       [oauth_token] -> oauth token of user on ManageWP Dropbox application
     *                       [oauth_token_secret] -> oauth token secret of user on ManageWP Dropbox application
     *                       [dropbox_destination] -> folder on user's Dropbox account which backup file should be downloaded from
     *                       [dropbox_site_folder] -> subfolder with site name in dropbox_destination which backup file should be downloaded from
     *                       [backup_file] -> absolute path of backup file on local server
     *
     * @return    void
     */
    function remove_dropbox_backup($args)
    {
        mwp_logger()->info('Acquiring Dropbox token to remove a backup file');
        try {
            $dropbox = mwp_dropbox_oauth1_factory($args['consumer_key'], $args['consumer_secret'], $args['oauth_token'], $args['oauth_token_secret']);
        } catch (Exception $e) {
            mwp_logger()->error('Error while acquiring Dropbox token', array(
                'exception' => $e,
            ));

            return;
        }

        $args['dropbox_destination'] = '/'.ltrim($args['dropbox_destination'], '/');

        if ($args['dropbox_site_folder'] == true) {
            $args['dropbox_destination'] .= '/'.$this->site_name;
        }

        mwp_logger()->info('Removing backup file from Dropbox', array(
            'backup_file' => $args['backup_file'],
        ));
        try {
            $dropbox->delete($args['dropbox_destination'].'/'.$args['backup_file']);
        } catch (Exception $e) {
            mwp_logger()->error('Error while acquiring Dropbox token: [{class}] {message}', array(
                'exception' => $e,
            ));
        }
    }

    /**
     * Downloads backup file from Dropbox to root folder on local server.
     *
     * @param    array $args arguments passed to the function
     *                       [consumer_key] -> consumer key of ManageWP Dropbox application
     *                       [consumer_secret] -> consumer secret of ManageWP Dropbox application
     *                       [oauth_token] -> oauth token of user on ManageWP Dropbox application
     *                       [oauth_token_secret] -> oauth token secret of user on ManageWP Dropbox application
     *                       [dropbox_destination] -> folder on user's Dropbox account which backup file should be deleted from
     *                       [dropbox_site_folder] -> subfolder with site name in dropbox_destination which backup file should be deleted from
     *                       [backup_file] -> absolute path of backup file on local server
     *
     * @return    bool|array        absolute path to downloaded file is successful, array with error message if not
     */
    function get_dropbox_backup($args)
    {
        mwp_logger()->info('Acquiring Dropbox token to download the backup file');
        try {
            $dropbox = mwp_dropbox_oauth1_factory($args['consumer_key'], $args['consumer_secret'], $args['oauth_token'], $args['oauth_token_secret']);
        } catch (Exception $e) {
            mwp_logger()->error('Error while acquiring Dropbox token', array(
                'exception' => $e,
            ));

            return array(
                'error'   => $e->getMessage(),
                'partial' => 1
            );
        }

        $args['dropbox_destination'] = '/'.ltrim($args['dropbox_destination'], '/');

        if ($args['dropbox_site_folder'] == true) {
            $args['dropbox_destination'] .= '/'.$this->site_name;
        }

        $file = $args['dropbox_destination'].'/'.$args['backup_file'];
        $temp = ABSPATH.'mwp_temp_backup.zip';

        mwp_logger()->info('Downloading backup file from Dropbox to a temporary path', array(
            'backup_file' => $file,
            'temp_path'   => $temp,
        ));

        $start = microtime(true);
        try {
            $fh = fopen($temp, 'wb');

            if (!$fh) {
                throw new RuntimeException(sprintf('Temporary file (%s) is not writable', $temp));
            }

            $dropbox->getFile($file, $fh);
            $result = fclose($fh);

            if (!$result) {
                throw new Exception('Unable to close file handle.');
            }
        } catch (Exception $e) {
            mwp_logger()->error('Downloading backup file from Dropbox failed', array(
                'exception' => $e,
            ));

            return array(
                'error'   => $e->getMessage(),
                'partial' => 1
            );
        }

        $fileSize = filesize($temp);
        mwp_logger()->info('Downloading backup file from Dropbox completed; file size is {backup_size}; average speed is {speed}', array(
            'backup_size' => mwp_format_bytes($fileSize),
            'speed'       => mwp_format_bytes($fileSize / (microtime(true) - $start))
        ));

        return $temp;
    }

    /**
     * Uploads backup file from server to Amazon S3.
     *
     * @param    array $args arguments passed to the function
     *                       [as3_bucket_region] -> Amazon S3 bucket region
     *                       [as3_bucket] -> Amazon S3 bucket
     *                       [as3_access_key] -> Amazon S3 access key
     *                       [as3_secure_key] -> Amazon S3 secure key
     *                       [as3_directory] -> folder on user's Amazon S3 account which backup file should be upload to
     *                       [as3_site_folder] -> subfolder with site name in as3_directory which backup file should be upload to
     *                       [backup_file] -> absolute path of backup file on local server
     *
     * @return    bool|array        true is successful, array with error message if not
     */
    function amazons3_backup($args)
    {
        if ($args['as3_site_folder'] == true) {
            $args['as3_directory'] .= '/'.$this->site_name;
        }
        $endpoint        = isset($args['as3_bucket_region']) ? $args['as3_bucket_region'] : 's3.amazonaws.com';
        $fileSize        = filesize($args['backup_file']);
        $progressSupport = version_compare(PHP_VERSION, '5.3', '>=');
        $start           = microtime(true);

        mwp_logger()->info('Uploading backup file to Amazon S3)', array(
            'directory'        => $args['as3_directory'],
            'bucket'           => $args['as3_bucket'],
            'endpoint'         => $endpoint,
            'backup_file'      => $args['backup_file'],
            'backup_size'      => $fileSize,
            'progress_support' => ($progressSupport ? 'enabled' : 'disabled'),
        ));

        try {
            $s3 = new S3_Client(trim($args['as3_access_key']), trim(str_replace(' ', '+', $args['as3_secure_key'])), false, $endpoint);
            $s3->setExceptions(true);

            if ($progressSupport) {
                $progress = new MWP_Progress_Upload(filesize($args['backup_file']), 3, mwp_logger());
                $s3->setProgressCallback($progress->getCallback());
            }

            $s3->putObjectFile($args['backup_file'], $args['as3_bucket'], $args['as3_directory'].'/'.basename($args['backup_file']), S3_Client::ACL_PRIVATE);
        } catch (Exception $e) {
            mwp_logger()->error('Upload to Amazon S3 failed', array(
                'exception' => $e,
            ));

            return array(
                'error' => 'Failed to upload to Amazon S3 ('.$e->getMessage().').',
            );
        }

        mwp_logger()->info('Upload to Amazon S3 completed; average speed is {speed}/s', array(
            'speed' => mwp_format_bytes($fileSize / (microtime(true) - $start)),
        ));

        return true;
    }


    /**
     * Deletes backup file from Amazon S3.
     *
     * @param    array $args arguments passed to the function
     *                       [as3_bucket_region] -> Amazon S3 bucket region
     *                       [as3_bucket] -> Amazon S3 bucket
     *                       [as3_access_key] -> Amazon S3 access key
     *                       [as3_secure_key] -> Amazon S3 secure key
     *                       [as3_directory] -> folder on user's Amazon S3 account which backup file should be deleted from
     *                       [as3_site_folder] -> subfolder with site name in as3_directory which backup file should be deleted from
     *                       [backup_file] -> absolute path of backup file on local server
     *
     * @return    void
     */
    function remove_amazons3_backup($args)
    {
        if ($args['as3_site_folder'] == true) {
            $args['as3_directory'] .= '/'.$this->site_name;
        }
        $endpoint = isset($args['as3_bucket_region']) ? $args['as3_bucket_region'] : 's3.amazonaws.com';

        mwp_logger()->info('Removing the backup file from Amazon S3', array(
            'directory'   => $args['as3_directory'],
            'bucket'      => $args['as3_bucket'],
            'endpoint'    => $endpoint,
            'backup_file' => $args['backup_file'],
        ));

        try {
            $s3 = new S3_Client(trim($args['as3_access_key']), trim(str_replace(' ', '+', $args['as3_secure_key'])), false, $endpoint);
            $s3->setExceptions(true);
            $s3->deleteObject($args['as3_bucket'], $args['as3_directory'].'/'.$args['backup_file']);
        } catch (Exception $e) {
            // @todo what now?
        }
    }

    /**
     * Downloads backup file from Amazon S3 to root folder on local server.
     *
     * @param    array $args arguments passed to the function
     *                       [as3_bucket_region] -> Amazon S3 bucket region
     *                       [as3_bucket] -> Amazon S3 bucket
     *                       [as3_access_key] -> Amazon S3 access key
     *                       [as3_secure_key] -> Amazon S3 secure key
     *                       [as3_directory] -> folder on user's Amazon S3 account which backup file should be downloaded from
     *                       [as3_site_folder] -> subfolder with site name in as3_directory which backup file should be downloaded from
     *                       [backup_file] -> absolute path of backup file on local server
     *
     * @return    bool|array        absolute path to downloaded file is successful, array with error message if not
     */
    function get_amazons3_backup($args)
    {
        $endpoint = isset($args['as3_bucket_region']) ? $args['as3_bucket_region'] : 's3.amazonaws.com';

        mwp_logger()->info('Downloading the backup file from Amazon S3', array(
            'directory'   => $args['as3_directory'],
            'bucket'      => $args['as3_bucket'],
            'endpoint'    => $args['endpoint'],
            'backup_file' => $args['backup_file'],
        ));

        if ($args['as3_site_folder'] == true) {
            $args['as3_directory'] .= '/'.$this->site_name;
        }
        $start = microtime(true);
        try {
            $s3 = new S3_Client($args['as3_access_key'], str_replace(' ', '+', $args['as3_secure_key']), false, $endpoint);
            $s3->setExceptions(true);
            $temp = ABSPATH.'mwp_temp_backup.zip';

            if (version_compare(PHP_VERSION, '5.3', '>=')) {
                $progress = new MWP_Progress_Download(3, mwp_logger());
                $s3->setProgressCallback($progress->getCallback());
            }
            $s3->getObject($args['as3_bucket'], $args['as3_directory'].'/'.$args['backup_file'], $temp);
        } catch (Exception $e) {
            mwp_logger()->error('Error while downloading the backup file', array(
                'exception' => $e,
            ));

            return array(
                'error' => 'Error while downloading the backup file from Amazon S3: '.$e->getMessage(),
            );
        }

        $fileSize = filesize($temp);
        mwp_logger()->info('Downloading backup file from Amazon S3 completed; file size is {backup_size}; average speed is {speed}', array(
            'backup_size' => mwp_format_bytes($fileSize),
            'speed'       => mwp_format_bytes($fileSize / (microtime(true) - $start))
        ));

        return $temp;
    }

    /**
     * Uploads backup file from server to Google Drive.
     *
     * @param    array $args arguments passed to the function
     *                       [google_drive_token] -> user's Google drive token in json form
     *                       [google_drive_directory] -> folder on user's Google Drive account which backup file should be upload to
     *                       [google_drive_site_folder] -> subfolder with site name in google_drive_directory which backup file should be upload to
     *                       [backup_file] -> absolute path of backup file on local server
     *
     * @return    bool|array        true is successful, array with error message if not
     */
    function google_drive_backup($args)
    {
        mwp_register_autoload_google();
        $googleClient = new Google_ApiClient();
        $googleClient->setAccessToken($args['google_drive_token']);

        $googleDrive = new Google_Service_Drive($googleClient);

        mwp_logger()->info('Fetching Google Drive root folder ID');
        try {
            $about        = $googleDrive->about->get();
            $rootFolderId = $about->getRootFolderId();
        } catch (Exception $e) {
            mwp_logger()->error('Error while fetching Google Drive root folder ID', array(
                'exception' => $e,
            ));

            return array(
                'error' => 'Error while fetching Google Drive root folder ID: '.$e->getMessage(),
            );
        }

        mwp_logger()->info('Loading Google Drive backup directory');
        try {
            $rootFiles = $googleDrive->files->listFiles(array("q" => "title='".addslashes($args['google_drive_directory'])."' and '$rootFolderId' in parents and trashed = false"));
        } catch (Exception $e) {
            mwp_logger()->error('Error while loading Google Drive backup directory', array(
                'exception' => $e,
            ));

            return array(
                'error' => 'Error while loading Google Drive backup directory: '.$e->getMessage(),
            );
        }

        if ($rootFiles->offsetExists(0)) {
            $backupFolder = $rootFiles->offsetGet(0);
        } else {
            try {
                mwp_logger()->info('Creating Google Drive backup directory');
                $newBackupFolder = new Google_Service_Drive_DriveFile();
                $newBackupFolder->setTitle($args['google_drive_directory']);
                $newBackupFolder->setMimeType('application/vnd.google-apps.folder');

                if ($rootFolderId) {
                    $parent = new Google_Service_Drive_ParentReference();
                    $parent->setId($rootFolderId);
                    $newBackupFolder->setParents(array($parent));
                }

                $backupFolder = $googleDrive->files->insert($newBackupFolder);
            } catch (Exception $e) {
                mwp_logger()->info('Error while creating Google Drive backup directory', array(
                    'exception' => $e,
                ));

                return array(
                    'error' => 'Error while creating Google Drive backup directory: '.$e->getMessage(),
                );
            }
        }

        if ($args['google_drive_site_folder']) {
            try {
                mwp_logger()->info('Fetching Google Drive site directory');
                $siteFolderTitle = $this->site_name;
                $backupFolderId  = $backupFolder->getId();
                $driveFiles      = $googleDrive->files->listFiles(array("q" => "title='".addslashes($siteFolderTitle)."' and '$backupFolderId' in parents and trashed = false"));
            } catch (Exception $e) {
                mwp_logger()->info('Error while fetching Google Drive site directory', array(
                    'exception' => $e,
                ));

                return array(
                    'error' => 'Error while fetching Google Drive site directory: '.$e->getMessage(),
                );
            }
            if ($driveFiles->offsetExists(0)) {
                $siteFolder = $driveFiles->offsetGet(0);
            } else {
                try {
                    mwp_logger()->info('Creating Google Drive site directory');
                    $_backup_folder = new Google_Service_Drive_DriveFile();
                    $_backup_folder->setTitle($siteFolderTitle);
                    $_backup_folder->setMimeType('application/vnd.google-apps.folder');

                    if (isset($backupFolder)) {
                        $_backup_folder->setParents(array($backupFolder));
                    }

                    $siteFolder = $googleDrive->files->insert($_backup_folder, array());
                } catch (Exception $e) {
                    mwp_logger()->info('Error while creating Google Drive site directory', array(
                        'exception' => $e,
                    ));

                    return array(
                        'error' => 'Error while creating Google Drive site directory: '.$e->getMessage(),
                    );
                }
            }
        } else {
            $siteFolder = $backupFolder;
        }

        $file_path  = explode('/', $args['backup_file']);
        $backupFile = new Google_Service_Drive_DriveFile();
        $backupFile->setTitle(end($file_path));
        $backupFile->setDescription('Backup file of site: '.$this->site_name.'.');

        if ($siteFolder != null) {
            $backupFile->setParents(array($siteFolder));
        }
        $googleClient->setDefer(true);
        // Deferred client returns request object.
        /** @var Google_Http_Request $request */
        $request   = $googleDrive->files->insert($backupFile);
        $chunkSize = 1024 * 1024 * 4;

        $media    = new Google_Http_MediaFileUpload($googleClient, $request, 'application/zip', null, true, $chunkSize);
        $fileSize = filesize($args['backup_file']);
        $media->setFileSize($fileSize);

        mwp_logger()->info('Uploading backup file to Google Drive; file size is {backup_size}', array(
            'backup_size' => mwp_format_bytes($fileSize),
        ));

        // Upload the various chunks. $status will be false until the process is
        // complete.
        $status           = false;
        $handle           = fopen($args['backup_file'], 'rb');
        $started          = microtime(true);
        $lastNotification = $started;
        $lastProgress     = 0;
        $threshold        = 1;
        $uploaded         = 0;
        $started          = microtime(true);
        while (!$status && !feof($handle)) {
            $chunk        = fread($handle, $chunkSize);
            $newChunkSize = strlen($chunk);

            if (($elapsed = microtime(true) - $lastNotification) > $threshold) {
                $lastNotification = microtime(true);
                mwp_logger()->info('Upload progress: {progress}% (speed: {speed}/s)', array(
                    'progress' => round($uploaded / $fileSize * 100, 2),
                    'speed'    => mwp_format_bytes(($uploaded - $lastProgress) / $elapsed),
                ));
                $lastProgress = $uploaded;
                echo ".";
                flush();
            }
            $uploaded += $newChunkSize;
            $status = $media->nextChunk($chunk);
        }
        fclose($handle);

        if (!$status instanceof Google_Service_Drive_DriveFile) {
            mwp_logger()->error('Upload to Google Drive failed', array(
                'status' => $status,
            ));

            return array(
                'error' => 'Upload to Google Drive was not successful.',
            );
        }

        mwp_logger()->info('Upload to Google Drive completed; average speed is {speed}/s', array(
            'speed' => mwp_format_bytes(round($fileSize / (microtime(true) - $started))),
        ));

        return true;
    }

    /**
     * Deletes backup file from Google Drive.
     *
     * @param    array $args arguments passed to the function
     *                       [google_drive_token] -> user's Google drive token in json form
     *                       [google_drive_directory] -> folder on user's Google Drive account which backup file should be deleted from
     *                       [google_drive_site_folder] -> subfolder with site name in google_drive_directory which backup file should be deleted from
     *                       [backup_file] -> absolute path of backup file on local server
     *
     * @return    void
     */
    function remove_google_drive_backup($args)
    {
        mwp_register_autoload_google();
        mwp_logger()->info('Removing Google Drive backup file', array(
            'google_drive_directory'   => $args['google_drive_directory'],
            'google_drive_site_folder' => $args['google_drive_site_folder'],
            'backup_file'              => $args['backup_file'],
        ));
        try {
            $googleClient = new Google_ApiClient();
            $googleClient->setAccessToken($args['google_drive_token']);
        } catch (Exception $e) {
            mwp_logger()->error('Google Drive file removal failed', array(
                'exception' => $e,
            ));

            return;
        }

        $driveService = new Google_Service_Drive($googleClient);

        mwp_logger()->info('Fetching Google Drive root folder ID');
        try {
            $about          = $driveService->about->get();
            $root_folder_id = $about->getRootFolderId();
        } catch (Exception $e) {
            mwp_logger()->info('Error fetching Google Drive root folder ID', array(
                'exception' => $e,
            ));

            return;
        }

        mwp_logger()->info('Listing Google Drive files');
        try {
            $listFiles = $driveService->files->listFiles(array("q" => "title='".addslashes($args['google_drive_directory'])."' and '$root_folder_id' in parents and trashed = false"));
            /** @var Google_Service_Drive_DriveFile[] $files */
            $files = $listFiles->getItems();
        } catch (Exception $e) {
            mwp_logger()->error('Error while listing Google Drive files', array(
                'exception' => $e,
            ));

            return;
        }
        if (isset($files[0])) {
            $managewpFolder = $files[0];
        } else {
            return;
        }

        if ($args['google_drive_site_folder']) {
            try {
                $subFolderTitle   = $this->site_name;
                $managewpFolderId = $managewpFolder->getId();
                $listFiles        = $driveService->files->listFiles(array("q" => "title='".addslashes($subFolderTitle)."' and '$managewpFolderId' in parents and trashed = false"));
                $files            = $listFiles->getItems();
            } catch (Exception $e) {
                $this->_log($e->getMessage());
                /*return array(
                    'error' => $e->getMessage(),
                );*/
            }
            if (isset($files[0])) {
                $backup_folder = $files[0];
            }
        } else {
            /** @var Google_Service_Drive_DriveFile $backup_folder */
            $backup_folder = $managewpFolder;
        }

        if (isset($backup_folder)) {
            try {
                $backup_folder_id = $backup_folder->getId();
                $listFiles        = $driveService->files->listFiles(array("q" => "title='".addslashes($args['backup_file'])."' and '$backup_folder_id' in parents and trashed = false"));
                $files            = $listFiles->getItems();;
            } catch (Exception $e) {
                $this->_log($e->getMessage());
                /*return array(
                    'error' => $e->getMessage(),
                );*/
            }
            if (isset($files[0])) {
                try {
                    $driveService->files->delete($files[0]->getId());
                } catch (Exception $e) {
                }
            } else {
            }
        } else {
        }
    }

    /**
     * Downloads backup file from Google Drive to root folder on local server.
     *
     * @param    array $args arguments passed to the function
     *                       [google_drive_token] -> user's Google drive token in json form
     *                       [google_drive_directory] -> folder on user's Google Drive account which backup file should be downloaded from
     *                       [google_drive_site_folder] -> subfolder with site name in google_drive_directory which backup file should be downloaded from
     *                       [backup_file] -> absolute path of backup file on local server
     *
     * @return    bool|array        absolute path to downloaded file is successful, array with error message if not
     */
    function get_google_drive_backup($args)
    {
        mwp_register_autoload_google();
        $googleClient = new Google_ApiClient();
        $googleClient->setAccessToken($args['google_drive_token']);
        $driveService = new Google_Service_Drive($googleClient);

        mwp_logger()->info('Connecting to Google Drive');
        try {
            $about        = $driveService->about->get();
            $rootFolderId = $about->getRootFolderId();
        } catch (Exception $e) {
            mwp_logger()->error('Error while connecting to Google Drive', array(
                'exception' => $e,
            ));

            return array(
                'error' => 'Error while connecting to Google Drive: '.$e->getMessage(),
            );
        }

        mwp_logger()->info('Looking for backup directory');
        try {
            $backupFolderFiles = $driveService->files->listFiles(array(
                'q' => sprintf("title='%s' and '%s' in parents and trashed = false", addslashes($args['google_drive_directory']), $rootFolderId),
            ));
        } catch (Exception $e) {
            mwp_logger()->error('Error while looking for backup directory', array(
                'exception' => $e,
            ));

            return array(
                'error' => 'Error while looking for backup directory: '.$e->getMessage(),
            );
        }

        if (!$backupFolderFiles->offsetExists(0)) {
            mwp_logger()->error('Backup directory ("{directory}") does not exist', array(
                'directory' => $args['google_drive_directory'],
            ));

            return array(
                'error' => sprintf("The backup directory (%s) does not exist.", $args['google_drive_directory']),
            );
        }

        /** @var Google_Service_Drive_DriveFile $backupFolder */
        $backupFolder = $backupFolderFiles->offsetGet(0);

        if ($args['google_drive_site_folder']) {
            mwp_logger()->info('Looking into the site folder');
            try {
                $siteFolderFiles = $driveService->files->listFiles(array(
                    'q' => sprintf("title='%s' and '%s' in parents and trashed = false", addslashes($this->site_name), $backupFolder->getId()),
                ));
            } catch (Exception $e) {
                mwp_logger()->error('Error while looking for the site folder', array(
                    'exception' => $e,
                ));

                return array(
                    'error' => 'Error while looking for the site folder: '.$e->getMessage(),
                );
            }

            if ($siteFolderFiles->offsetExists(0)) {
                $backupFolder = $siteFolderFiles->offsetGet(0);
            }
        }

        try {
            $backupFiles = $driveService->files->listFiles(array(
                'q' => sprintf("title='%s' and '%s' in parents and trashed = false", addslashes($args['backup_file']), $backupFolder->getId()),
            ));
        } catch (Exception $e) {
            mwp_logger()->error('Error while fetching Google Drive backup file', array(
                'file_name' => $args['backup_file'],
                'exception' => $e,
            ));

            return array(
                'error' => 'Error while fetching Google Drive backup file: '.$e->getMessage(),
            );
        }

        if (!$backupFiles->offsetExists(0)) {
            return array(
                'error' => sprintf('Backup file "%s" was not found on your Google Drive account.', $args['backup_file']),
            );
        }

        /** @var Google_Service_Drive_DriveFile $backupFile */
        $backupFile       = $backupFiles->offsetGet(0);
        $downloadUrl      = $backupFile->getDownloadUrl();
        $downloadLocation = ABSPATH.'mwp_temp_backup.zip';
        $fileSize         = $backupFile->getFileSize();
        $downloaded       = 0;
        $chunkSize        = 1024 * 1024 * 4;
        $fh               = fopen($downloadLocation, 'w+');

        if (!is_resource($fh)) {
            return array(
                'error' => 'Temporary backup download location is not writable (location: "%s").',
                $downloadLocation,
            );
        }
        while ($downloaded < $fileSize) {
            $request = new Google_Http_Request($downloadUrl);
            $googleClient->getAuth()->sign($request);
            $toDownload = min($chunkSize, $fileSize - $downloaded);
            mwp_logger()->info('Downloading: {downloaded}/{size}', array(
                'downloaded' => mwp_format_bytes($downloaded),
                'size'       => mwp_format_bytes($fileSize),
            ));
            $request->setRequestHeaders($request->getRequestHeaders() + array('Range' => 'bytes='.$downloaded.'-'.($downloaded + $toDownload - 1)));
            $googleClient->getIo()->makeRequest($request);
            if ($request->getResponseHttpCode() !== 206) {
                mwp_logger()->error('Google Drive has returned an invalid response', array(
                    'response_headers' => $request->getResponseHeaders(),
                    'response_body'    => $request->getResponseBody(),
                ));

                return array(
                    'error' => sprintf('Google Drive service has returned an invalid response code (%s)', $request->getResponseHttpCode()),
                );
            }
            fwrite($fh, $request->getResponseBody());
            $downloaded += $toDownload;
        }
        fclose($fh);

        $fileMd5 = md5_file($downloadLocation);
        if ($backupFile->getMd5Checksum() !== $fileMd5) {
            mwp_logger()->error('File checksum does not match, downloaded file is corrupted.', array(
                'original'   => $backupFile->getMd5Checksum(),
                'downloaded' => $fileMd5,
            ));

            return array(
                'error' => 'File downloaded was corrupted.',
            );
        }

        return $downloadLocation;
    }

    /**
     * Schedules the next execution of some backup task.
     *
     * @param    string $type     daily, weekly or monthly
     * @param    string $schedule format: task_time (if daily), task_time|task_day (if weekly), task_time|task_date (if monthly)
     *
     * @return    bool|int            timestamp if sucessful, false if not
     */
    function schedule_next($type, $schedule)
    {
        $schedule = explode("|", $schedule);

        if (empty($schedule)) {
            return false;
        }
        switch ($type) {
            case 'daily':
                if (isset($schedule[1]) && $schedule[1]) {
                    $delay_time = $schedule[1] * 60;
                }

                $current_hour  = date("H");
                $schedule_hour = $schedule[0];
                if ($current_hour >= $schedule_hour) {
                    $time = mktime($schedule_hour, 0, 0, date("m"), date("d") + 1, date("Y"));
                } else {
                    $time = mktime($schedule_hour, 0, 0, date("m"), date("d"), date("Y"));
                }
                break;

            case 'weekly':
                if (isset($schedule[2]) && $schedule[2]) {
                    $delay_time = $schedule[2] * 60;
                }
                $current_weekday  = date('w');
                $schedule_weekday = $schedule[1];
                $current_hour     = date("H");
                $schedule_hour    = $schedule[0];

                if ($current_weekday > $schedule_weekday) {
                    $weekday_offset = 7 - ($week_day - $task_schedule[1]);
                } else {
                    $weekday_offset = $schedule_weekday - $current_weekday;
                }

                if (!$weekday_offset) { //today is scheduled weekday
                    if ($current_hour >= $schedule_hour) {
                        $time = mktime($schedule_hour, 0, 0, date("m"), date("d") + 7, date("Y"));
                    } else {
                        $time = mktime($schedule_hour, 0, 0, date("m"), date("d"), date("Y"));
                    }
                } else {
                    $time = mktime($schedule_hour, 0, 0, date("m"), date("d") + $weekday_offset, date("Y"));
                }
                break;

            case 'monthly':
                if (isset($schedule[2]) && $schedule[2]) {
                    $delay_time = $schedule[2] * 60;
                }
                $current_monthday  = date('j');
                $schedule_monthday = $schedule[1];
                $current_hour      = date("H");
                $schedule_hour     = $schedule[0];

                if ($current_monthday > $schedule_monthday) {
                    $time = mktime($schedule_hour, 0, 0, date("m") + 1, $schedule_monthday, date("Y"));
                } else {
                    if ($current_monthday < $schedule_monthday) {
                        $time = mktime($schedule_hour, 0, 0, date("m"), $schedule_monthday, date("Y"));
                    } else {
                        if ($current_monthday == $schedule_monthday) {
                            if ($current_hour >= $schedule_hour) {
                                $time = mktime($schedule_hour, 0, 0, date("m") + 1, $schedule_monthday, date("Y"));
                            } else {
                                $time = mktime($schedule_hour, 0, 0, date("m"), $schedule_monthday, date("Y"));
                            }
                            break;
                        }
                    }
                }

                break;

            default:
                break;
        }

        if (isset($delay_time) && $delay_time) {
            $time += $delay_time;
        }

        return $time;
    }

    /**
     * Parse task arguments for info on master.
     *
     * @return mixed    associative array with stats for every backup task or error if backup is manually deleted on server
     */
    function get_backup_stats()
    {
        $stats = array();
        $tasks = $this->tasks;
        if (is_array($tasks) && !empty($tasks)) {
            foreach ($tasks as $task_name => $info) {
                if (!empty($info['task_results']) && is_array($info['task_results'])) {
                    foreach ($info['task_results'] as $key => $result) {
                        if (isset($result['server']) && !isset($result['error'])) {
                            if (isset($result['server']['file_path']) && !$info['task_args']['del_host_file']) {
                                if (!file_exists($result['server']['file_path'])) {
                                    $info['task_results'][$key]['error'] = 'Backup created but manually removed from server.';
                                }
                            }
                        }
                    }
                    $stats[$task_name] = $info['task_results'];
                }
            }
        }

        return $stats;
    }

    /**
     * Returns all backup tasks with information when the next schedule will be.
     *
     * @return    mixed    associative array with timestamp with next schedule for every backup task
     */
    function get_next_schedules()
    {
        $stats = array();
        $tasks = $this->tasks;
        if (is_array($tasks) && !empty($tasks)) {
            foreach ($tasks as $task_name => $info) {
                $stats[$task_name] = isset($info['task_args']['next']) ? $info['task_args']['next'] : array();
            }
        }

        return $stats;
    }

    /**
     * Deletes all old backups from local server.
     * It depends on configuration on master (Number of backups to keep).
     *
     * @param    string $task_name name of backup task
     *
     * @return    bool|void            true if there are backups for deletion, void if not
     */
    function remove_old_backups($task_name)
    {
        //Check for previous failed backups first
        $this->cleanup();

        //Remove by limit
        $backups = $this->tasks;
        if ($task_name == 'Backup Now') {
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
                if (isset($backups[$task_name]['task_results'][$i]['ftp']) && isset($backups[$task_name]['task_args']['account_info']['mwp_ftp'])) {
                    $ftp_file            = $backups[$task_name]['task_results'][$i]['ftp'];
                    $args                = $backups[$task_name]['task_args']['account_info']['mwp_ftp'];
                    $args['backup_file'] = $ftp_file;
                    $this->remove_ftp_backup($args);
                }
                if (isset($backups[$task_name]['task_results'][$i]['sftp']) && isset($backups[$task_name]['task_args']['account_info']['mwp_sftp'])) {
                    $sftp_file           = $backups[$task_name]['task_results'][$i]['sftp'];
                    $args                = $backups[$task_name]['task_args']['account_info']['mwp_sftp'];
                    $args['backup_file'] = $sftp_file;
                    $this->remove_sftp_backup($args);
                }

                if (isset($backups[$task_name]['task_results'][$i]['amazons3']) && isset($backups[$task_name]['task_args']['account_info']['mwp_amazon_s3'])) {
                    $amazons3_file       = $backups[$task_name]['task_results'][$i]['amazons3'];
                    $args                = $backups[$task_name]['task_args']['account_info']['mwp_amazon_s3'];
                    $args['backup_file'] = $amazons3_file;
                    $this->remove_amazons3_backup($args);
                }

                if (isset($backups[$task_name]['task_results'][$i]['dropbox']) && isset($backups[$task_name]['task_args']['account_info']['mwp_dropbox'])) {
                    //To do: dropbox remove
                    $dropbox_file        = $backups[$task_name]['task_results'][$i]['dropbox'];
                    $args                = $backups[$task_name]['task_args']['account_info']['mwp_dropbox'];
                    $args['backup_file'] = $dropbox_file;
                    $this->remove_dropbox_backup($args);
                }

                if (isset($backups[$task_name]['task_results'][$i]['google_drive']) && isset($backups[$task_name]['task_args']['account_info']['mwp_google_drive'])) {
                    $google_drive_file   = $backups[$task_name]['task_results'][$i]['google_drive'];
                    $args                = $backups[$task_name]['task_args']['account_info']['mwp_google_drive'];
                    $args['backup_file'] = $google_drive_file;
                    $this->remove_google_drive_backup($args);
                }

                //Remove database backup info
                unset($backups[$task_name]['task_results'][$i]);
            } //end foreach

            if (is_array($backups[$task_name]['task_results'])) {
                $backups[$task_name]['task_results'] = array_values($backups[$task_name]['task_results']);
            } else {
                $backups[$task_name]['task_results'] = array();
            }

            $this->update_tasks($backups);

            return true;
        }
    }

    /**
     * Deletes specified backup.
     *
     * @param    array $args arguments passed to function
     *                       [task_name] -> name of backup task
     *                       [result_id] -> id of baskup task result, which should be restored
     *                       [google_drive_token] -> json of Google Drive token, if it is remote destination
     *
     * @return    bool            true if successful, false if not
     */
    function delete_backup($args)
    {
        if (empty($args)) {
            return false;
        }
        extract($args);
        $task_name = stripslashes($task_name);
        if (isset($google_drive_token)) {
            $this->tasks[$task_name]['task_args']['account_info']['mwp_google_drive']['google_drive_token'] = $google_drive_token;
        }

        $tasks = $this->tasks;

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
        if (isset($backup['sftp'])) {
            $sftp_file           = $backup['sftp'];
            $args                = $tasks[$task_name]['task_args']['account_info']['mwp_sftp'];
            $args['backup_file'] = $sftp_file;
            $this->remove_sftp_backup($args);
        }

        if (isset($backup['amazons3'])) {
            $amazons3_file       = $backup['amazons3'];
            $args                = $tasks[$task_name]['task_args']['account_info']['mwp_amazon_s3'];
            $args['backup_file'] = $amazons3_file;
            $this->remove_amazons3_backup($args);
        }

        if (isset($backup['dropbox'])) {
            $dropbox_file        = $backup['dropbox'];
            $args                = $tasks[$task_name]['task_args']['account_info']['mwp_dropbox'];
            $args['backup_file'] = $dropbox_file;
            $this->remove_dropbox_backup($args);
        }

        if (isset($backup['google_drive'])) {
            $google_drive_file   = $backup['google_drive'];
            $args                = $tasks[$task_name]['task_args']['account_info']['mwp_google_drive'];
            $args['backup_file'] = $google_drive_file;
            $this->remove_google_drive_backup($args);
        }

        unset($backups[$result_id]);

        if (count($backups)) {
            $tasks[$task_name]['task_results'] = $backups;
        } else {
            unset($tasks[$task_name]['task_results']);
        }

        $this->update_tasks($tasks);

        //update_option('mwp_backup_tasks', $tasks);
        return true;
    }

    /**
     * Deletes all unneeded files produced by backup process.
     *
     * @return    array    array of deleted files
     */
    function cleanup()
    {
        $tasks             = $this->tasks;
        $backup_folder     = WP_CONTENT_DIR.'/'.md5('mmb-worker').'/mwp_backups/';
        $backup_folder_new = MWP_BACKUP_DIR.'/';
        $files             = glob($backup_folder."*");
        $new               = glob($backup_folder_new."*");

        //Failed db files first
        $db_folder = MWP_DB_DIR.'/';
        $db_files  = glob($db_folder."*");
        if (is_array($db_files) && !empty($db_files)) {
            foreach ($db_files as $file) {
                @unlink($file);
            }
            @unlink(MWP_BACKUP_DIR.'/mwp_db/index.php');
            @unlink(MWP_BACKUP_DIR.'/mwp_db/info.json');
            @rmdir(MWP_DB_DIR);
        }

        //clean_old folder?
        if ((isset($files[0]) && basename($files[0]) == 'index.php' && count($files) == 1) || (empty($files))) {
            if (!empty($files)) {
                foreach ($files as $file) {
                    @unlink($file);
                }
            }
            @rmdir(WP_CONTENT_DIR.'/'.md5('mmb-worker').'/mwp_backups');
            @rmdir(WP_CONTENT_DIR.'/'.md5('mmb-worker'));
        }

        if (!empty($new)) {
            foreach ($new as $b) {
                $files[] = $b;
            }
        }
        $deleted = array();

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
                if (!in_array($file, $results) && basename($file) != 'index.php') {
                    @unlink($file);
                    $deleted[] = basename($file);
                    $num_deleted++;
                }
            }
        }

        return $deleted;
    }

    /**
     * Uploads to remote destination in the second step, invoked from master.
     *
     * @param    array $args arguments passed to function
     *                       [task_name] -> name of backup task
     *
     * @return    array|void        void if success, array with error message if not
     */
    function remote_backup_now($args)
    {
        /**
         * Remember if this is called as a forked http request, or a connection to the dasboard is persistent
         */
        global $forkedRequest;
        $forkedRequest = isset($args['forked']) ? $args['forked'] : false;

        $this->set_memory();
        if (!empty($args)) {
            extract($args);
        }


        $tasks     = $this->tasks;
        $task_name = stripslashes($task_name);
        $task      = $tasks[$task_name];

        if (!empty($task)) {
            extract($task['task_args']);
        }

        $results = $task['task_results'];

        if (is_array($results) && count($results)) {
            $backup_file = $results[count($results) - 1]['server']['file_path'];
        }

        if ($backup_file && file_exists($backup_file)) {
            //FTP, Amazon S3, Dropbox or Google Drive
            if (isset($account_info['mwp_ftp']) && !empty($account_info['mwp_ftp'])) {
                $this->update_status($task_name, $this->statuses['ftp']);
                $account_info['mwp_ftp']['backup_file'] = $backup_file;
                $return                                 = $this->ftp_backup($account_info['mwp_ftp']);
                $this->wpdb_reconnect();

                if (!(is_array($return) && isset($return['error']))) {
                    $this->update_status($task_name, $this->statuses['ftp'], true);
                    $this->update_status($task_name, $this->statuses['finished'], true);
                }
            }

            if (isset($account_info['mwp_sftp']) && !empty($account_info['mwp_sftp'])) {
                $this->update_status($task_name, $this->statuses['sftp']);
                $account_info['mwp_sftp']['backup_file'] = $backup_file;
                $return                                  = $this->sftp_backup($account_info['mwp_sftp']);
                $this->wpdb_reconnect();

                if (!(is_array($return) && isset($return['error']))) {
                    $this->update_status($task_name, $this->statuses['sftp'], true);
                    $this->update_status($task_name, $this->statuses['finished'], true);
                }
            }

            if (isset($account_info['mwp_amazon_s3']) && !empty($account_info['mwp_amazon_s3'])) {
                $this->update_status($task_name, $this->statuses['s3']);
                $account_info['mwp_amazon_s3']['backup_file'] = $backup_file;
                $return                                       = $this->amazons3_backup($account_info['mwp_amazon_s3']);
                $this->wpdb_reconnect();

                if (!(is_array($return) && isset($return['error']))) {
                    $this->update_status($task_name, $this->statuses['s3'], true);
                    $this->update_status($task_name, $this->statuses['finished'], true);
                }
            }

            if (isset($account_info['mwp_dropbox']) && !empty($account_info['mwp_dropbox'])) {
                $this->update_status($task_name, $this->statuses['dropbox']);
                $account_info['mwp_dropbox']['backup_file'] = $backup_file;
                $return                                     = $this->dropbox_backup($account_info['mwp_dropbox']);
                $this->wpdb_reconnect();

                if (!(is_array($return) && isset($return['error']))) {
                    $this->update_status($task_name, $this->statuses['dropbox'], true);
                    $this->update_status($task_name, $this->statuses['finished'], true);
                }
            }

            if (isset($account_info['mwp_email']) && !empty($account_info['mwp_email'])) {
                $this->update_status($task_name, $this->statuses['email']);
                $account_info['mwp_email']['task_name'] = $task_name;
                $account_info['mwp_email']['file_path'] = $backup_file;
                $return                                 = $this->email_backup($account_info['mwp_email']);
                $this->wpdb_reconnect();

                if (!(is_array($return) && isset($return['error']))) {
                    $this->update_status($task_name, $this->statuses['email'], true);
                    $this->update_status($task_name, $this->statuses['finished'], true);
                }
            }

            if (isset($account_info['mwp_google_drive']) && !empty($account_info['mwp_google_drive'])) {
                $this->update_status($task_name, $this->statuses['google_drive']);
                $account_info['mwp_google_drive']['backup_file'] = $backup_file;
                $return                                          = $this->google_drive_backup($account_info['mwp_google_drive']);
                $this->wpdb_reconnect();

                if (!(is_array($return) && isset($return['error']))) {
                    $this->update_status($task_name, $this->statuses['google_drive'], true);
                    $this->update_status($task_name, $this->statuses['finished'], true);
                }
            }

            $tasks = $this->tasks;
            @file_put_contents(MWP_BACKUP_DIR.'/mwp_db/index.php', '');
            if ($return == true && $del_host_file) {
                @unlink($backup_file);
                unset($tasks[$task_name]['task_results'][count($tasks[$task_name]['task_results']) - 1]['server']);
            }
            $this->update_tasks($tasks);
        } else {
            $return = array(
                'error' => 'Backup file not found on your server. Please try again.'
            );
        }
        $this->sendDataToMaster();

        return $return;
    }

    /**
     * Checks if scheduled backup tasks should be executed.
     *
     * @param    array  $args arguments passed to function
     *                        [task_name] -> name of backup task
     *                        [task_id] -> id of backup task
     *                        [$site_key] -> hash key of backup task
     *                        [worker_version] -> version of worker
     *                        [mwp_google_drive_refresh_token] ->    should be Google Drive token be refreshed, true if it is remote destination of task
     * @param    string $url  url on master where worker validate task
     *
     * @return    string|array|boolean
     */
    function validate_task($args, $url)
    {
        if (!class_exists('WP_Http')) {
            include_once(ABSPATH.WPINC.'/class-http.php');
        }

        $worker_upto_3_9_22 = ($GLOBALS['MMB_WORKER_VERSION'] <= '3.9.22'); // worker version is less or equals to 3.9.22
        $params             = array('timeout' => 100);
        $params['body']     = $args;
        $result             = wp_remote_post($url, $params);

        if ($worker_upto_3_9_22) {
            if (is_array($result) && $result['body'] == 'mwp_delete_task') {
                //$tasks = $this->get_backup_settings();
                $tasks = $this->tasks;
                unset($tasks[$args['task_name']]);
                $this->update_tasks($tasks);
                $this->cleanup();

                return 'deleted';
            } elseif (is_array($result) && $result['body'] == 'mwp_pause_task') {
                return 'paused';
            } elseif (is_array($result) && substr($result['body'], 0, 8) == 'token - ') {
                return $result['body'];
            }
        } else {
            if (is_array($result) && $result['body']) {
                $response = unserialize($result['body']);
                if ($response['message'] == 'mwp_delete_task') {
                    $tasks = $this->tasks;
                    unset($tasks[$args['task_name']]);
                    $this->update_tasks($tasks);
                    $this->cleanup();

                    return 'deleted';
                } elseif ($response['message'] == 'mwp_pause_task') {
                    return 'paused';
                } elseif ($response['message'] == 'mwp_do_task') {
                    return $response;
                }
            }
        }

        return false;
    }

    /**
     * Updates status of backup task.
     * Positive number if completed, negative if not.
     *
     * @param    string $task_name name of backup task
     * @param    int    $status    status which tasks should be updated to
     *                             (
     *                             0 - Backup started,
     *                             1 - DB dump,
     *                             2 - DB ZIP,
     *                             3 - Files ZIP,
     *                             4 - Amazon S3,
     *                             5 - Dropbox,
     *                             6 - FTP,
     *                             7 - Email,
     *                             8 - Google Drive,
     *                             100 - Finished
     *                             )
     * @param    bool   $completed completed or not
     *
     * @return    void
     */
    function update_status($task_name, $status, $completed = false)
    {
        if ($task_name != 'Backup Now') {
            $tasks = $this->tasks;
            $index = count($tasks[$task_name]['task_results']) - 1;
            if (!is_array($tasks[$task_name]['task_results'][$index]['status'])) {
                $tasks[$task_name]['task_results'][$index]['status'] = array();
            }
            if (!$completed) {
                $tasks[$task_name]['task_results'][$index]['status'][] = (int) $status * (-1);
            } else {
                $status_index                                                       = count($tasks[$task_name]['task_results'][$index]['status']) - 1;
                $tasks[$task_name]['task_results'][$index]['status'][$status_index] = abs($tasks[$task_name]['task_results'][$index]['status'][$status_index]);
            }

            $this->update_tasks($tasks);
            //update_option('mwp_backup_tasks',$tasks);
        }
    }

    /**
     * Update $this->tasks attribute and save it to wp_options with key mwp_backup_tasks.
     *
     * @param    mixed $tasks associative array with all tasks data
     *
     * @return    void
     */
    function update_tasks($tasks)
    {
        $this->tasks = $tasks;
        update_option('mwp_backup_tasks', $tasks);
    }

    /**
     * Reconnects to database to avoid timeout problem after ZIP files.
     *
     * @return void
     */
    function wpdb_reconnect()
    {
        global $wpdb;

        if (class_exists('wpdb') && function_exists('wp_set_wpdb_vars')) {
            @mysql_close($wpdb->dbh);
            $wpdb = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
            wp_set_wpdb_vars();
            if (function_exists('is_multisite')) {
                if (is_multisite()) {
                    $wpdb->set_blog_id(get_current_blog_id());
                }
            }
        }
    }

    /**
     * Replaces .htaccess file in process of restoring WordPress site.
     *
     * @param    string $url url of current site
     *
     * @return    void
     */
    function replace_htaccess($url)
    {
        $file = @file_get_contents(ABSPATH.'.htaccess');
        if ($file && strlen($file)) {
            $args    = parse_url($url);
            $string  = rtrim($args['path'], "/");
            $regex   = "/BEGIN WordPress(.*?)RewriteBase(.*?)\n(.*?)RewriteRule \.(.*?)index\.php(.*?)END WordPress/sm";
            $replace = "BEGIN WordPress$1RewriteBase ".$string."/ \n$3RewriteRule . ".$string."/index.php$5END WordPress";
            $file    = preg_replace($regex, $replace, $file);
            @file_put_contents(ABSPATH.'.htaccess', $file);
        }
    }

    /**
     * Removes cron for checking scheduled tasks, if there are not any scheduled task.
     *
     * @return    void
     */
    function check_cron_remove()
    {
        if (empty($this->tasks) || (count($this->tasks) == 1 && isset($this->tasks['Backup Now']))) {
            wp_clear_scheduled_hook('mwp_backup_tasks');
            exit;
        }
    }

    /**
     * Re-add tasks on website re-add.
     *
     * @param    array $params arguments passed to function
     *
     * @return    array            $params without backups
     */
    public function readd_tasks($params = array())
    {
        global $mmb_core;

        if (empty($params) || !isset($params['backups'])) {
            return $params;
        }

        $before = array();
        $tasks  = $params['backups'];
        if (!empty($tasks)) {
            $mmb_backup = new MMB_Backup();

            if (function_exists('wp_next_scheduled')) {
                /*if (!wp_next_scheduled('mwp_backup_tasks')) {
                    wp_schedule_event(time(), 'tenminutes', 'mwp_backup_tasks');
                }*/
            }

            foreach ($tasks as $task) {
                $before[$task['task_name']] = array();

                if (isset($task['secure'])) {
                    if (is_array($task['secure'])) {
                        $secureParams = $task['secure'];
                        foreach ($secureParams as $key => $value) {
                            $secureParams[$key] = base64_decode($value);
                        }
                        $task['secure'] = $secureParams;
                    } else {
                        $task['secure'] = base64_decode($task['secure']);
                    }
                    if ($decrypted = $mmb_core->_secure_data($task['secure'])) {
                        $decrypted = maybe_unserialize($decrypted);
                        if (is_array($decrypted)) {
                            foreach ($decrypted as $key => $val) {
                                if (!is_numeric($key)) {
                                    $task[$key] = $val;
                                }
                            }
                            unset($task['secure']);
                        } else {
                            $task['secure'] = $decrypted;
                        }
                    }
                    if (!$decrypted && $mmb_core->get_random_signature() !== false) {
                        $cipher = new Crypt_AES(CRYPT_AES_MODE_ECB);
                        $cipher->setKey($mmb_core->get_random_signature());
                        $decrypted            = $cipher->decrypt($task['secure']);
                        $task['account_info'] = json_decode($decrypted, true);
                    }

                }
                if (isset($task['account_info']) && is_array($task['account_info'])) { //only if sends from master first time(secure data)
                    $task['args']['account_info'] = $task['account_info'];
                }

                $before[$task['task_name']]['task_args']         = $task['args'];
                $before[$task['task_name']]['task_args']['next'] = $mmb_backup->schedule_next($task['args']['type'], $task['args']['schedule']);
            }
        }
        update_option('mwp_backup_tasks', $before);

        unset($params['backups']);

        return $params;
    }

    /**
     * Start backup process. Invoked from remote ping
     *
     * @param    array $args arguments passed to function
     *                       [task_name] -> name of backup task
     *
     * @return    array      array with error message or if success task results
     */
    public function ping_backup($args)
    {
        // Belows code follows logic from check_backup
        $return    = "PONG";
        $task_name = $args['task_name'];
        $sendDataToMaster = false;
        if (is_array($this->tasks) && !empty($this->tasks) && !empty($this->tasks[$task_name])) {
            $task = $this->tasks[$task_name];
            $sendDataToMaster = isset($task['task_args']['account_info']) ? false : true;
            if ($task['task_args']['task_id'] && $task['task_args']['site_key']) {
                $potential_token = !empty($args['google_drive_token']) ? $args['google_drive_token'] : false;
                if ($potential_token) {
                    $this->tasks[$task_name]['task_args']['account_info']['mwp_google_drive']['google_drive_token'] = $potential_token;
                    $task['task_args']['account_info']['mwp_google_drive']['google_drive_token']                    = $potential_token;
                }
                /**
                 * From this point I am simulating previous way of working. In order to fix this,
                 * I need to refactor greater part, and release is tomorrow morning
                 */
                $update         = array(
                    'task_name' => $task_name,
                    'args'      => $task['task_args']
                );
                $update['time'] = time();
                $this->set_backup_task($update);
                $this->tasks = get_option('mwp_backup_tasks');
                $task        = $this->tasks[$task_name];

                $result = $this->backup($task['task_args'], $task_name);

                if (is_array($result) && array_key_exists('error', $result)) {
                    $return = $result;
                    $this->set_backup_task(
                        array(
                            'task_name' => $task_name,
                            'args'      => $task['task_args'],
                            'error'     => $return
                        )
                    );
                } else {
                    if (!empty($task['task_args']['account_info'])) {
                        $this->mwp_remote_upload($task_name);
                    }
                    $return = $this->tasks[$task_name]['task_results'];
                }


            }
        } else {
            $return = array("error" => "Unknown task name");
        }
        if ($sendDataToMaster) {
            $this->sendDataToMaster();
        }
        return $return;
    }

    public function sendDataToMaster()
    {
        $this->notifyMyself('mwp_datasend');
    }

    public function mwp_remote_upload($task_name)
    {
        $backup_file   = $this->tasks[$task_name]['task_results'][count($this->tasks[$task_name]['task_results']) - 1]['server']['file_url'];
        $del_host_file = $this->tasks[$task_name]['task_args']['del_host_file'];
        $args = array('task_name' => $task_name, 'backup_file' => $backup_file, 'del_host_file' => $del_host_file);

        $this->notifyMyself('mmb_remote_upload', $args);
    }

}

/*if( function_exists('add_filter') ) {
	add_filter( 'mwp_website_add', 'MMB_Backup::readd_tasks' );
}*/

if (!function_exists('get_all_files_from_dir')) {
    /**
     * Get all files in directory
     *
     * @param    string $path    Relative or absolute path to folder
     * @param    array  $exclude List of excluded files or folders, relative to $path
     *
     * @return    array                List of all files in folder $path, exclude all files in $exclude array
     */
    function get_all_files_from_dir($path, $exclude = array())
    {
        if ($path[strlen($path) - 1] === "/") {
            $path = substr($path, 0, -1);
        }
        global $directory_tree, $ignore_array;
        $directory_tree = array();
        foreach ($exclude as $file) {
            if (!in_array($file, array('.', '..'))) {
                if ($file[0] === "/") {
                    $path = substr($file, 1);
                }
                $ignore_array[] = "$path/$file";
            }
        }
        get_all_files_from_dir_recursive($path);

        return $directory_tree;
    }
}

if (!function_exists('get_all_files_from_dir_recursive')) {
    /**
     * Get all files in directory,
     * wrapped function which writes in global variable
     * and exclued files or folders are read from global variable
     *
     * @param    string $path Relative or absolute path to folder
     *
     * @return    void
     */
    function get_all_files_from_dir_recursive($path)
    {
        if ($path[strlen($path) - 1] === "/") {
            $path = substr($path, 0, -1);
        }
        global $directory_tree, $ignore_array;
        $directory_tree_temp = array();
        $dh                  = @opendir($path);

        while (false !== ($file = @readdir($dh))) {
            if (!in_array($file, array('.', '..'))) {
                if (empty($ignore_array) || !in_array("$path/$file", $ignore_array)) {
                    if (!is_dir("$path/$file")) {
                        $directory_tree[] = "$path/$file";
                    } else {
                        get_all_files_from_dir_recursive("$path/$file");
                    }
                }
            }
        }
        @closedir($dh);
    }
}

/**
 * Retrieves a value from an array by key, or a specified default if given key doesn't exist
 *
 * @param array $array
 * @param       $key
 * @param null  $default
 *
 * @return mixed
 */
function getKey($key, array $array, $default = null)
{
    return array_key_exists($key, $array) ? $array[$key] : $default;
}

function recursiveUrlReplacement(&$value, $index, $data)
{
    if (is_string($value)) {
        if (is_string($data['regex'])) {
            $expressions = array($data['regex']);
        } else if (is_array($data['regex'])) {
            $expressions = $data['regex'];
        } else {
            return;
        }

        foreach ($expressions as $exp) {
            $value = preg_replace($exp, $data['newUrl'], $value);
        }
    }
}

/**
 * This should mirror database replacements in cloner.php
 */
function restore_migrate_urls()
{
    // ----- DATABASE REPLACEMENTS

    /**
     * Finds all urls that begin with $oldSiteUrl AND
     * end either with OPTIONAL slash OR with MANDATORY slash following any number of any characters
     */

    //     Get all options that contain old urls, then check if we can replace them safely
    // Now check for old urls without WWW
    global $restoreParams, $wpdb;
    $oldSiteUrl  = $restoreParams['oldSiteUrl'];
    $oldUrl      = $restoreParams['oldUrl'];
    $tablePrefix = $restoreParams['tablePrefix'];
    $newUrl      = $restoreParams['newUrl'];

    if(!isset($oldSiteUrl) || !isset($oldUrl)){
        return false;
    }

    $parsedOldSiteUrl      = parse_url(strpos($oldSiteUrl, '://') === false ? "http://$oldSiteUrl" : $oldSiteUrl);
    $parsedOldUrl          = parse_url(strpos($oldUrl, '://') === false ? "http://$oldUrl" : $oldUrl);
    $host                  = getKey('host', $parsedOldSiteUrl, '');
    $path                  = getKey('path', $parsedOldSiteUrl, '');
    $oldSiteUrlNoWww       = preg_replace('#^www\.(.+\.)#i', '$1', $host) . $path;
    $parsedOldSiteUrlNoWww = parse_url(strpos($oldSiteUrlNoWww, '://') === false
            ? "http://$oldSiteUrlNoWww"
            : $oldSiteUrlNoWww
    );
    if (isset($parse['scheme'])) {
        $oldSiteUrlNoWww = "{$parse['scheme']}://$oldSiteUrlNoWww";
    }

    // Modify the database for two variants of url, one with and one without WWW
    $oldUrls = array('oldSiteUrl' => $oldSiteUrl);
    $tmp1 = @"{$parsedOldUrl['host']}/{$parsedOldUrl['path']}";
    $tmp2 = @"{$parsedOldSiteUrlNoWww['host']}/{$parsedOldSiteUrlNoWww['path']}";
    if ($oldSiteUrlNoWww != $oldSiteUrl && $tmp1 != $tmp2) {
        $oldUrls['oldSiteUrlNoWww'] = $oldSiteUrlNoWww;
    }
    if (strpos($oldSiteUrl, $oldUrl
        ) !== false && $oldSiteUrl != $oldUrl && $parsedOldUrl['host'] != $parsedOldSiteUrl['host']
    ) {
        $oldUrls['oldUrl'] = $oldUrl;
    }
    foreach ($oldUrls as $key => $url) {
        if (empty($url) || strlen($url) <= 1) {
            continue;
        }

        if ($key == 'oldSiteUrlNoWww') {
            $amazingRegex = "~http://{$url}(?=(((/.*)+)|(/?$)))~";
        } else {
            $amazingRegex = "~{$url}(?=(((/.*)+)|(/?$)))~";
        }
        // Check options
        $query     = "SELECT option_id, option_value FROM {$tablePrefix}options WHERE option_value LIKE '%{$url}%';";
        $selection = $wpdb->get_results($query, ARRAY_A);
        foreach ($selection as $row) {
            // Set a default value untouched
            $replaced = $row['option_value'];

            if (is_serialized($row['option_value'])) {
                $unserialized = unserialize($row['option_value']);
                if (is_array($unserialized)) {
                    array_walk_recursive($unserialized, 'recursiveUrlReplacement', array(
                            'newUrl' => $newUrl,
                            'regex'  => $amazingRegex
                        )
                    );
                    $replaced = serialize($unserialized);
                }
            } else {
                $replaced = preg_replace($amazingRegex, $newUrl, $replaced);
            }

            $escapedReplacement = $wpdb->_escape($replaced);

            $optId = $row['option_id'];
            if ($row['option_value'] != $replaced) {
                $query = "UPDATE {$tablePrefix}options SET option_value = '{$escapedReplacement}' WHERE option_id = {$optId}";
                $wpdb->query($query);
            }
        }

        // Check post meta
        $query     = "SELECT meta_id, meta_value FROM {$tablePrefix}postmeta WHERE meta_value LIKE '%{$url}%'";
        $selection = $wpdb->get_results($query, ARRAY_A);
        foreach ($selection as $row) {
            $replacement = $row['meta_value'];
            if (is_serialized($replacement)) {
                $unserialized = unserialize($replacement);
                if (is_array($unserialized)) {
                    array_walk_recursive($unserialized, 'recursiveUrlReplacement', array(
                            'newUrl' => $newUrl,
                            'regex'  => $amazingRegex
                        )
                    );
                }
                $replacement = serialize($unserialized);
            } else {
                $replacement = preg_replace($amazingRegex, $newUrl, $replacement);
            }

            if ($replacement != $row['meta_value']) {
                $escapedReplacement = $wpdb->_escape($replacement);
                $id                 = $row['meta_id'];
                $query              = "UPDATE {$tablePrefix}postmeta SET meta_value = '{$escapedReplacement}' WHERE meta_id = '$id'";
                $wpdb->query($query);
            }

        }

        // Do the same with posts
        $query     = "SELECT ID, post_content, guid FROM {$tablePrefix}posts WHERE post_content LIKE '%{$url}%' OR guid LIKE '%{$url}%'";
        $selection = $wpdb->get_results($query, ARRAY_A);
        foreach ($selection as &$row) {
            $postContent = preg_replace($amazingRegex, $newUrl, $row['post_content']);
            $guid        = preg_replace($amazingRegex, $newUrl, $row['guid']);


            if ($postContent != $row['post_content'] || $guid != $row['guid']) {
                $postContent = $wpdb->_escape($postContent);
                $guid        = $wpdb->_escape($guid);
                $postId      = $row['ID'];
                $q           = "UPDATE {$tablePrefix}posts SET post_content = '$postContent', guid = '$guid' WHERE ID = {$postId}";
                $wpdb->query($q);
            }
        }
    }
}

function restore_htaccess()
{
    $htaccessRealpath = realpath(ABSPATH . '.htaccess');

    if ($htaccessRealpath) {
        @rename($htaccessRealpath, "$htaccessRealpath.old");
    }
    @include(ABSPATH . 'wp-admin/includes/admin.php');
    @flush_rewrite_rules(true);
}
