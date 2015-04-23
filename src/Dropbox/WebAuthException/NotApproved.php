<?php

/**
 * Thrown if the user chose not to grant your app access to their Dropbox account.
 */
class Dropbox_WebAuthException_NotApproved extends Dropbox_Exception
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
