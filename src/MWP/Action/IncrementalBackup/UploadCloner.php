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
                    $filesystem->dumpFile($realpath, $file['contents'], 0644);
                }
            }
        } catch (Symfony_Filesystem_Exception_IOException $e) {
            throw new MWP_Worker_Exception(MWP_Worker_Exception::IO_EXCEPTION, $e->getMessage());
        }

        return array();
    }
}
