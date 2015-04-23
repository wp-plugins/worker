<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Http_MultipartResponsePart
{

    /** @var array */
    private $headers = array();

    /** @var MWP_Stream_Interface */
    private $body;

    function __construct($headers, $body = null)
    {
        $this->headers = array_change_key_case($headers, CASE_LOWER);
        $this->setBody($body);
    }

    /**
     * @param string $header
     * @param string $value
     */
    public function setHeader($header, $value)
    {
        $this->headers[strtolower($header)] = $value;
    }

    /**
     * @param $header
     *
     * @return string|null
     */
    public function getHeader($header)
    {
        if ($this->hasHeader($header)) {
            return $this->headers[strtolower($header)];
        }

        return null;
    }

    /**
     * @param string $header
     *
     * @return bool
     */
    public function hasHeader($header)
    {
        return isset($this->headers[strtolower($header)]);
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @return MWP_Stream_Interface|null
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * @param MWP_Stream_Interface $body
     */
    public function setBody($body)
    {
        if ($body !== null && !$body instanceof MWP_Stream_Interface) {
            $body = MWP_Stream_Stream::factory($body);
        }

        $this->body = $body;
    }
}
