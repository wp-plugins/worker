<?php

/**
 * Thrown if the redirect URL was missing parameters or if the given parameters were not valid.
 *
 * The recommended action is to show an HTTP 400 error page.
 */
class Dropbox_WebAuthException_BadRequest extends Dropbox_Exception
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
