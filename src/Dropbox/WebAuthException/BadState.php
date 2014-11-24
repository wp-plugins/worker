<?php

/**
 * Thrown if all the parameters are correct, but there's no CSRF token in the session.  This
 * probably means that the session expired.
 *
 * The recommended action is to redirect the user's browser to try the approval process again.
 */
class Dropbox_WebAuthException_BadState extends Dropbox_Exception
{
    /**
     * @internal
     */
    public function __construct()
    {
        parent::__construct("Missing CSRF token in session.");
    }
}
