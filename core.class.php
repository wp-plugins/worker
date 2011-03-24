<?php

class MMB_Core extends MMB_Helper
{
    var $name;
    var $slug;
    var $settings;
    var $remote_client;
    var $comment_instance;
    var $plugin_instance;
    var $theme_instance;
    var $wp_instance;
    var $post_instance;
    var $stats_instance;
    var $user_instance;
    var $backup_instance;

    
    function __construct(){
        global $mmb_plugin_dir;

        $this->name = 'Manage Multiple Blogs';
        $this->slug = 'manage-multiple-blogs';
        $this->settings = get_option($this->slug);
        if (!$this->settings)
        {
            $this->settings = array(
                'blogs'         => array(),
                'current_blog'  => array('type' => null)
            );
        }
        add_action('rightnow_end', array($this, 'add_right_now_info'));
        add_action('wp_footer', array('MMB_Stats', 'set_hit_count'));
        register_activation_hook($mmb_plugin_dir.'/init.php', array($this, 'install'));
        add_action('init', array($this, 'automatic_login'));
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
    * Gets an instance of the Comment class
    * 
    */
    function get_comment_instance()
    {
        if (!isset($this->comment_instance))
        {
            $this->comment_instance = new MMB_Comment();
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
            $this->plugin_instance = new MMB_Plugin();
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
            $this->theme_instance = new MMB_Theme();
        }
        
        return $this->theme_instance;
    }
    
    
    /**
    * Gets an instance of MMB_Post class
    * 
    */
    function get_post_instance()
    {
        if (!isset($this->post_instance))
        {
            $this->post_instance = new MMB_Post();
        }
        
        return $this->post_instance;
    }
    
    /**
    * Gets an instance of Blogroll class
    * 
    */
    function get_blogroll_instance()
    {
        if (!isset($this->blogroll_instance))
        {
            $this->blogroll_instance = new MMB_Blogroll();
        }
        
        return $this->blogroll_instance;
    }

   
    
    /**
    * Gets an instance of the WP class
    * 
    */
    function get_wp_instance()
    {
        if (!isset($this->wp_instance))
        {
            $this->wp_instance = new MMB_WP();
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
            $this->user_instance = new MMB_User();
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
            $this->stats_instance = new MMB_Stats();
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
            $this->backup_instance = new MMB_Backup();
        }

        return $this->backup_instance;
    }
    
    /**
    * Plugin install callback function
    * Check PHP version
    */
    function install()
    {	
		delete_option('_worker_nossl_key');
		delete_option('_worker_public_key');
		delete_option('_action_message_id');
		if(PHP_VERSION < 5)
			exit("<p>Plugin could not be activated. Your PHP version must be 5.0 or higher.</p>");
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
    * Deletes options for communication with master
    * 
    */
		function uninstall(){
			delete_option('_worker_nossl_key');
			delete_option('_worker_public_key');
			delete_option('_action_message_id');
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

		/**
    * Worker update
    * 
    */
		function update_worker_plugin($params) {
		
		extract($params);
		if($download_url){
			
			include_once(ABSPATH . 'wp-admin/includes/file.php');
			include_once ABSPATH . 'wp-admin/includes/misc.php';
			include_once ABSPATH . 'wp-admin/includes/template.php';
			include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			
			ob_start();
			@unlink(dirname(__FILE__));
			$upgrader = new Plugin_Upgrader();
			$result = $upgrader->run(array(
						'package' => $download_url,
						'destination' => WP_PLUGIN_DIR,
						'clear_destination' => true,
						'clear_working' => true,
						'hook_extra' => array(
								'plugin' => 'worker/init.php'
						)));
			ob_end_clean();
			if(is_wp_error($result) || !$result){
				return array('error' => 'Manage WP Worker could not been upgraded.');
			}
			else{
				return array('success' => 'Manage WP Worker plugin successfully upgraded.');
			}
		}
		return array('error' => 'Bad download path for worker installation file.');
	 }
   
    /**
    * Automatically logs in when called from Master
    * 
    */
    function automatic_login(){
			
			
				$where = ($_GET['mwp_goto']);
				if (!is_user_logged_in() && $_GET['auto_login']) {
					$signature = base64_decode($_GET['signature']);
					$message_id = trim($_GET['message_id']);
					$username = $_GET['username'];
					
					$auth = $this->_authenticate_message($where.$message_id, $signature, $message_id);
					if($auth === true){
						$user = get_user_by('login', $username);
						$user_id = $user->ID;
						wp_set_current_user($user_id, $username);
						wp_set_auth_cookie( $user_id );
						do_action('wp_login', $username);
					}
					else 
					{
						wp_die($auth['error']);
					}
				}
			
			if($_GET['auto_login']){
					wp_redirect(get_bloginfo('url')."/wp-admin/".$where);
					exit();
				}
	}

		
}