<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Http_JsonResponse extends MWP_Http_Response
{
    public function __construct($content, $statusCode = 200, array $headers = array())
    {
        $headers['content-type'] = 'application/json';
        parent::__construct($content, $statusCode, $headers);
    }

    public function getContentAsString()
    {
        return "\n".json_encode($this->content);
    }
}
