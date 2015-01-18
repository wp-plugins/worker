<?php

/**
 * Thrown when there's an error reading from a stream that was passed in by the caller.
 */
class Dropbox_StreamReadException extends Dropbox_Exception
{
    /**
     * @internal
     */
    public function __construct($message, $cause = null)
    {
        parent::__construct($message, 0, $cause);
    }
}
