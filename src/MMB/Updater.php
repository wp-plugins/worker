<?php

class MMB_Updater
{
    private function __construct()
    {
    }

    public static function isSupported()
    {
        global $wp_version;

        if (version_compare($wp_version, '3.7', '<')) {
            return false;
        }

        return true;
    }

    public static function register()
    {
        if (!self::isSupported()) {
            return;
        }

        $updater = new self();

        $autoUpdateCore = get_option('mwp_core_autoupdate');

        if ($autoUpdateCore === 'never') {
            add_filter('allow_minor_auto_core_updates', '__return_false', 100);
            add_filter('allow_major_auto_core_updates', '__return_false', 100);
        } elseif ($autoUpdateCore === 'minor') {
            add_filter('allow_minor_auto_core_updates', '__return_true', 100);
            add_filter('allow_major_auto_core_updates', '__return_false', 100);
        } elseif ($autoUpdateCore === 'major') {
            add_filter('allow_minor_auto_core_updates', '__return_true', 100);
            add_filter('allow_major_auto_core_updates', '__return_true', 100);
        }

        add_filter('auto_update_plugin', array($updater, 'updatePlugin'), 100, 2);
        add_filter('auto_update_theme', array($updater, 'updateTheme'), 100, 2);
        add_filter('auto_update_translation', array($updater, 'updateTranslation'), 100, 1);
    }

    public function updatePlugin($update, $item)
    {
        /*
          {
            "id": "11780",
            "slug": "bbpress",
            "plugin": "bbpress\/bbpress.php",
            "new_version": "2.5.3",
            "url": "https:\/\/wordpress.org\/plugins\/bbpress\/",
            "package": "https:\/\/downloads.wordpress.org\/plugin\/bbpress.2.5.3.zip"
          }
         */
        $slug = $item->plugin;
        if ($slug == 'worker/init.php') {
            return false;
        }
        $alwaysUpdatePlugins = get_option('mwp_global_plugins_autoupdate', 'disabled');

        if ($alwaysUpdatePlugins === 'enabled') {
            return true;
        }
        $whitelistedPlugins = get_option('mwp_active_autoupdate_plugins', array());

        if (in_array($slug, $whitelistedPlugins)) {
            return true;
        }

        return $update;
    }

    public function updateTheme($update, $item)
    {
        /*
          {
            "theme": "twentyfourteen",
            "new_version": "1.1",
            "url": "https:\/\/wordpress.org\/themes\/twentyfourteen",
            "package": "https:\/\/wordpress.org\/themes\/download\/twentyfourteen.1.1.zip"
          }
         */
        $slug = $item->theme;
        $alwaysUpdateThemes = get_option('mwp_global_themes_autoupdate', 'disabled');

        if ($alwaysUpdateThemes === 'enabled') {
            return true;
        }

        $whitelistedThemes = get_option('mwp_active_autoupdate_themes', array());

        if (in_array($slug, $whitelistedThemes)) {
            return true;
        }

        return $update;
    }

    public function updateTranslation($update)
    {
        $alwaysUpdateTranslations = get_option('mwp_global_translations_autoupdate', 'disabled');

        if ($alwaysUpdateTranslations === 'enabled') {
            return true;
        }

        return $update;
    }

    /**
     * @api
     *
     * @param $args
     *
     * @return array
     */
    public static function setSettings($args)
    {
        if (!self::isSupported()) {
            return array(
                'error' => "This functionality requires at least WordPress version 3.7",
            );
        }

        $type = $args['type'];

        switch ($type) {
            case 'plugins':
            case 'themes':
                self::setAutoUpdateSettings($args['items'], $type);
                break;
            case 'core_never':
            case 'core_minor':
            case 'core_major':
                // Get the last segment, 'core_never' will become 'never'.
                $value = explode('_', $type, 2);
                $value = $value[1];
                update_option('mwp_core_autoupdate', $value);
                break;
            case 'global_plugins_update':
                update_option('mwp_global_plugins_autoupdate', 'enabled');
                break;
            case 'global_plugins_update_disable':
                update_option('mwp_global_plugins_autoupdate', 'disabled');
                break;
            case 'global_themes_update':
                update_option('mwp_global_themes_autoupdate', 'enabled');
                break;
            case 'global_themes_update_disable':
                update_option('mwp_global_themes_autoupdate', 'disabled');
                break;
            case 'global_translations_update':
                update_option('mwp_global_translations_autoupdate', 'enabled');
                break;
            case 'global_translations_update_disable':
                update_option('mwp_global_translations_autoupdate', 'disabled');
                break;
        }

        return array(
            'success' => "Successfully updated.",
        );
    }

    private static function setAutoUpdateSettings($items, $type)
    {
        $return = array();
        foreach ($items as $item) {
            if($type == 'plugins'){
                $pluginOrTheme  = plugin_basename($item['path']);
            }else{
                $pluginOrTheme = $item['name'];
            }
            $current = get_option('mwp_active_autoupdate_'.$type, array());
            if ($item['action'] === 'on') {
                $current[] = $pluginOrTheme;
            } else {
                $current = array_diff($current, array($pluginOrTheme));
            }
            sort($current);
            update_option('mwp_active_autoupdate_'.$type, $current);
            $return[$item['name']] = 'OK';
        }

        return $return;
    }

    /**
     * @api
     *
     * @param $args
     *
     * @return array
     */
    public static function getSettings($args)
    {
        if (!isset($args['items']) || !is_array($args['items'])) {
            return array(
                'error' => "No requested items provided (plugins or themes).",
            );
        }
        $items = $args['items'];

        $return = array();
        $search = empty($args['search']) ? null : $args['search'];
        if (in_array('plugins', $items)) {
            $return['plugins'] = self::getPluginSettings($search);
        }
        if (in_array('themes', $items)) {
            $return['themes'] = self::getThemeSettings($search);
        }

        return $return;
    }

    private function getPluginSettings($search = null)
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH.'wp-admin/includes/plugin.php';
        }
        $allPlugins = get_plugins();
        $plugins    = array(
            'active'   => array(),
            'inactive' => array(),
        );

        $whitelistedPlugins = get_option('mwp_active_autoupdate_plugins', array());
        foreach ($allPlugins as $slug => $pluginInfo) {
            if ($slug === 'worker/init.php') {
                continue;
            }

            if (!empty($search) && stripos($pluginInfo['Name'], $search) === false) {
                continue;
            }
            $key = 'inactive';

            if (in_array($slug, $whitelistedPlugins)) {
                $key = 'active';
            }
            $plugins[$key][] = array(
                'path'    => $slug,
                'name'    => $pluginInfo['Name'],
                'version' => $pluginInfo['Version'],
            );
        }

        return $plugins;
    }

    private static function getThemeSettings($search = null)
    {
        if (!function_exists('wp_get_themes')) {
            include_once ABSPATH.WPINC.'/theme.php';
        }

        if (!function_exists('wp_get_themes')) {
            return array();
        }
        $themes    = array(
            'active'   => array(),
            'inactive' => array(),
        );
        $allThemes = wp_get_themes();

        $whitelistedThemes = get_option('mwp_active_autoupdate_themes', array());
        foreach ($allThemes as $slug => $themeInfo) {
            /** @var WP_Theme $themeInfo */
            if (!empty($search) && stripos($themeInfo->name, $search) === false) {
                continue;
            }
            $key = 'inactive';

            if (in_array($slug, $whitelistedThemes)) {
                $key = 'active';
            }
            $themes[$key][] = array(
                'path'       => $slug,
                'name'       => $themeInfo->name,
                'version'    => $themeInfo->version,
                'stylesheet' => $themeInfo->stylesheet,
            );
        }

        return $themes;
    }
}
