<?php

class MMB_Security extends MMB_Core
{
    function security_check($args)
    {

        if (MMB_Security::prevent_listing_ok()) {
            $output["prevent_listing_ok"] = true;
        } else {
            $output["prevent_listing_ok"] = false;
        }

        if (MMB_Security::remove_wp_version_ok()) {
            $output["remove_wp_version_ok"] = true;
        } else {
            $output["remove_wp_version_ok"] = false;
        }

        if (MMB_Security::remove_database_reporting_ok()) {
            $output["remove_database_reporting_ok"] = true;
        } else {
            $output["remove_database_reporting_ok"] = false;
        }

        if (MMB_Security::remove_php_reporting_ok()) {
            $output["remove_php_reporting_ok"] = true;
        } else {
            $output["remove_php_reporting_ok"] = false;
        }

        if (MMB_Security::admin_user_ok()) {
            $output["admin_user_ok"] = true;
        } else {
            $output["admin_user_ok"] = false;
        }

        if (MMB_Security::htaccess_permission_ok()) {
            $output["htaccess_permission_ok"] = true;
        } else {
            $output["htaccess_permission_ok"] = false;
        }
        if (MMB_Security::remove_scripts_version_ok() && MMB_Security::remove_styles_version_ok()) {
            $output["remove_scripts_and_styles_version_ok"] = true;
        } else {
            $output["remove_scripts_and_styles_version_ok"] = false;
        }
        if (MMB_Security::file_permission_ok()) {
            $output["file_permission_ok"] = true;
        } else {
            $output["file_permission_ok"] = false;
        }

        return $output;

    }

    function security_fix_dir_listing($args)
    {
        MMB_Security::prevent_listing();

        return $this->security_check($args);
    }

    function security_fix_permissions($args)
    {
        MMB_Security::file_permission();

        return $this->security_check($args);
    }

    function security_fix_php_reporting($args)
    {
        MMB_Security::remove_php_reporting();

        return $this->security_check($args);
    }

    function security_fix_database_reporting($args)
    {
        MMB_Security::remove_database_reporting();

        return $this->security_check($args);
    }

    function security_fix_wp_version($args)
    {
        MMB_Security::remove_wp_version();

        return $this->security_check($args);
    }

    function security_fix_admin_username($args)
    {
        $username = $args[0];
        MMB_Security::change_admin_username($username);
        $scan_res                  = $this->security_check($args);
        $scan_res["admin_user_ok"] = true;

        return $scan_res;
    }

    function security_fix_scripts_styles($args)
    {

        MMB_Security::remove_styles_version();
        MMB_Security::remove_scripts_version();

        return $this->security_check($args);
    }

    function security_fix_htaccess_permission($args)
    {
        MMB_Security::htaccess_permission();

        return $this->security_check($args);
    }

    //Prevent listing wp-content, wp-content/plugins, wp-content/themes, wp-content/uploads
    private static $listingDirectories = null;

    private static function init_listingDirectories()
    {
        if (MMB_Security::$listingDirectories == null) {
            $wp_upload_dir                    = wp_upload_dir();
            MMB_Security::$listingDirectories = array(WP_CONTENT_DIR, WP_PLUGIN_DIR, get_theme_root(), $wp_upload_dir['basedir']);
        }
    }

    public static function prevent_listing_ok()
    {
        MMB_Security::init_listingDirectories();
        foreach (MMB_Security::$listingDirectories as $directory) {
            $file = $directory.DIRECTORY_SEPARATOR.'index.php';
            if (!file_exists($file)) {
                return false;
            }
        }

        return true;
    }

    public static function prevent_listing()
    {
        MMB_Security::init_listingDirectories();
        foreach (MMB_Security::$listingDirectories as $directory) {
            $file = $directory.DIRECTORY_SEPARATOR.'index.php';
            if (!file_exists($file)) {
                chmod($directory, 0777);
                $h = fopen($file, 'w');
                fwrite($h, '<?php die(); ?>');
                fclose($h);
                chmod($directory, 0755);
            }
        }
    }

    //Removed wp-version
    public static function remove_wp_version_ok()
    {
        return !(has_action('wp_head', 'wp_generator') || has_filter('wp_head', 'wp_generator'));
    }

    public static function remove_wp_version()
    {
        update_option('mwp_remove_wp_version', 'T');
        if (get_option('mwp_remove_wp_version') == 'T') {
            remove_action('wp_head', 'wp_generator');
            remove_filter('wp_head', 'wp_generator');
        }
    }

    //Database error reporting turned on/off
    public static function remove_database_reporting_ok()
    {
        global $wpdb;

        return ($wpdb->show_errors == false);
    }

    public static function remove_database_reporting()
    {
        global $wpdb;

        $wpdb->hide_errors();
        $wpdb->suppress_errors();
    }

    //PHP error reporting turned on/off
    public static function remove_php_reporting_ok()
    {
        return !(((ini_get('display_errors') != 0) && (ini_get('display_errors') != 'off')) || ((ini_get('display_startup_errors') != 0) && (ini_get('display_startup_errors') != 'off')));
    }

    public static function remove_php_reporting()
    {
        update_option('mwp_remove_php_reporting', 'T');
        if (get_option('mwp_remove_php_reporting') == 'T') {
            @ini_set('display_errors', 'off');
            @ini_set('display_startup_errors', "off");
        }
    }

    //Admin user name is not admin
    public static function admin_user_ok()
    {
        $user = get_user_by('login', 'admin');

        return !($user && ($user->wp_user_level == 10 || (isset($user->user_level) && $user->user_level == 10)));
    }

    public static function change_admin_username($new_username)
    {
        global $wpdb;
        $wpdb->query($wpdb->prepare("Update {$wpdb->prefix}users SET user_login='%s' where user_login='admin'", $new_username));

    }

    //Admin user name is not admin
    public static function htaccess_permission_ok()
    {
        $htaccessPerm = substr(sprintf('%o', fileperms(ABSPATH."/.htaccess")), -4);
        if ($htaccessPerm === "0644" || $htaccessPerm === "0444") {
            return true;
        } else {
            return false;
        }
    }

    public static function htaccess_permission()
    {
        $htaccessPerm = fileperms(ABSPATH.".htaccess");
        $succ         = chmod(ABSPATH.".htaccess", 0644);
    }


    public static function remove_scripts_version_ok()
    {
        return (get_option('managewp_remove_scripts_version') == 'T');

    }

    public static function remove_script_versions($src)
    {
        update_option('managewp_remove_scripts_version', 'T');
        if (get_option('managewp_remove_scripts_version') == 'T') {
            if (strpos($src, '?ver=')) {
                $src = remove_query_arg('ver', $src);
            }

            return $src;
        }

        return $src;
    }

    public static function remove_theme_versions($src)
    {
        update_option('managewp_remove_styles_version', 'T');
        if (get_option('managewp_remove_styles_version') == 'T') {
            if (strpos($src, '?ver=')) {
                $src = remove_query_arg('ver', $src);
            }

            return $src;
        }

        return $src;
    }

    public static function remove_scripts_version()
    {
        update_option('managewp_remove_scripts_version', 'T');
        if (get_option('managewp_remove_scripts_version') == 'T') {
            global $wp_scripts;
            if (!is_a($wp_scripts, 'WP_Scripts')) {
                return;
            }

            foreach ($wp_scripts->registered as $handle => $script) {
                $wp_scripts->registered[$handle]->ver = null;
            }
        }
    }

    public static function remove_styles_version_ok()
    {
        return (get_option('managewp_remove_styles_version') == 'T');
    }

    public static function remove_styles_version()
    {
        update_option('managewp_remove_styles_version', 'T');
        if (get_option('managewp_remove_styles_version') == 'T') {
            global $wp_styles;
            if (!is_a($wp_styles, 'WP_Styles')) {
                return;
            }

            foreach ($wp_styles->registered as $handle => $style) {
                $wp_styles->registered[$handle]->ver = null;
            }
        }
    }

    public static function file_permission_ok($dir = ABSPATH)
    {
        $files = scandir($dir);
        $dir   = rtrim($dir, "/");
        foreach ($files as $file) {
            if ($file !== "." && $file !== ".." && $file !== "wp-admin" && $file !== "wp-includes" && $file !== "plugins" && $file !== "themes" && $file !== "uploads") {
                if (is_dir($dir."/".$file)) {
                    $dirPerm = substr(sprintf('%o', fileperms($dir."/".$file)), -4);
                    if ($dirPerm !== "0755") {
                        return false;
                    }
                    $res = MMB_Security::file_permission_ok($dir."/".$file);
                    if ($res === false) {
                        return false;
                    }
                } else {
                    $filePerm = substr(sprintf('%o', fileperms($dir."/".$file)), -4);
                    if ($filePerm !== "0644") {
                        return false;
                    }
                }

            }
        }

        return true;
    }

    public static function file_permission($dir = ABSPATH)
    {
        $files = scandir($dir);
        $dir   = rtrim($dir, "/");
        foreach ($files as $file) {
            if ($file !== "." && $file !== ".." && $file !== "wp-admin" && $file !== "wp-includes" && $file !== "plugins" && $file !== "themes" && $file !== "uploads") {
                if (is_dir($dir."/".$file)) {
                    $dirPerm = substr(sprintf('%o', fileperms($dir."/".$file)), -4);
                    if ($dirPerm !== "0755") {
                        chmod($dir."/".$file, 0755);
                    }

                    MMB_Security::file_permission($dir."/".$file);
                } else {
                    $filePerm = substr(sprintf('%o', fileperms($dir."/".$file)), -4);
                    if ($filePerm !== "0644") {
                        chmod($dir."/".$file, 0644);
                    }
                }
            }

        }
    }

    public function security_fix_all($args) // Potrebno dosta promena pre release
    {
        $user_name = $args['new_user_name'];

        if ($args['fix_listing']) {
            MMB_Security::security_fix_dir_listing($args); // ovo drugacije implementirati, ovako ne moze
        }
        if ($args['fix_wp_version']) {
            MMB_Security::security_fix_wp_version($args);
        }
        if ($args['fix_database_reporting']) {
            MMB_Security::security_fix_database_reporting($args);  // treba da iskljuci samo prikazivanje errora
        }
        if ($args['fix_php_reporting']) {
            MMB_Security::security_fix_php_reporting($args); // treba da iskljuci samo prikazivanje errora
        }
        if ($args['security_fix_scripts_styles']) {
            MMB_Security::security_fix_scripts_styles($args); // ovo skloniti
        }
        if ($args['fix_htaccess_permission']) {
            MMB_Security::security_fix_htaccess_permission($args); // skloniti, sve bez chmod-a
        }
        if ($args['security_fix_permissions']) {
            MMB_Security::security_fix_permissions($args); // skloniti, sve bez chmod-a
        }
        $scan_res = $this->security_check($args);
        if ($args['fix_admin_username']) {
            $params[] = $user_name;
            MMB_Security::security_fix_admin_username($params);
            $scan_res["admin_user_ok"] = true;
        }

        return $scan_res;
    }


}
