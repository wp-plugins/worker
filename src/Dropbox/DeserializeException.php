<?php

/**
 * If, when loading a serialized {@link RequestToken} or {@link AccessToken}, the input string is
 * malformed, this exception will be thrown.
 */
final class Dropbox_DeserializeException extends Dropbox_Exception
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
