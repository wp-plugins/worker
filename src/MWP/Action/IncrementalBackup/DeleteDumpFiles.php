<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

// This action does not delete dump files only - it can delete any existing files, so be careful in using it
class MWP_Action_IncrementalBackup_DeleteDumpFiles extends MWP_Action_Abstract
{
    public function execute(array $params = array())
    {
        $files = $params['files'];

        $result = array();

        foreach ($files as $pathname) {
            $successful = @unlink(ABSPATH.$pathname);

            if (!$successful) {
                $error   = error_get_last();
                $message = isset($error['message']) ? $error['message'] : null;
            } else {
                $message = null;
            }

            $result[] = array(
                'pathname'     => $pathname,
                'successful'   => $successful,
                'errorMessage' => $message,
            );
        }

        return array('files' => $result);
    }
}
