<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Http_Response implements MWP_Http_ResponseInterface
{

    protected $content;

    protected $headers = array();

    public function __construct($content, $headers = array())
    {
        $this->content = $content;
        $this->headers = array_change_key_case($headers, CASE_LOWER);
    }

    public function getContentAsString()
    {
        return $this->content;
    }

    /**
     * @param mixed $content
     *
     * @return mixed
     */
    public function setContent($content)
    {
        $this->content = $content;
    }

    /**
     * @return mixed
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * @return void
     */
    public function send()
    {
        $this->sendHeaders();
        print $this->getContentAsString();
    }

    protected function sendHeaders()
    {
        if (headers_sent()) {
            return;
        }

        foreach ($this->headers as $headerName => $headerValue) {
            header(sprintf('%s: %s', $headerName, $headerValue), true);
        }
    }
}
