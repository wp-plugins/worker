<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Action_IncrementalBackup_UploadCloner extends MWP_Action_Abstract
{
    public function execute(array $params = array(), MWP_Worker_Request $request)
    {
        $files = $params['files'];

        $rootPath   = ABSPATH;
        $filesystem = new Symfony_Filesystem_Filesystem();

        try {
            foreach ($files as $file) {
                $realpath = $rootPath.$file['pathname'];
                if ($file['dir'] === true) {
                    $filesystem->mkdir($realpath);
                } else {
                    // Files contents are sent as base64 encoded strings
                    // mod_security scans request payload for PHP code and blocks the request
                    // base64 is just a workaround which passes the mod_security check
                    $filesystem->dumpFile($realpath, base64_decode($file['contents']), 0644);
                }
            }
        } catch (Symfony_Filesystem_Exception_IOException $e) {
            throw new MWP_Worker_Exception(MWP_Worker_Exception::IO_EXCEPTION, $e->getMessage());
        }

        return array();
    }
}
