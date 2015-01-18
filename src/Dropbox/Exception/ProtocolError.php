<?php

/**
 * There was an protocol misunderstanding between this SDK and the server.  One of us didn't
 * understand what the other one was saying.
 */
class Dropbox_Exception_ProtocolError extends Dropbox_Exception
{
    /**
     * @internal
     */
    public function __construct($message)
    {
        parent::__construct($message);
    }
}
