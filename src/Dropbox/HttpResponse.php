<?php

/**
 * @internal
 */
final class Dropbox_HttpResponse
{
    public $statusCode;
    public $body;

    function __construct($statusCode, $body)
    {
        $this->statusCode = $statusCode;
        $this->body = $body;
    }
}
