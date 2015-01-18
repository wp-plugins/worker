<?php

/**
 * Thrown when the server tells us that our request was invalid.  This is typically due to an
 * HTTP 400 response from the server.
 */
final class Dropbox_Exception_BadRequest extends Dropbox_Exception_ProtocolError
{
    /**
     * @internal
     */
    public function __construct($message = "")
    {
        parent::__construct($message);
    }
}
