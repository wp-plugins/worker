<?php

/**
 * When this SDK can't understand the response from the server.  This could be due to a bug in this
 * SDK or a buggy response from the Dropbox server.
 */
class Dropbox_Exception_BadResponse extends Dropbox_Exception_ProtocolError
{
    /**
     * @internal
     */
    public function __construct($message)
    {
        parent::__construct($message);
    }
}
