<?php

/**
 * Thrown if Dropbox returns some other error about the authorization request.
 */
class Dropbox_WebAuthException_Provider extends Dropbox_Exception
{
    /**
     * @param string $message
     *
     * @internal
     */
    public function __construct($message)
    {
        parent::__construct($message);
    }
}
