<?php

/**
 * Thrown if the given 'state' parameter doesn't contain the CSRF token from the user's session.
 * This is blocked to prevent CSRF attacks.
 *
 * The recommended action is to respond with an HTTP 403 error page.
 */
class Dropbox_WebAuthException_Csrf extends Dropbox_Exception
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
