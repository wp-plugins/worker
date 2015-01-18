<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_WordPress_Provider_Theme implements MWP_WordPress_Provider_Interface
{
    const STATUS_ACTIVE = 'active';

    const STATUS_INACTIVE = 'inactive';

    const STATUS_INHERITED = 'inherited';

    private $context;

    public function __construct(MWP_WordPress_Context $context)
    {
        $this->context = $context;
    }

    public function fetch()
    {
        $rawThemes = $this->context->getThemes();
        $themeMap  = array();
        $themes    = array();

        $themeInfo = array(
            'name'        => 'Name',
            // Absolute path to theme directory.
            'root'        => 'Theme Root',
            // Absolute URL to theme directory.
            'rootUri'     => 'Theme Root URI',

            'version'     => 'Version',
            'description' => 'Description',
            'author'      => 'Author',
            'authorUri'   => 'Author URI',
            'status'      => 'Status',
            'parent'      => 'Parent Theme',
        );

        foreach ($rawThemes as $rawTheme) {
            $theme = array(
                // Theme directory, followed by slash and slug, to keep it consistent with plugin info; ie. "twentytwelve/twentytwelve".
                'basename' => $rawTheme['Template'].'/'.$rawTheme['Stylesheet'],
                // A.k.a. "stylesheet", for some reason. This is the theme identifier; ie. "twentytwelve".
                'slug'     => $rawTheme['Stylesheet'],
            );

            foreach ($themeInfo as $property => $info) {
                $theme[$property] = !empty($rawTheme[$info]) ? $rawTheme[$info] : null;
            }

            $themes[]                 = $theme;
            $themeMap[$theme['name']] = $theme;
        }

        // Link parents and children.
        foreach ($themes as $theme) {
            if ($theme['parent'] !== null) {
                $theme['parent']               = $themeMap[$theme['parent']];
                $theme['parent']['children'][] = $theme['basename'];
            }
        }

        $this->markInheritedThemes($themes);

        return $themes;
    }

    /**
     * Marks inherited themes as such.
     *
     * @param $themes array[]
     */
    private function markInheritedThemes(&$themes)
    {
        foreach ($themes as $theme) {
            if ($theme['status'] !== self::STATUS_ACTIVE) {
                continue;
            }
            while ($parent = $theme['parent']) {
                $parent->status = self::STATUS_INHERITED;
            }
        }
    }
}
