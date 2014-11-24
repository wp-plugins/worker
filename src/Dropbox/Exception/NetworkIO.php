<?php

/**
 * There was a network I/O error when making the request.
 */
final class Dropbox_Exception_NetworkIO extends Dropbox_Exception
{
    /**
     * @internal
     */
    public function __construct($message, $cause = null)
    {
        parent::__construct($message, $cause);
    }
}
