<?php

class Mmb_Core extends Mmb_Helper
{
    var $name;
    var $slug;
    var $settings;
    var $remote_client;
    var $comment_instance;
    var $plugin_instance;
    var $theme_instance;
    var $category_instance;
    var $wp_instance;
    var $page_instance;
    var $post_instance;
    var $stats_instance;
    var $user_instance;
    var $tag_instance;
    var $backup_instance;
//    var $ende_instance;
//    protected $secret_key;
    
    function __construct(){
        global $mmb_plugin_dir;
//        get_option();
//        $this->secret_key = trim(get_option('siteurl'), ' /');
        $this->name = 'Manage Multiple Blogs';
        $this->slug = 'manage-multiple-blogs';
        $this->settings = get_option($this->slug);
//        $this->ende_instance = new Mmb_EnDe($this->secret_key);
        if (!$this->settings)
        {
            $this->settings = array(
                'blogs'         => array(),
                'current_blog'  => array(
                    'type'          => null,
                ),
            );
        }
        
        add_action('rightnow_end', array($this, 'add_right_now_info'));
        add_action('wp_footer', array('Mmb_Stats', 'set_hit_count'));
//        add_action('xmlrpc_call', array($this, 'extend_xmlrpc_methods'), 0, 1);
        add_filter('xmlrpc_methods', array($this, 'add_xmlrpc_methods'));
        register_activation_hook($mmb_plugin_dir.'/init.php', array($this, 'install'));
        add_action('init', array($this, 'mmb_test_fix'));
	}	

    function mmb_test_fix() {
            
    }
    
    /**
    * Add an item into the Right Now Dashboard widget 
    * to inform that the blog can be managed remotely
    * 
    */
    function add_right_now_info()
    {
        echo '<div class="mmb-slave-info">
            <p>This site can be managed remotely.</p>
        </div>';
    }
    
    /**
    * Add custom XMLRPC methods to fit our needs
    * This function should only be called if the current blog is a Slave
    * 
    * @param mixed $methods
    */
    function add_xmlrpc_methods($methods)
    {

        $methods['mmbUpgradeWorker'] = 'mmb_worker_upgrade';
        // stats
        $methods['mmbGetStats'] = 'mmb_stats_get';
        $methods['mmbGetServerStatus'] = 'mmb_stats_server_get';
        $methods['mmbGetUserHitStats'] = 'mmb_stats_hit_count_get';
        
        // plugins
        $methods['mmbGetPluginList'] = 'mmb_plugin_get_list';
        $methods['mmbActivatePlugin'] = 'mmb_plugin_activate';
        $methods['mmbDeactivatePlugin'] = 'mmb_plugin_deactivate';
        $methods['mmbUpgradePlugin'] = 'mmb_plugin_upgrade';
        $methods['mmbUpgradePlugins'] = 'mmb_plugin_upgrade_multiple';
        $methods['mmbUpgradeAllPlugins'] = 'mmb_plugin_upgrade_all';
        $methods['mmbDeletePlugin'] = 'mmb_plugin_delete';
        $methods['mmbInstallPlugin'] = 'mmb_plugin_install';
        $methods['mmbUploadPluginByURL'] = 'mmb_plugin_upload_by_url';
        
        //themes
        $methods['mmbGetThemeList'] = 'mmb_theme_get_list';
        $methods['mmbActivateTheme'] = 'mmb_theme_activate';
        $methods['mmbDeleteTheme'] = 'mmb_theme_delete';
        $methods['mmbInstallTheme'] = 'mmb_theme_install';
        $methods['mmbUpgradeTheme'] = 'mmb_theme_upgrade';
        $methods['mmbUpgradeThemes'] = 'mmb_themes_upgrade';
        $methods['mmbUploadThemeByURL'] = 'mmb_theme_upload_by_url';
        
        // wordpress update
        $methods['mmbWPCheckVersion'] = 'mmb_wp_checkversion';
        $methods['mmbWPUpgrade'] = 'mmb_wp_upgrade';
        $methods['mmbWPGetUpdates'] = 'mmb_wp_get_updates';
        
        // categories
        // native XMLRPC method to get category list is not good enough
        // so we make our own
        $methods['mmbGetCategoryList'] = 'mmb_cat_get_list';
        $methods['mmbUpdateCategory'] = 'mmb_cat_update';
        $methods['mmbAddCategory'] = 'mmb_cat_add';
        // (category deleting can be handled well by native XMLRPC method)

        //tags by ashish
        $methods['mmbGetTagList'] = 'mmb_tag_get_list';
        $methods['mmbUpdateTag'] = 'mmb_tag_update';
        $methods['mmbAddTag'] = 'mmb_tag_add'; 
        $methods['mmbDeleteTag'] = 'mmb_tag_delete';


        // pages
        $methods['mmbGetPageEditData'] = 'mmb_page_get_edit_data';
        $methods['mmbGetPageNewData'] = 'mmb_page_get_new_data';
        $methods['mmbUpdatePage'] = 'mmb_page_update';
        $methods['mmbCreatePage'] = 'mmb_page_create';
        
        // posts
        $methods['mmbGetPostList'] = 'mmb_post_get_list';
        $methods['mmbGetPostNewData'] = 'mmb_post_get_new_data';
        $methods['mmbGetPostEditData'] = 'mmb_post_get_edit_data';
        $methods['mmbUpdatePost'] = 'mmb_post_update';
        $methods['mmbCreatePost'] = 'mmb_post_create';
        $methods['mmbPublishPost'] = 'mmb_post_publish';
		$methods['mmbPostChecksum'] = 'mmb_post_checksum';

        //comments
        $methods['mmbRestoreComment'] = 'mmb_restore_comment';
        $methods['mmbGetCommentCount'] = 'mmb_get_comment_count';
        $methods['mmbBulkEditComment'] = 'mmb_bulk_edit_comment';
        
        //users
        $methods['mmbUserChangePassword'] = 'mmb_user_change_password';

        //Backup/Restore
        $methods['mmbBackupNow'] = 'mmb_backup_now';
        $methods['mmbRestoreNow'] = 'mmb_restore_now';
        $methods['mmbGetBackupUrl'] = 'mmb_get_backup_url';
        $methods['mmbWeeklyBackup'] = 'mmb_weekly_backup';
        $methods['mmbLastWorkerMessage'] = 'mmb_geet_last_worker_message';
        return $methods;
    }

    /**
    * Gets an instance of the Comment class
    * 
    */
    function get_comment_instance()
    {
        if (!isset($this->comment_instance))
        {
            $this->comment_instance = new Mmb_Comment();
        }
        
        return $this->comment_instance;
    }
    
    /**
    * Gets an instance of the Plugin class
    * 
    */
    function get_plugin_instance()
    {
        if (!isset($this->plugin_instance))
        {
            $this->plugin_instance = new Mmb_Plugin();
        }
        
        return $this->plugin_instance;
    }
    
    /**
    * Gets an instance of the Theme class
    * 
    */
    function get_theme_instance()
    {
        if (!isset($this->theme_instance))
        {
            $this->theme_instance = new Mmb_Theme();
        }
        
        return $this->theme_instance;
    }
    
    /**
    * Gets an instance of Mmb_Page class
    * 
    */
    function get_page_instance()
    {
        if (!isset($this->page_instance))
        {
            $this->page_instance = new Mmb_Page();
        }
        
        return $this->page_instance;
    }
    
    /**
    * Gets an instance of Mmb_Post class
    * 
    */
    function get_post_instance()
    {
        if (!isset($this->post_instance))
        {
            $this->post_instance = new Mmb_Post();
        }
        
        return $this->post_instance;
    }
    
    /**
    * Gets an instance of Category class
    * 
    */
    function get_category_instance()
    {
        if (!isset($this->category_instance))
        {
            $this->category_instance = new Mmb_Category();
        }
        
        return $this->category_instance;
    }

     /**
    * Gets an instance of Tag class
    *
    */
    function get_tag_instance()
    {
        if (!isset($this->tag_instance))
        {
            $this->tag_instance = new Mmb_Tags();
        }

        return $this->tag_instance;
    }
    
    /**
    * Gets an instance of the WP class
    * 
    */
    function get_wp_instance()
    {
        if (!isset($this->wp_instance))
        {
            $this->wp_instance = new Mmb_WP();
        }
        
        return $this->wp_instance;
    }
    
    /**
    * Gets an instance of User
    * 
    */
    function get_user_instance()
    {
        if (!isset($this->user_instance))
        {
            $this->user_instance = new Mmb_User();
        }
        
        return $this->user_instance;
    }
    
    /**
    * Gets an instance of stats class
    * 
    */
    function get_stats_instance()
    {
        if (!isset($this->stats_instance))
        {
            $this->stats_instance = new Mmb_Stats();
        }
        
        return $this->stats_instance;
    }
    
    /**
    * Gets an instance of stats class
    *
    */
    function get_backup_instance()
    {
        if (!isset($this->backup_instance))
        {
            $this->backup_instance = new Mmb_Backup();
        }

        return $this->backup_instance;
    }
    
    function install()
    {
        //no need to check just update the table will run only when plugin installs
        update_option('enable_xmlrpc', 1);
    }
    
    /**
    * Saves the (modified) options into the database
    * 
    */
    function _save_options()
    {
        if (get_option($this->slug))
        {
            update_option($this->slug, $this->settings);
        }
        else
        {
            add_option($this->slug, $this->settings);
        }
    }
    
    /**
    * Constructs a url (for ajax purpose)
    * 
    * @param mixed $base_page
    */
    function _construct_url($params = array(), $base_page = 'index.php')
    {
        $url = "$base_page?_wpnonce=" . wp_create_nonce($this->slug);
        foreach ($params as $key => $value)
        {
            $url .= "&$key=$value";
        }
        
        return $url;
    }

    function update_this_plugin($args) {
        $this->_escape($args);

        $username = $args[0];
        $password = $args[1];
        $url = $args[2];
//        return array('test' => 'hello there');

        if (!$user = $this->login($username, $password))
        {
            return $this->error;
        }
        if (!current_user_can('administrator'))
        {
            return new IXR_Error(401, 'Sorry, Only administrators can upgrade this plugin on the remote blog.');
        }
        
		include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		
		ob_start();
		@unlink(WP_PLUGIN_DIR.'/worker');
		$upgrader = new Plugin_Upgrader();
		//$deactivate = $upgrader->deactivate_plugin_before_upgrade(false, 'worker/init.php');
		$result = $upgrader->run(array(
						'package' => $url,
						'destination' => WP_PLUGIN_DIR,
						'clear_destination' => true,
						'clear_working' => true,
						'hook_extra' => array(
								'plugin' => 'worker/init.php'
						)));
		ob_end_clean();
		if(is_wp_error($result) || !$result){
			$error = is_wp_error($result) ? $result->get_error_message() : 'Check your FTP details. <a href="http://managewp.com/user-guide#ftp" title="More Info" target="_blank">More Info</a>' ;
			$this->_last_worker_message(array('error' => print_r($error, true)));
		}else {
			$data = get_plugin_data(WP_PLUGIN_DIR . '/' . $upgrader->plugin_info());
			$this->_last_worker_message(array('success' => $upgrader->plugin_info(), 'name' => $data['Name'], 'activate' => print_r($activate, true)));
		}

    }

    /**
    * Logs a user int
    * 
    * @param mixed $username
    * @param mixed $password
    * @return WP_Error|WP_User
    */
    function login($username, $password)
    {
        if (!get_option( 'enable_xmlrpc'))
        {
            update_option('enable_xmlrpc', 1);
//            return new IXR_Error(405, 'XML-RPC services are disabled on this blog.');
        }
        
        $user = wp_authenticate($username, $password);

        if (is_wp_error($user)) {
            $this->error = new IXR_Error(403, __('Bad login/pass combination.'));
            return false;
        }

        set_current_user( $user->ID );
        return $user;
    }
}