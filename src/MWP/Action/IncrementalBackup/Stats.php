<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Action_IncrementalBackup_Stats extends MWP_Action_IncrementalBackup_Abstract
{

    public function execute(array $params = array(), MWP_Worker_Request $request)
    {

        $wpdb     = $this->container->getWordPressContext()->getDb();
        $getState = new MWP_Action_GetState();
        $getState->setContainer($this->container);
        $themesKey                       = MWP_Action_GetState::THEMES;
        $themes                          = $getState->execute(array($themesKey => array('type' => $themesKey, 'options' => array())));
        $pluginsKey                      = MWP_Action_GetState::PLUGINS;
        $plugins                         = $getState->execute(array($pluginsKey => array('type' => $pluginsKey, 'options' => array())));
        $statistics                      = array();
        $statistics['themes']            = count($themes[$themesKey]['result']);
        $statistics['plugins']           = count($plugins[$pluginsKey]['result']);
        $statistics['posts']             = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='post'");
        $statistics['pages']             = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='page'");
        $statistics['uploads']           = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='attachment'");
        $statistics['comments']          = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments}");
        $statistics['latest_post_title'] = $wpdb->get_var("SELECT post_title FROM {$wpdb->posts} WHERE post_type='post' AND post_status='publish' ORDER BY ID DESC LIMIT 1");
        $statistics['wp_version']        = $this->container->getWordPressContext()->getVersion();
        $currentTheme                    = $this->container->getWordPressContext()->getCurrentTheme();
        $statistics['active_theme']      = $currentTheme['Name'].' v'.$currentTheme['Version'];
        $statistics['platform']          = strtoupper(substr(PHP_OS, 0, 3));

        if (!empty($params['file_count'])) {
            $paths    = !empty($params['file_paths']) ? $params['file_paths'] : array(ABSPATH);
            $pathSize = array();
            foreach ($paths as $path) {
                $pathSize[$path] = $this->getFileCount($path);
            }
            $statistics['file_count'] = $pathSize;
        }

        return $this->createResult(array('statistic' => $statistics));
    }

    /**
     * @param $path
     *
     * @return int
     */
    private function getFileCount($path)
    {
        $size   = 0;
        $ignore = array('.', '..');
        $files  = scandir($path);
        foreach ($files as $file) {
            if (in_array($file, $ignore)) {
                continue;
            }
            if (is_dir(rtrim($path, '/').'/'.$file)) {
                $size += $this->getFileCount(rtrim($path, '/').'/'.$file);
            } else {
                $size++;
            }
        }

        return $size;
    }
}
